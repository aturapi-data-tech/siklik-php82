<?php

// Cek Saldo Kas — saldo per cara bayar (SKACC_CARABAYARS → acc_id) memakai rumus 6i
// App\Support\Keuangan\SaldoKas, yang membaca jurnal LANGSUNG dari tabel transaksi
// (App\Support\Keuangan\Jurnal), bukan view SKVIEW_ACCOUNTS. Pola sirus-php82.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\Keuangan\SaldoKas;

new class extends Component {
    public string $tanggal = '';
    public string $searchKeyword = '';

    /** Shift terakhir yang dihitung ('' = seluruh hari). Siklik: SKTXN_SHIFTCTLS kosong → selalu ''. */
    public string $shift = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        // Pemilih shift hanya relevan bila definisi shift memang ada.
        $this->shift = SaldoKas::daftarShift() === [] ? '' : SaldoKas::shiftSekarang();
    }

    public function updatedTanggal(): void { /* recompute saldo */ }
    public function updatedShift(): void { /* recompute saldo */ }
    public function updatedSearchKeyword(): void { /* refilter */ }

    // Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset).
    public function resetFilters(): void
    {
        $this->tanggal = now()->toDateString();
        $this->shift = SaldoKas::daftarShift() === [] ? '' : SaldoKas::shiftSekarang();
        $this->searchKeyword = '';
    }

    public function openEdit(string $cbId): void
    {
        if (!$this->isAdmin()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }
        $this->dispatch('keuangan.saldo-kas.openEdit', cbId: $cbId, tanggal: $this->tanggal, shift: $this->shift);
    }

    public function openHistory(string $cbId): void
    {
        $this->dispatch('keuangan.saldo-kas.openHistory', cbId: $cbId, tanggal: $this->tanggal);
    }

    #[On('keuangan.saldo-kas.saved')]
    public function refreshAfterSaved(): void { /* trigger re-render */ }

    public function isAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('Admin');
    }

    /**
     * Saldo per tanggal — rumus Oracle Forms 6i (hitung_saldo_tanggal):
     *   saldo = saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal.
     * Akun D dibaca dari baris txn_acc (D − K), akun K dari baris txn_acc_k (K − D).
     */
    private function hitungSaldoTanggal(string $accId, string $dkStatus, string $tanggal): float
    {
        return SaldoKas::hitung($accId, $dkStatus, $tanggal, $this->shift !== '' ? $this->shift : null);
    }

    #[Computed]
    public function daftarShift(): array
    {
        return SaldoKas::daftarShift();
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('skacc_carabayars as cb')
            ->leftJoin('skacc_accountses as a', 'a.acc_id', '=', 'cb.acc_id')
            ->select('cb.cb_id', 'cb.cb_desc', 'cb.acc_id', 'cb.active_status',
                'a.acc_desc', 'a.acc_dk_status')
            ->where('cb.active_status', '1');

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(cb.cb_id) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(cb.cb_desc) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(cb.acc_id) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(a.acc_desc) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $query->orderBy('cb.cb_desc')->get()->map(function ($caraBayar) {
            $caraBayar->saldo = $this->hitungSaldoTanggal(
                (string) $caraBayar->acc_id,
                (string) ($caraBayar->acc_dk_status ?: 'D'),
                $this->tanggal,
            );
            return $caraBayar;
        });
    }

    #[Computed]
    public function totalSaldo(): float
    {
        return (float) $this->rows->sum('saldo');
    }
};
?>

<div>
    <x-page-title
        title="Saldo Kas Per Tanggal"
        :subtitle="'Posisi saldo kas/bank per tanggal yang dipilih (rumus sama dengan Arus Kas Oracle Forms 6i).' . (!$this->isAdmin() ? ' Mode tampilan saja — edit saldo hanya untuk admin.' : '')" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-52">
                            <x-input-label for="tanggal" value="Saldo Per Tanggal" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-text-input id="tanggal" type="date"
                                wire:model.live="tanggal"
                                class="block w-full" />
                        </div>

                        {{-- Pemilih shift hanya muncul bila SKTXN_SHIFTCTLS terisi (siklik: kosong). --}}
                        @if (count($this->daftarShift) > 0)
                            <div class="w-full sm:w-36">
                                <x-input-label for="shift" value="s/d Shift" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                                <x-select-input id="shift" wire:model.live="shift" class="block w-full">
                                    <option value="">Seluruh hari</option>
                                    @foreach ($this->daftarShift as $nomorShift)
                                        <option value="{{ $nomorShift }}">Shift {{ $nomorShift }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        @endif

                        <div class="w-full sm:w-72">
                            <x-input-label for="searchKeyword" value="Cari" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Kode / nama cara bayar / akun..."
                                class="block w-full" />
                        </div>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>

                    <div class="px-4 py-2 text-right border rounded-lg bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                        <div class="text-[11px] font-medium tracking-wider text-emerald-700 uppercase dark:text-emerald-300">
                            Total Saldo
                        </div>
                        <div class="text-xl font-bold text-emerald-800 dark:text-emerald-200">
                            Rp {{ number_format($this->totalSaldo, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <details class="mt-3 text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                        Cara pakai &amp; sumber data
                    </summary>
                    <div class="px-3 pb-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                        <p>
                            Baris tabel = cara bayar aktif (<span class="font-mono">SKACC_CARABAYARS</span>); akun kas tiap baris
                            diambil dari kolom <span class="font-mono">acc_id</span> dan sifat D/K dari
                            <span class="font-mono">SKACC_ACCOUNTSES.acc_dk_status</span> (default <b>D</b> bila kosong).
                        </p>
                        <p>
                            Saldo dihitung <span class="font-mono">App\Support\Keuangan\SaldoKas</span> yang merakit jurnal
                            LANGSUNG dari tabel transaksi (<span class="font-mono">App\Support\Keuangan\Jurnal</span>),
                            <b>bukan</b> view <span class="font-mono">SKVIEW_ACCOUNTS</span> — view itu memberi hasil tidak stabil
                            di Oracle 10g untuk UNION ALL + subquery skalar.
                        </p>
                        <p>
                            Rumus 6i (<span class="font-mono">hitung_saldo_tanggal</span>):
                            <span class="font-mono">saldo = saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal</span>.
                            Akun D memakai baris <span class="font-mono">txn_acc</span> dengan arus
                            <span class="font-mono">txn_d − txn_k</span>; akun K memakai baris
                            <span class="font-mono">txn_acc_k</span> dengan arus <span class="font-mono">txn_k − txn_d</span>.
                        </p>
                        <p>
                            <b>Shift:</b> siklik tidak menyimpan shift di jurnal dan <span class="font-mono">SKTXN_SHIFTCTLS</span>
                            kosong, jadi pemilih “s/d Shift” disembunyikan dan seluruh hari selalu ikut dihitung.
                        </p>
                        <p>
                            Tombol <b>Riwayat</b> membuka mutasi harian/bulanan akun kas tersebut (bisa dicetak),
                            tombol <b>Edit Saldo</b> (admin) melakukan back-calc saldo awal tahun agar posisi per tanggal
                            sama dengan angka target.
                        </p>
                    </div>
                </details>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-20">CB ID</th>
                                <th>CARA BAYAR / AKUN</th>
                                <th class="w-24 ds-c">D/K</th>
                                <th class="w-60 text-right">
                                    SALDO PER {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}{{ $shift !== '' ? ' / SHIFT ' . $shift : '' }}
                                </th>
                                <th class="{{ $this->isAdmin() ? 'w-56' : 'w-32' }}">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="saldo-{{ $row->cb_id }}-{{ $tanggal }}-{{ $shift }}">
                                    <td class="ds-td-token align-middle">{{ $row->cb_id }}</td>
                                    <td class="align-middle">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $row->cb_desc }}
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $row->acc_id }}</span>
                                            @if (!empty($row->acc_desc))
                                                — {{ $row->acc_desc }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="ds-c align-middle">
                                        @if ((string) $row->acc_dk_status === 'D')
                                            <span class="px-3 py-1 text-sm font-bold rounded bg-blue-100 text-blue-700">D</span>
                                        @elseif ((string) $row->acc_dk_status === 'K')
                                            <span class="px-3 py-1 text-sm font-bold rounded bg-purple-100 text-purple-700">K</span>
                                        @else
                                            <span class="text-sm text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="ds-td-token text-right align-middle">
                                        <span class="text-lg font-bold {{ $row->saldo < 0 ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                            Rp {{ number_format($row->saldo, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="flex items-center gap-2 flex-nowrap">
                                            <x-outline-button type="button"
                                                wire:click="openHistory('{{ $row->cb_id }}')"
                                                class="whitespace-nowrap">
                                                Riwayat
                                            </x-outline-button>
                                            @if ($this->isAdmin())
                                                <x-action-edit
                                                    wire:click="openEdit('{{ $row->cb_id }}')"
                                                    class="whitespace-nowrap">Edit Saldo</x-action-edit>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                        Tidak ada cara bayar aktif.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas-history wire:key="saldo-kas-history" />
            @if ($this->isAdmin())
                <livewire:pages::transaksi.keuangan.saldo-kas.saldo-kas-actions wire:key="saldo-kas-actions" />
            @endif
        </div>
    </div>
</div>
