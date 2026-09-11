<?php

// Riwayat mutasi satu akun kas (cara bayar) — harian / bulanan (+ per shift bila SKTXN_SHIFTCTLS terisi).
// Sumber: App\Support\Keuangan\SaldoKas (jurnal langsung dari tabel transaksi), bukan SKVIEW_ACCOUNTS.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Support\Keuangan\SaldoKas;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $cbId        = '';
    public string $cbDesc      = '';
    public string $accId       = '';
    public string $accDesc     = '';
    public string $accDkStatus = 'D';

    /** Mode tampilan: 'harian' (satu tanggal) | 'shift' (satu tanggal dipotong shift) | 'bulanan' */
    public string $mode = 'harian';

    /** Format internal: 'YYYY-MM' (mode bulanan) */
    public string $periode = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Tanggal internal mode harian/shift: 'YYYY-MM-DD' */
    public string $tanggalHarian = '';
    /** Input user: 'DD/MM/YYYY' */
    public string $tanggalHarianInput = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /** Ambang detail transaksi di PDF; di atas ini cetakan memakai rekap harian (batas memori DomPDF). */
    private const BATAS_DETAIL_CETAK = 400;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** Mode berbasis satu tanggal (harian & per-shift), lawan dari bulanan. */
    private function isModeHarian(): bool
    {
        return in_array($this->mode, ['harian', 'shift'], true);
    }

    #[Computed]
    public function dariTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }

        return $this->periode === '' ? '' : $this->periode . '-01';
    }

    #[Computed]
    public function sampaiTanggal(): string
    {
        if ($this->isModeHarian()) {
            return $this->tanggalHarian;
        }
        if ($this->periode === '') return '';

        return Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString();
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, ['harian', 'shift', 'bulanan'], true)) return;
        // Mode shift hanya tersedia bila definisi shift ada (siklik: SKTXN_SHIFTCTLS kosong).
        if ($mode === 'shift' && $this->daftarShift === []) return;

        $this->mode = $mode;
        if ($this->isModeHarian() && $this->tanggalHarian === '' && $this->periode !== '') {
            $this->setTanggalHarian(Carbon::parse($this->periode . '-01')->endOfMonth()->toDateString());
        }
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse($this->periode . '-01')->addMonth()->format('Y-m'));
    }

    public function prevDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        if ($this->tanggalHarian === '') return;
        $this->setTanggalHarian(Carbon::parse($this->tanggalHarian)->addDay()->toDateString());
    }

    /** User mengubah text "MM/YYYY" → parse → set internal YYYY-MM. */
    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $cocok)) {
            $this->periode = '';
            return;
        }
        [, $bulan, $tahun] = $cocok;
        $this->periode = "{$tahun}-{$bulan}";
    }

    /** User ketik manual "DD/MM/YYYY" → parse ke internal YYYY-MM-DD. */
    public function updatedTanggalHarianInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $value)) {
            return;
        }
        try {
            $tanggalDipilih = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            return;
        }
        // tolak overflow (mis. 32/01/2026 → Carbon normalisasi jadi 01/02)
        if ($tanggalDipilih->format('d/m/Y') !== $value) {
            return;
        }
        $this->tanggalHarian = $tanggalDipilih->format('Y-m-d');
        $this->setPeriode(substr($this->tanggalHarian, 0, 7));
    }

    private function setPeriode(string $tahunBulan): void
    {
        $this->periode = $tahunBulan;
        $this->periodeInput = Carbon::parse($tahunBulan . '-01')->format('m/Y');
    }

    private function setTanggalHarian(string $tanggal): void
    {
        $this->tanggalHarian = $tanggal;
        $this->tanggalHarianInput = Carbon::parse($tanggal)->format('d/m/Y');
        $this->setPeriode(substr($tanggal, 0, 7));
    }

    #[On('keuangan.saldo-kas.openHistory')]
    public function openHistory(string $cbId, string $tanggal): void
    {
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

        // Default: mode harian pada tanggal terpilih di induk.
        $this->mode = 'harian';
        $this->setTanggalHarian($tanggal);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-history');
    }

    /** Saldo per tanggal (seluruh hari) — rumus 6i, sama dengan induk. */
    private function hitungSaldoTanggal(string $tanggal): float
    {
        return SaldoKas::hitung($this->accId, $this->accDkStatus, $tanggal);
    }

    /** Saldo awal periode = saldo per (dari_tanggal − 1 hari). */
    #[Computed]
    public function saldoAwalPeriode(): float
    {
        if ($this->accId === '' || $this->dariTanggal === '') return 0;

        return $this->hitungSaldoTanggal(Carbon::parse($this->dariTanggal)->subDay()->toDateString());
    }

    /**
     * Daftar transaksi akun kas dalam rentang + saldo berjalan.
     * Kolom D/K mengikuti sisi 6i (SaldoKas::sisi): akun D dibaca dari baris txn_acc
     * (debit kita = txn_d), akun K dari baris txn_acc_k (debit kita = txn_k);
     * kolom akun satunya jadi LAWAN AKUN. Nama akun lawan diambil query kecil terpisah.
     */
    #[Computed]
    public function rows()
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            return collect();
        }

        $sisi = SaldoKas::sisi($this->accDkStatus);

        $barisList = SaldoKas::query($this->accId, $this->accDkStatus, $this->dariTanggal, $this->sampaiTanggal)
            ->select(
                'txn_date', 'txn_name', 'shift',
                $sisi['lawan'] . ' as lawan_acc_id',
                DB::raw("NVL({$sisi['debit']},0) AS debit_kita"),
                DB::raw("NVL({$sisi['kredit']},0) AS kredit_kita"),
            )
            ->orderBy('txn_date')
            ->get();

        $namaLawan = DB::table('skacc_accountses')
            ->whereIn('acc_id', $barisList->pluck('lawan_acc_id')->filter()->unique()->values()->all() ?: ['-'])
            ->pluck('acc_desc', 'acc_id');

        $saldo = $this->saldoAwalPeriode;

        return $barisList->map(function ($baris) use (&$saldo, $namaLawan) {
            $baris->lawan_acc_desc = $namaLawan[$baris->lawan_acc_id] ?? null;
            $mutasi = (float) $baris->debit_kita - (float) $baris->kredit_kita;
            $saldo += $mutasi;
            $baris->mutasi = $mutasi;
            $baris->saldo_berjalan = $saldo;
            return $baris;
        })->values();
    }

    #[Computed]
    public function totalDebit(): float
    {
        return (float) $this->rows->sum('debit_kita');
    }

    #[Computed]
    public function totalKredit(): float
    {
        return (float) $this->rows->sum('kredit_kita');
    }

    #[Computed]
    public function saldoAkhir(): float
    {
        return $this->saldoAwalPeriode + $this->totalDebit - $this->totalKredit;
    }

    /**
     * Rekap per jenis transaksi — jenis = PREFIX 2 KATA txn_name ("BAYAR RJ", "CASH IN", "RJ OBAT", ...).
     * Label jurnal siklik tidak memakai kurung seperti sirus; 1 kata terlalu kasar, 3 kata sudah memuat
     * nama pasien, jadi 2 kata yang dipakai.
     */
    private function rekapJenisDari($barisList)
    {
        return collect($barisList)
            ->groupBy(function ($baris) {
                // Prefix 2 kata pertama: label siklik berbentuk '<MODUL> <SUBJENIS> ...' (mis. "RJ OBAT
                // TRANSAKSI", "BAYAR RJ <nama pasien>"); 1 kata terlalu kasar, 3 kata sudah kena nama pasien.
                $kataList = preg_split('/\s+/', trim((string) $baris->txn_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $jenis = implode(' ', array_slice($kataList, 0, 2));
                return $jenis === '' ? '-' : $jenis;
            })
            ->map(fn ($grup, $jenis) => (object) [
                'jenis'   => $jenis,
                'jumlah'  => $grup->count(),
                'nominal' => (float) $grup->sum(fn ($baris) => (float) $baris->debit_kita + (float) $baris->kredit_kita),
            ])
            ->sortByDesc('nominal')
            ->values();
    }

    #[Computed]
    public function rekapJenis()
    {
        return $this->rekapJenisDari($this->rows);
    }

    /** Daftar nomor shift yang terdefinisi — kosong di siklik, dipakai untuk menyembunyikan mode shift. */
    #[Computed]
    public function daftarShift(): array
    {
        return SaldoKas::daftarShift();
    }

    /** Definisi shift (jam mulai/selesai) untuk label kelompok. */
    #[Computed]
    public function definisiShift()
    {
        return DB::table('sktxn_shiftctls')
            ->select('shift', 'shift_start', 'shift_end')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->orderBy('shift_start')
            ->get();
    }

    /** Kelompokkan transaksi per nomor shift + subtotal & saldo akhir tiap shift. */
    private function susunKelompokShift(): array
    {
        $perShift = [];
        foreach ($this->rows as $row) {
            $perShift[(string) ($row->shift ?: '1')][] = $row;
        }
        ksort($perShift, SORT_NATURAL);

        $kelompokList = [];
        $saldoAwalShift = $this->saldoAwalPeriode;

        foreach ($perShift as $nomorShift => $barisList) {
            $definisi = $this->definisiShift->first(fn ($baris) => (string) $baris->shift === (string) $nomorShift);
            $subtotalDebit = 0.0;
            $subtotalKredit = 0.0;
            $saldo = $saldoAwalShift;
            $items = [];

            foreach ($barisList as $row) {
                $item = clone $row;                       // saldo berjalan per shift, jangan menimpa mode harian
                $subtotalDebit  += (float) $item->debit_kita;
                $subtotalKredit += (float) $item->kredit_kita;
                $saldo += (float) $item->mutasi;
                $item->saldo_berjalan = $saldo;
                $items[] = $item;
            }

            $kelompokList[] = (object) [
                'shift'          => (string) $nomorShift,
                'range'          => $definisi ? substr((string) $definisi->shift_start, 0, 5) . '–' . substr((string) $definisi->shift_end, 0, 5) : null,
                'items'          => $items,
                'subtotalDebit'  => $subtotalDebit,
                'subtotalKredit' => $subtotalKredit,
                'saldoAwal'      => $saldoAwalShift,
                'saldoAkhir'     => $saldo,
            ];
            $saldoAwalShift = $saldo;
        }

        return $kelompokList;
    }

    #[Computed]
    public function shiftGroups(): array
    {
        if ($this->mode !== 'shift' || $this->dariTanggal === '') {
            return [];
        }

        return $this->susunKelompokShift();
    }

    public function cetakRekap(): mixed
    {
        if ($this->accId === '' || $this->dariTanggal === '' || $this->sampaiTanggal === '') {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        // Pra-format tanggal di zona class supaya blade cetak bebas Carbon.
        $formatItem = fn ($row) => (object) [
            'tglLabel'      => Carbon::parse($row->txn_date)->format('d/m/Y'),
            'jamLabel'      => Carbon::parse($row->txn_date)->format('H:i'),
            'deskripsi'     => $row->txn_name,
            'lawanAccId'    => $row->lawan_acc_id,
            'lawanAccDesc'  => $row->lawan_acc_desc,
            'debit'         => (float) $row->debit_kita,
            'kredit'        => (float) $row->kredit_kita,
            'saldoBerjalan' => (float) $row->saldo_berjalan,
        ];

        $transaksiList = $this->rows->map($formatItem)->values();

        // DomPDF kehabisan memori bila detail transaksi terlalu banyak (mode bulanan akun kas ramai
        // bisa >1.500 baris). Di atas ambang ini cetakan diganti REKAP HARIAN (per tanggal).
        $detailPenuh = $transaksiList->count() <= self::BATAS_DETAIL_CETAK;

        $rekapHarian = $detailPenuh ? collect() : $this->rows
            ->groupBy(fn ($baris) => Carbon::parse($baris->txn_date)->format('Y-m-d'))
            ->map(fn ($grup, $tanggal) => (object) [
                'tglLabel'   => Carbon::parse($tanggal)->format('d/m/Y'),
                'jumlah'     => $grup->count(),
                'debit'      => (float) $grup->sum('debit_kita'),
                'kredit'     => (float) $grup->sum('kredit_kita'),
                'saldoAkhir' => (float) $grup->last()->saldo_berjalan,
            ])
            ->sortBy('tglLabel', SORT_NATURAL)
            ->values();

        $shiftGroups = [];
        if ($this->mode === 'shift') {
            foreach ($this->susunKelompokShift() as $kelompok) {
                $shiftGroups[] = (object) [
                    'shift'          => $kelompok->shift,
                    'range'          => $kelompok->range,
                    'items'          => collect($kelompok->items)->map($formatItem)->values(),
                    'subtotalDebit'  => $kelompok->subtotalDebit,
                    'subtotalKredit' => $kelompok->subtotalKredit,
                    'saldoAwal'      => $kelompok->saldoAwal,
                    'saldoAkhir'     => $kelompok->saldoAkhir,
                ];
            }
        }

        $tglMulai  = Carbon::parse($this->dariTanggal);
        $tglSampai = Carbon::parse($this->sampaiTanggal);
        $modeLabel = ['harian' => 'Harian', 'shift' => 'Harian (per Shift)', 'bulanan' => 'Bulanan'][$this->mode] ?? 'Harian';

        $identitas = DB::table('skmst_identitases')
            ->select('int_name', 'int_address', 'int_city', 'int_phone1')
            ->first();

        $dataCetak = [
            'identitas'     => $identitas,
            'cbId'          => $this->cbId,
            'cbDesc'        => $this->cbDesc,
            'accId'         => $this->accId,
            'accDesc'       => $this->accDesc,
            'mode'          => $this->mode,
            'modeLabel'     => $modeLabel,
            'periodeLabel'  => $this->isModeHarian()
                ? $tglMulai->format('d/m/Y')
                : $tglMulai->format('d/m/Y') . ' — ' . $tglSampai->format('d/m/Y'),
            'saldoAwalTgl'  => $tglMulai->copy()->subDay()->format('d/m/Y'),
            'sampaiLabel'   => $tglSampai->format('d/m/Y'),
            'dicetakPada'   => now()->format('d/m/Y H:i'),
            'saldoAwal'     => $this->saldoAwalPeriode,
            'totalDebit'    => $this->totalDebit,
            'totalKredit'   => $this->totalKredit,
            'saldoAkhir'    => $this->saldoAkhir,
            'transaksiList' => $transaksiList,
            'shiftGroups'   => $shiftGroups,
            'rekapJenis'    => $this->rekapJenis,
            'detailPenuh'   => $detailPenuh,
            'rekapHarian'   => $rekapHarian,
            'batasDetail'   => self::BATAS_DETAIL_CETAK,
        ];

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.transaksi.keuangan.saldo-kas.saldo-kas-history-print', $dataCetak)
            ->setPaper('a4', 'portrait');

        $akhiranPeriode = $this->isModeHarian() ? $tglMulai->format('Ymd') : $tglMulai->format('Ym');

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'rekap-kas-' . $this->accId . '-' . $akhiranPeriode . '.pdf',
        );
    }

    public function closeModal(): void
    {
        $this->reset(['cbId', 'cbDesc', 'accId', 'accDesc', 'accDkStatus', 'mode',
                      'periode', 'periodeInput', 'tanggalHarian', 'tanggalHarianInput']);
        $this->dispatch('close-modal', name: 'saldo-kas-history');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-history" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$cbId, $mode, $periode, $tanggalHarian]) }}">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Riwayat Transaksi — {{ $cbDesc }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $cbId }}</span> ·
                            Akun <span class="font-mono">{{ $accId }}</span>
                            @if (!empty($accDesc)) — {{ $accDesc }} @endif
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="px-4 py-3 bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div>
                            <x-input-label value="Tampilan" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-tabs variant="pill">
                                <x-tab :active="$mode === 'harian'" color="emerald" wire:click="setMode('harian')">Harian</x-tab>
                                {{-- Mode per shift hanya relevan bila SKTXN_SHIFTCTLS terisi (siklik: kosong). --}}
                                @if (count($this->daftarShift) > 0)
                                    <x-tab :active="$mode === 'shift'" color="emerald" wire:click="setMode('shift')">Per Shift</x-tab>
                                @endif
                                <x-tab :active="$mode === 'bulanan'" color="emerald" wire:click="setMode('bulanan')">Bulanan</x-tab>
                            </x-tabs>
                        </div>

                        @if ($mode === 'bulanan')
                            <div>
                                <x-input-label for="periodeInput" value="Periode (mm/yyyy)" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevMonth"
                                        class="px-3" title="Bulan sebelumnya">◀</x-secondary-button>
                                    <x-text-input id="periodeInput" type="text"
                                        wire:model.live.debounce.500ms="periodeInput"
                                        placeholder="01/2026" maxlength="7"
                                        class="w-28 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextMonth"
                                        class="px-3" title="Bulan berikutnya">▶</x-secondary-button>
                                </div>
                            </div>
                        @else
                            <div>
                                <x-input-label for="tanggalHarianInput" value="Tanggal (dd/mm/yyyy)" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                                <div class="flex items-stretch gap-1">
                                    <x-secondary-button type="button" wire:click="prevDay"
                                        class="px-3" title="Hari sebelumnya">◀</x-secondary-button>
                                    <x-text-input id="tanggalHarianInput" type="text"
                                        wire:model.live.debounce.500ms="tanggalHarianInput"
                                        placeholder="dd/mm/yyyy" maxlength="10"
                                        class="w-32 text-center font-mono" />
                                    <x-secondary-button type="button" wire:click="nextDay"
                                        class="px-3" title="Hari berikutnya">▶</x-secondary-button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-right">
                        <div class="px-3 py-2 border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                            <div class="text-[10px] tracking-wider text-gray-500 uppercase">Saldo Awal</div>
                            <div class="font-mono text-sm font-semibold">
                                {{ number_format($this->saldoAwalPeriode, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                            <div class="text-[10px] tracking-wider text-blue-700 uppercase dark:text-blue-300">Debit</div>
                            <div class="font-mono text-sm font-semibold text-blue-700 dark:text-blue-300">
                                {{ number_format($this->totalDebit, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="px-3 py-2 border rounded-lg bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800">
                            <div class="text-[10px] tracking-wider text-rose-700 uppercase dark:text-rose-300">Kredit</div>
                            <div class="font-mono text-sm font-semibold text-rose-700 dark:text-rose-300">
                                {{ number_format($this->totalKredit, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <details class="mt-3 text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                        Rekap per jenis transaksi ({{ $this->rekapJenis->count() }} jenis)
                    </summary>
                    <div class="flex flex-wrap gap-1.5 px-3 pb-3">
                        @forelse ($this->rekapJenis as $rekap)
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $rekap->jenis }}</span>
                                <span class="px-1.5 text-xs font-medium text-gray-500 rounded bg-white dark:bg-gray-900 dark:text-gray-400">{{ $rekap->jumlah }} trx</span>
                                <span class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($rekap->nominal, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <span class="text-xs text-gray-400">Tidak ada transaksi.</span>
                        @endforelse
                    </div>
                </details>

                <details class="mt-2 text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                        Cara pakai &amp; sumber data
                    </summary>
                    <div class="px-3 pb-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                        <p>
                            Baris di sini dirakit <span class="font-mono">App\Support\Keuangan\SaldoKas::query()</span> di atas
                            <span class="font-mono">App\Support\Keuangan\Jurnal</span> — jurnal dibaca LANGSUNG dari tabel
                            transaksi, <b>bukan</b> view <span class="font-mono">SKVIEW_ACCOUNTS</span>.
                        </p>
                        <p>
                            Sisi 6i: akun <b>D</b> memakai baris <span class="font-mono">txn_acc</span> (DEBIT = <span class="font-mono">txn_d</span>,
                            KREDIT = <span class="font-mono">txn_k</span>); akun <b>K</b> memakai baris <span class="font-mono">txn_acc_k</span>
                            (DEBIT = <span class="font-mono">txn_k</span>, KREDIT = <span class="font-mono">txn_d</span>). Kolom akun satunya
                            ditampilkan sebagai LAWAN AKUN.
                        </p>
                        <p>
                            Saldo awal periode = <span class="font-mono">saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d hari sebelum periode</span>;
                            kolom SALDO adalah saldo berjalan (saldo awal + akumulasi DEBIT − KREDIT).
                        </p>
                        <p>
                            <b>Shift:</b> siklik tidak mencatat shift di jurnal dan <span class="font-mono">SKTXN_SHIFTCTLS</span> kosong,
                            jadi tab “Per Shift” disembunyikan. Tombol <b>Cetak Rekap</b> menghasilkan PDF A4 dengan kop klinik
                            (<span class="font-mono">SKMST_IDENTITASES</span>).
                        </p>
                    </div>
                </details>
            </div>

            <div class="flex-1 px-4 py-3 overflow-hidden bg-gray-50/70 dark:bg-gray-950/20">
                <div class="h-full overflow-y-auto bg-white border border-gray-200 rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-28">TANGGAL</th>
                                <th>DESKRIPSI</th>
                                <th class="w-56">LAWAN AKUN</th>
                                <th class="w-28 text-right">DEBIT</th>
                                <th class="w-28 text-right">KREDIT</th>
                                <th class="w-36 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($this->dariTanggal !== '')
                                <tr class="bg-amber-50 dark:bg-amber-900/20">
                                    <td colspan="5" class="px-3 py-2 text-xs font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                                        Saldo awal per {{ \Carbon\Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') }}
                                    </td>
                                    <td class="ds-td-token text-right font-bold text-amber-800 dark:text-amber-300">
                                        {{ number_format($this->saldoAwalPeriode, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif

                            @if ($mode === 'shift')
                                @forelse ($this->shiftGroups as $group)
                                    <tr class="bg-emerald-50 dark:bg-emerald-900/30">
                                        <td colspan="6" class="px-3 py-1.5 text-xs font-bold tracking-wide uppercase text-emerald-800 dark:text-emerald-200">
                                            Shift {{ $group->shift }}@if ($group->range)<span class="ml-1 font-normal normal-case text-gray-500">({{ $group->range }})</span>@endif
                                        </td>
                                    </tr>
                                    @foreach ($group->items as $i => $row)
                                        <tr wire:key="shift-{{ $group->shift }}-{{ $i }}-{{ $row->txn_date }}">
                                        <td class="px-3 py-2 font-mono text-xs leading-tight align-middle whitespace-nowrap">
                                            <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="text-xs align-middle">{{ $row->txn_name }}</td>
                                        <td class="text-xs text-muted dark:text-gray-400 align-middle">
                                            <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_desc))
                                                <div class="text-[10px] truncate">{{ $row->lawan_acc_desc }}</div>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-middle text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit_kita > 0)
                                                {{ number_format((float) $row->debit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-middle text-rose-700 dark:text-rose-300">
                                            @if ((float) $row->kredit_kita > 0)
                                                {{ number_format((float) $row->kredit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-middle {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                        </td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-semibold bg-gray-50 dark:bg-gray-800/50">
                                        <td colspan="3" class="px-3 py-1.5 text-xs text-right uppercase text-gray-500">
                                            Subtotal Shift {{ $group->shift }}
                                        </td>
                                        <td class="ds-td-token text-right text-blue-700 dark:text-blue-300">
                                            {{ number_format($group->subtotalDebit, 0, ',', '.') }}
                                        </td>
                                        <td class="ds-td-token text-right text-rose-700 dark:text-rose-300">
                                            {{ number_format($group->subtotalKredit, 0, ',', '.') }}
                                        </td>
                                        <td class="ds-td-token text-right {{ $group->saldoAkhir < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format($group->saldoAkhir, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada transaksi pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                @forelse ($this->rows as $i => $row)
                                    <tr wire:key="hist-{{ $i }}-{{ $row->txn_date }}">
                                        <td class="px-3 py-2 font-mono text-xs leading-tight align-middle whitespace-nowrap">
                                            <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="text-xs align-middle">{{ $row->txn_name }}</td>
                                        <td class="text-xs text-muted dark:text-gray-400 align-middle">
                                            <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_desc))
                                                <div class="text-[10px] truncate">{{ $row->lawan_acc_desc }}</div>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-middle text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit_kita > 0)
                                                {{ number_format((float) $row->debit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-middle text-rose-700 dark:text-rose-300">
                                            @if ((float) $row->kredit_kita > 0)
                                                {{ number_format((float) $row->kredit_kita, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-middle {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif

                            @if ($this->rows->count() > 0)
                                <tr class="font-semibold bg-emerald-50 dark:bg-emerald-900/20">
                                    <td colspan="3" class="px-3 py-2 text-xs uppercase">
                                        Saldo akhir per {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                    </td>
                                    <td class="ds-td-token text-right text-blue-700 dark:text-blue-300">
                                        {{ number_format($this->totalDebit, 0, ',', '.') }}
                                    </td>
                                    <td class="ds-td-token text-right text-rose-700 dark:text-rose-300">
                                        {{ number_format($this->totalKredit, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->saldoAkhir < 0 ? 'text-red-600' : 'text-emerald-700 dark:text-emerald-300' }}">
                                        {{ number_format($this->saldoAkhir, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-3 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-cetak-button wire:click="cetakRekap" :disabled="$this->rows->count() === 0" label="Cetak Rekap" />
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
