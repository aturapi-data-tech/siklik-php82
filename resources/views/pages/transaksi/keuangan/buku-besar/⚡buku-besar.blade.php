<?php

// Buku Besar — jurnal dibaca LANGSUNG dari tabel transaksi lewat App\Support\Keuangan\Jurnal
// (pengganti view SKVIEW_ACCOUNTS yang hasilnya tidak stabil di Oracle 10g). Pola sirus-php82.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\Keuangan\Jurnal;
use App\Support\Keuangan\Hpp;

new class extends Component {
    use WithPagination;

    public string $accId        = '';
    public string $accDesc      = '';
    public string $accDkStatus  = '';

    /** Format internal: 'YYYY-MM' */
    public string $periode      = '';
    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Baris per halaman — satu akun besar (mis. piutang) bisa puluhan ribu baris per bulan. */
    public int $itemsPerPage = 50;

    public function mount(): void
    {
        $this->setPeriode(now()->format('Y-m'));
    }

    #[On('lov.selected.buku-besar-acc')]
    public function onAkunSelected(string $target, ?array $payload): void
    {
        $this->accId       = (string) ($payload['acc_id'] ?? '');
        $this->accDesc     = (string) ($payload['acc_desc'] ?? '');
        $this->accDkStatus = (string) ($payload['acc_dk_status'] ?? '');
        $this->resetPage();
    }

    public function updatedPeriodeInput(string $value): void
    {
        $value = trim($value);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $value, $cocok)) {
            $this->periode = '';
            return;
        }
        [, $bulan, $tahun] = $cocok;
        $this->periode = "{$tahun}-{$bulan}";
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    private function setPeriode(string $tahunBulan): void
    {
        $this->periode = $tahunBulan;
        $this->periodeInput = Carbon::parse("{$tahunBulan}-01")->format('m/Y');
        $this->resetPage();
    }

    public function prevMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse("{$this->periode}-01")->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        if ($this->periode === '') return;
        $this->setPeriode(Carbon::parse("{$this->periode}-01")->addMonth()->format('Y-m'));
    }

    /** Reset filter (dipanggil tombol Reset di x-toolbar-refresh-reset). */
    public function resetFilters(): void
    {
        $this->itemsPerPage = 50;
        $this->setPeriode(now()->format('Y-m'));
    }

    #[Computed]
    public function dariTanggal(): string
    {
        return $this->periode === '' ? '' : "{$this->periode}-01";
    }

    #[Computed]
    public function sampaiTanggal(): string
    {
        if ($this->periode === '') return '';
        return Carbon::parse("{$this->periode}-01")->endOfMonth()->toDateString();
    }

    /** Akun bertabiat kredit (kewajiban/ekuitas/pendapatan): saldo bertambah saat dikredit. */
    private function akunKredit(): bool
    {
        return $this->accDkStatus === 'K';
    }

    /**
     * Saldo akun per tanggal — generic untuk akun D maupun K.
     * Sumber: Jurnal (baris milik akun = txn_acc, arus dari kolom txn_d/txn_k baris itu).
     *   saldo = saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal
     *   akun D: mutasi = D − K   |   akun K: mutasi = K − D
     */
    private function hitungSaldoTanggal(string $tanggal): float
    {
        if ($this->accId === '' || $tanggal === '') return 0;

        $tahun = (int) substr($tanggal, 0, 4);
        $kosong = ['debit' => 0.0, 'kredit' => 0.0];

        $saldoAwalTahun = Jurnal::saldoAwalPerAkun([$this->accId], $tahun)[$this->accId] ?? $kosong;
        $arus = Jurnal::arusPerAkun([$this->accId], sprintf('%04d-01-01', $tahun), $tanggal)[$this->accId] ?? $kosong;

        return $this->akunKredit()
            ? $saldoAwalTahun['kredit'] + $arus['kredit'] - $arus['debit']
            : $saldoAwalTahun['debit'] + $arus['debit'] - $arus['kredit'];
    }

    /** Saldo awal periode = saldo per (tanggal awal periode − 1 hari). */
    #[Computed]
    public function saldoAwalPeriode(): float
    {
        if ($this->dariTanggal === '') return 0;

        return $this->hitungSaldoTanggal(Carbon::parse($this->dariTanggal)->subDay()->toDateString());
    }

    /**
     * Seluruh baris jurnal akun pada periode (urut tanggal) + saldo berjalan.
     * Nama akun lawan diambil lewat satu query kecil terpisah — JANGAN join di atas jurnal.
     */
    #[Computed]
    public function barisJurnal()
    {
        if ($this->accId === '' || $this->periode === '') return collect();

        $barisList = Jurnal::query($this->accId, Jurnal::SISI_ACC, $this->dariTanggal, $this->sampaiTanggal)
            ->select(
                'txn_date', 'txn_name',
                'txn_acc_k as lawan_acc_id',
                DB::raw('NVL(txn_d,0) AS debit'),
                DB::raw('NVL(txn_k,0) AS kredit'),
            )
            ->orderBy('txn_date')
            ->get();

        $namaLawan = Jurnal::namaAkun($barisList->pluck('lawan_acc_id'));

        $saldo = $this->saldoAwalPeriode;
        $akunKredit = $this->akunKredit();

        return $barisList->map(function ($baris) use (&$saldo, $akunKredit, $namaLawan) {
            $baris->lawan_acc_desc = $namaLawan[$baris->lawan_acc_id] ?? null;
            $mutasi = $akunKredit
                ? ((float) $baris->kredit - (float) $baris->debit)
                : ((float) $baris->debit - (float) $baris->kredit);
            $saldo += $mutasi;
            $baris->mutasi = $mutasi;
            $baris->saldo_berjalan = $saldo;
            return $baris;
        })->values();
    }

    /** Paginasi dilakukan di PHP: saldo berjalan butuh seluruh baris periode terurut. */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $seluruhBaris = $this->barisJurnal;
        $halaman = Paginator::resolveCurrentPage();
        $perHalaman = max(10, $this->itemsPerPage);

        return new LengthAwarePaginator(
            $seluruhBaris->slice(($halaman - 1) * $perHalaman, $perHalaman)->values(),
            $seluruhBaris->count(),
            $perHalaman,
            $halaman,
            ['path' => request()->url()],
        );
    }

    /** Saldo sebelum baris pertama halaman ini (halaman 1 = saldo awal periode). */
    #[Computed]
    public function saldoAwalHalaman(): float
    {
        $indeksAwal = ($this->rows->currentPage() - 1) * $this->rows->perPage();
        if ($indeksAwal <= 0) return $this->saldoAwalPeriode;

        $barisSebelumnya = $this->barisJurnal->get($indeksAwal - 1);

        return $barisSebelumnya ? (float) $barisSebelumnya->saldo_berjalan : $this->saldoAwalPeriode;
    }

    /**
     * Rekap per jenis transaksi — jenis = PREFIX 2 KATA txn_name (mis. "RJ OBAT", "BAYAR RJ", "CASH IN").
     * Label jurnal siklik tidak memakai kurung seperti sirus; 1 kata terlalu kasar (akun kas cuma 1 jenis),
     * 3 kata sudah memuat nama pasien, jadi 2 kata yang dipakai.
     */
    #[Computed]
    public function rekapJenis()
    {
        return $this->barisJurnal
            ->groupBy(function ($baris) {
                // Prefix 2 kata pertama: label siklik berbentuk '<MODUL> <SUBJENIS> ...' (mis. "RJ OBAT
                // TRANSAKSI", "BAYAR RJ <nama pasien>"); 1 kata terlalu kasar, 3 kata sudah kena nama pasien.
                $kataList = preg_split('/\s+/', trim((string) $baris->txn_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $jenis = implode(' ', array_slice($kataList, 0, 2));
                return $jenis === '' ? '-' : $jenis;
            })
            ->map(fn ($grup, $jenis) => (object) [
                'jenis'  => $jenis,
                'jumlah' => $grup->count(),
                'debit'  => (float) $grup->sum('debit'),
                'kredit' => (float) $grup->sum('kredit'),
            ])
            ->sortByDesc(fn ($rekap) => $rekap->debit + $rekap->kredit)
            ->values();
    }

    /**
     * Peringatan HPP: untuk akun HPP (conf 9) & persediaan (conf 2), Jurnal menyisipkan baris semu HPP
     * tahunan bertanggal 1 Desember. Bila HPP tahun itu tidak wajar (negatif karena stock opname /
     * harga pokok rusak), angkanya harus dibaca dengan hati-hati.
     */
    #[Computed]
    public function peringatanHpp(): ?array
    {
        if ($this->accId === '' || $this->periode === '') return null;

        $tahun = (int) substr($this->periode, 0, 4);
        $tanggalHpp = sprintf('%04d-12-01', $tahun);
        if ($tanggalHpp < $this->dariTanggal || $tanggalHpp > $this->sampaiTanggal) return null;

        $akunHppList = array_filter([
            Jurnal::akunKonfigurasiId(Hpp::CONF_HPP),
            Jurnal::akunKonfigurasiId(Hpp::CONF_PERSEDIAAN),
        ]);
        if (!in_array($this->accId, $akunHppList, true)) return null;

        $wajar = Hpp::wajar($tahun);
        $nilaiHpp = number_format(Hpp::nilai($tahun), 0, ',', '.');

        return [
            'wajar' => $wajar,
            'pesan' => $wajar
                ? "Periode ini memuat baris semu HPP tahun {$tahun} (dijurnalkan 1 Desember) sebesar {$nilaiHpp}."
                : "HPP tahun {$tahun} tidak wajar ({$nilaiHpp}) — stock opname / harga pokok produk belum benar, "
                    ."jadi baris semu HPP 1 Desember dan saldo akhir akun ini belum bisa dipakai.",
        ];
    }

    #[Computed]
    public function totalDebit(): float
    {
        return (float) $this->barisJurnal->sum('debit');
    }

    #[Computed]
    public function totalKredit(): float
    {
        return (float) $this->barisJurnal->sum('kredit');
    }

    #[Computed]
    public function saldoAkhir(): float
    {
        $netMutasi = $this->akunKredit()
            ? ($this->totalKredit - $this->totalDebit)
            : ($this->totalDebit - $this->totalKredit);

        return $this->saldoAwalPeriode + $netMutasi;
    }
};
?>

<div>
    <x-page-title
        title="Buku Besar"
        subtitle="Riwayat mutasi semua transaksi per akun pada periode tertentu, lengkap dengan saldo berjalan. Pilih akun &amp; bulan untuk melihat detailnya." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-80">
                            <livewire:lov.akun.lov-akun
                                target="buku-besar-acc"
                                label="Akun"
                                placeholder="Cari akun (kode/nama)..."
                                :initialAccId="$accId" />
                        </div>

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
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                @if ($periode !== '')
                                    {{ \Carbon\Carbon::parse($this->dariTanggal)->format('d/m/Y') }}
                                    — {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                @else
                                    <span class="text-rose-600">Format: mm/yyyy (mis. 04/2026)</span>
                                @endif
                            </p>
                        </div>

                        <div class="w-full sm:w-32">
                            <x-input-label for="itemsPerPage" value="Baris/hal" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage" class="block w-full">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="250">250</option>
                            </x-select-input>
                        </div>

                        <x-toolbar-refresh-reset :label="null" />
                    </div>

                    @if ($accId !== '' && $periode !== '')
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
                    @endif
                </div>

                @if ($this->peringatanHpp)
                    <div class="mt-3 px-3 py-2 text-xs border rounded-lg {{ $this->peringatanHpp['wajar'] ? 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200' : 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-200' }}">
                        {{ $this->peringatanHpp['pesan'] }}
                    </div>
                @endif

                @if ($accId !== '' && $periode !== '')
                    <details class="mt-3 text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                        <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                            Rekap per jenis transaksi ({{ $this->rekapJenis->count() }} jenis)
                        </summary>
                        <div class="flex flex-wrap gap-1.5 px-3 pb-3">
                            @forelse ($this->rekapJenis as $rekap)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 border rounded-lg bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $rekap->jenis }}</span>
                                    <span class="px-1.5 text-xs font-medium text-gray-500 rounded bg-white dark:bg-gray-900 dark:text-gray-400">{{ $rekap->jumlah }} trx</span>
                                    <span class="font-mono text-xs font-semibold text-blue-700 dark:text-blue-300">D {{ number_format($rekap->debit, 0, ',', '.') }}</span>
                                    <span class="font-mono text-xs font-semibold text-rose-700 dark:text-rose-300">K {{ number_format($rekap->kredit, 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <span class="text-xs text-gray-400">Tidak ada transaksi pada periode ini.</span>
                            @endforelse
                        </div>
                    </details>
                @endif

                <details class="mt-2 text-sm border rounded-lg bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <summary class="px-3 py-2 font-semibold text-gray-700 cursor-pointer dark:text-gray-200">
                        Cara pakai &amp; sumber data
                    </summary>
                    <div class="px-3 pb-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                        <p>
                            Sumber angka halaman ini adalah <span class="font-mono">App\Support\Keuangan\Jurnal</span> —
                            jurnal dirakit langsung dari tabel transaksi (bayar RJ, apotek, penerimaan/pengeluaran kas TU,
                            pembelian, dsb.), <b>bukan</b> view <span class="font-mono">SKVIEW_ACCOUNTS</span> yang hasilnya
                            tidak stabil di Oracle 10g.
                        </p>
                        <p>
                            Baris yang ditampilkan adalah baris <b>milik akun terpilih</b>
                            (<span class="font-mono">txn_acc = akun</span>), jadi kolom DEBIT/KREDIT adalah debit/kredit akun itu
                            sendiri dan kolom LAWAN AKUN adalah sisi jurnal seberangnya.
                        </p>
                        <p>
                            Rumus 6i: <span class="font-mono">saldo = saldo awal tahun (SKTXN_SALDOAWALAKUNS) + arus 1 Januari s/d tanggal</span>.
                            Akun bertabiat D memakai mutasi <span class="font-mono">D − K</span>, akun bertabiat K memakai
                            <span class="font-mono">K − D</span>. Saldo awal periode = saldo per hari sebelum tanggal awal periode.
                        </p>
                        <p>
                            Khusus akun HPP &amp; persediaan, jurnal menyisipkan <b>baris semu HPP tahunan</b>
                            bertanggal 1 Desember (padanan cabang HPP di view lama). Bila HPP tahun itu tidak wajar,
                            muncul peringatan di atas.
                        </p>
                        <p>
                            Nama akun lawan diambil lewat query kecil terpisah (<span class="font-mono">Jurnal::namaAkun()</span>),
                            tidak di-join di atas jurnal, supaya rakitan UNION ALL tetap ringan.
                        </p>
                    </div>
                </details>
            </div>

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
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
                            @if ($accId === '' || $periode === '')
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Pilih akun &amp; periode di atas untuk menampilkan buku besar.
                                    </td>
                                </tr>
                            @else
                                <tr class="bg-gray-50 dark:bg-gray-800/40">
                                    <td colspan="5" class="px-3 py-2 text-xs italic text-gray-500">
                                        @if ($this->rows->currentPage() > 1)
                                            Saldo sebelum baris pertama halaman {{ $this->rows->currentPage() }}
                                        @else
                                            Saldo per {{ \Carbon\Carbon::parse($this->dariTanggal)->subDay()->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td class="ds-td-strong ds-td-token text-right">
                                        {{ number_format($this->saldoAwalHalaman, 0, ',', '.') }}
                                    </td>
                                </tr>

                                @forelse ($this->rows as $i => $row)
                                    <tr wire:key="bb-{{ $accId }}-{{ $this->rows->currentPage() }}-{{ $i }}-{{ $row->txn_date }}">
                                        <td class="px-3 py-2 font-mono text-xs leading-tight align-top">
                                            <div>{{ \Carbon\Carbon::parse($row->txn_date)->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($row->txn_date)->format('H:i') }}</div>
                                        </td>
                                        <td class="text-xs align-top">{{ $row->txn_name }}</td>
                                        <td class="text-xs text-muted dark:text-gray-400 align-top">
                                            <div class="font-mono">{{ $row->lawan_acc_id }}</div>
                                            @if (!empty($row->lawan_acc_desc))
                                                <div class="text-[10px] truncate">{{ $row->lawan_acc_desc }}</div>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-top text-blue-700 dark:text-blue-300">
                                            @if ((float) $row->debit > 0)
                                                {{ number_format((float) $row->debit, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="ds-td-token text-right align-top text-rose-700 dark:text-rose-300">
                                            @if ((float) $row->kredit > 0)
                                                {{ number_format((float) $row->kredit, 0, ',', '.') }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-sm font-semibold text-right align-top {{ (float) $row->saldo_berjalan < 0 ? 'text-red-600' : '' }}">
                                            {{ number_format((float) $row->saldo_berjalan, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse

                                @if ($this->rows->total() > 0)
                                    <tr class="font-semibold bg-emerald-50 dark:bg-emerald-900/20">
                                        <td colspan="3" class="px-3 py-2 text-xs uppercase">
                                            Saldo per {{ \Carbon\Carbon::parse($this->sampaiTanggal)->format('d/m/Y') }}
                                            <span class="normal-case text-gray-500">(total {{ number_format($this->rows->total(), 0, ',', '.') }} baris periode ini)</span>
                                        </td>
                                        <td class="ds-td-token text-right text-blue-700 dark:text-blue-300">
                                            {{ number_format($this->totalDebit, 0, ',', '.') }}
                                        </td>
                                        <td class="ds-td-token text-right text-rose-700 dark:text-rose-300">
                                            {{ number_format($this->totalKredit, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-base text-right text-emerald-700 dark:text-emerald-300">
                                            {{ number_format($this->saldoAkhir, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            @endif
                        </tbody>
                    </table>
                </div>

                @if ($accId !== '' && $periode !== '' && $this->rows->total() > 0)
                    <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                        {{ $this->rows->links() }}
                    </div>
                @endif
            </div>

            @if ($accId !== '')
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Akun aktif:
                    <span class="font-mono font-semibold">{{ $accId }}</span>
                    @if (!empty($accDesc)) — {{ $accDesc }} @endif
                    @if ($accDkStatus === 'D')
                        <span class="px-1.5 ml-2 text-[10px] rounded bg-blue-100 text-blue-700">D-natural</span>
                    @elseif ($accDkStatus === 'K')
                        <span class="px-1.5 ml-2 text-[10px] rounded bg-purple-100 text-purple-700">K-natural</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
