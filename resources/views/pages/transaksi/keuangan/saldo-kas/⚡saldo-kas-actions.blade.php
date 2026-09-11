<?php

// Edit Saldo Awal Tahun (akun kas satu cara bayar) — back-calc mengikuti form 6i:
//   saldo awal tahun = saldo target − arus tahun berjalan (Jan–Des).
// Arus dihitung App\Support\Keuangan\SaldoKas (jurnal langsung dari tabel transaksi),
// bukan view SKVIEW_ACCOUNTS. Pola sirus-php82.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Support\Keuangan\SaldoKas;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $cbId         = '';
    public string $cbDesc       = '';
    public string $accId        = '';
    public string $accDesc      = '';
    public string $accDkStatus  = 'D';
    public string $tanggal      = '';
    /** Shift terakhir yang dihitung ('' = seluruh hari) — ikut induk; siklik selalu ''. */
    public string $shift        = '';
    public string $tahun        = '';
    public string $saldoCurrent = '0';
    public string $saldoTarget  = '0';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    private function bolehEdit(): bool
    {
        return (bool) auth()->user()?->hasRole('Admin');
    }

    #[On('keuangan.saldo-kas.openEdit')]
    public function openEdit(string $cbId, string $tanggal, string $shift = ''): void
    {
        if (!$this->bolehEdit()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }

        $caraBayar = DB::table('skacc_carabayars as cb')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
            ->select('cb.cb_id', 'cb.cb_desc', 'cb.acc_id', 'a.acc_desc', 'a.acc_dk_status')
            ->where('cb.cb_id', $cbId)
            ->first();

        if (!$caraBayar) {
            $this->dispatch('toast', type: 'error', message: 'Cara bayar tidak ditemukan.');
            return;
        }

        $this->cbId        = (string) $caraBayar->cb_id;
        $this->cbDesc      = (string) ($caraBayar->cb_desc ?? '');
        $this->accId       = (string) $caraBayar->acc_id;
        $this->accDesc     = (string) ($caraBayar->acc_desc ?? '');
        $this->accDkStatus = (string) ($caraBayar->acc_dk_status ?: 'D');
        $this->tanggal     = $tanggal;
        $this->shift       = $shift;
        $this->tahun       = substr($tanggal, 0, 4);

        // Rumus 6i (SaldoKas), sama persis dengan angka di tabel induk.
        $this->saldoCurrent = (string) SaldoKas::hitung(
            $this->accId,
            $this->accDkStatus,
            $tanggal,
            $shift !== '' ? $shift : null,
        );
        $this->saldoTarget = $this->saldoCurrent;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-actions');
    }

    public function save(): void
    {
        if (!$this->bolehEdit()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }

        // Sifat akun menentukan KOLOM tujuan (sa_acc_d vs sa_acc_k) — guard whitelist,
        // nilai di luar D/K tidak boleh diam-diam jatuh ke salah satu cabang.
        if (!in_array($this->accDkStatus, ['D', 'K'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Sifat akun (D/K) tidak dikenali — saldo tidak disimpan.');
            return;
        }

        if ($this->accId === '' || $this->tahun === '') {
            $this->dispatch('toast', type: 'error', message: 'Konteks akun/tahun kosong — saldo tidak disimpan.');
            return;
        }

        $this->validate([
            'saldoTarget' => 'required|numeric',
        ], [
            'saldoTarget.required' => 'Saldo target wajib diisi.',
            'saldoTarget.numeric'  => 'Saldo target harus berupa angka.',
        ]);

        $target = (float) $this->saldoTarget;
        $tahun  = (int) $this->tahun;

        // Mengikuti legacy: arus_year = arus sepanjang tahun berjalan (Jan–Des), rumus 6i.
        // updatesaldo = saldo target − arus_year → disimpan sebagai saldo awal tahun.
        $arusTahun   = SaldoKas::arusTahun($this->accId, $this->accDkStatus, $tahun);
        $updateSaldo = $target - $arusTahun;

        $sudahAda = DB::table('sktxn_saldoawalakuns')
            ->where('acc_id', $this->accId)
            ->where('sa_year', (string) $tahun)
            ->exists();

        if ($this->accDkStatus === 'D') {
            $this->simpanSaldoAwal($tahun, $sudahAda, ['sa_acc_d' => $updateSaldo], ['sa_acc_d' => $updateSaldo, 'sa_acc_k' => 0]);
        }

        if ($this->accDkStatus === 'K') {
            $this->simpanSaldoAwal($tahun, $sudahAda, ['sa_acc_k' => $updateSaldo], ['sa_acc_d' => 0, 'sa_acc_k' => $updateSaldo]);
        }

        $this->dispatch('toast', type: 'success',
            message: "Saldo awal tahun {$tahun} di-update untuk akun {$this->accId}.");
        $this->closeModal();
        $this->dispatch('keuangan.saldo-kas.saved');
    }

    /** Update bila baris tahun itu sudah ada, insert bila belum (SKTXN_SALDOAWALAKUNS: acc_id + sa_year). */
    private function simpanSaldoAwal(int $tahun, bool $sudahAda, array $kolomUpdate, array $kolomInsert): void
    {
        if ($sudahAda) {
            DB::table('sktxn_saldoawalakuns')
                ->where('acc_id', $this->accId)
                ->where('sa_year', (string) $tahun)
                ->update($kolomUpdate);
            return;
        }

        DB::table('sktxn_saldoawalakuns')->insert(array_merge([
            'acc_id'  => $this->accId,
            'sa_year' => (string) $tahun,
        ], $kolomInsert));
    }

    public function closeModal(): void
    {
        $this->reset(['cbId', 'cbDesc', 'accId', 'accDesc', 'accDkStatus',
                      'tanggal', 'shift', 'tahun', 'saldoCurrent', 'saldoTarget']);
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'saldo-kas-actions');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-actions" focusable>
        <div class="p-6 space-y-5"
             wire:key="{{ $this->renderKey('modal', [$cbId, $tanggal]) }}">

            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Edit Saldo Awal Tahun
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sistem akan back-calc saldo awal tahun {{ $tahun }} agar saldo per
                    {{ $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : '' }}{{ $shift !== '' ? ' shift ' . $shift : '' }}
                    sama dengan target yang Anda tentukan.
                </p>
            </div>

            <x-border-form title="Konteks">
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Cara Bayar</dt>
                        <dd class="font-medium">{{ $cbId }} — {{ $cbDesc }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Akun</dt>
                        <dd class="font-medium">
                            <span class="font-mono">{{ $accId }}</span> — {{ $accDesc }}
                            <span class="ml-1 px-1.5 text-[10px] rounded {{ $accDkStatus === 'D' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                {{ $accDkStatus }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Saldo Saat Ini</dt>
                        <dd class="font-mono font-medium">
                            Rp {{ number_format((float) $saldoCurrent, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Tahun Saldo Awal</dt>
                        <dd class="font-medium">{{ $tahun }}</dd>
                    </div>
                </dl>
            </x-border-form>

            <div>
                <x-input-label for="saldoTarget" value="Saldo Target Per Tanggal" :required="true" />
                <x-text-input id="saldoTarget" type="number" step="0.01"
                    wire:model.live="saldoTarget"
                    :error="$errors->has('saldoTarget')"
                    class="block w-full mt-1 font-mono" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Saldo awal tahun akan dihitung mundur agar posisi per tanggal = nilai ini.
                </p>
                <x-input-error :messages="$errors->get('saldoTarget')" class="mt-1" />
            </div>

            <details class="text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                    Cara pakai &amp; sumber data
                </summary>
                <div class="px-3 pb-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                    <p>
                        Yang disimpan hanya <b>saldo awal tahun</b> di
                        <span class="font-mono">SKTXN_SALDOAWALAKUNS</span> (kolom
                        <span class="font-mono">sa_acc_d</span> untuk akun D,
                        <span class="font-mono">sa_acc_k</span> untuk akun K) — transaksi tidak diubah sama sekali.
                    </p>
                    <p>
                        Back-calc: <span class="font-mono">saldo awal tahun = saldo target − arus tahun berjalan (1 Jan s/d 31 Des)</span>.
                        Arus diambil dari <span class="font-mono">App\Support\Keuangan\SaldoKas::arusTahun()</span> yang membaca
                        jurnal LANGSUNG dari tabel transaksi (<span class="font-mono">App\Support\Keuangan\Jurnal</span>),
                        bukan view <span class="font-mono">SKVIEW_ACCOUNTS</span>.
                    </p>
                    <p>
                        Rumus 6i: akun D memakai baris <span class="font-mono">txn_acc</span> (<span class="font-mono">txn_d − txn_k</span>),
                        akun K memakai baris <span class="font-mono">txn_acc_k</span> (<span class="font-mono">txn_k − txn_d</span>).
                    </p>
                </div>
            </details>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan Saldo</span>
                    <span wire:loading>Saving...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
