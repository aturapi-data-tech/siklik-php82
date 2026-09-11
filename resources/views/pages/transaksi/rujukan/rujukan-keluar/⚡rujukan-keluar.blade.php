<?php
// resources/views/pages/transaksi/rujukan/rujukan-keluar/rujukan-keluar.blade.php
//
// PEMANTAUAN RUJUKAN KELUAR (FKTP) — daftar rujukan berbasis kompetensi yang
// dikirim dari EMR Rawat Jalan lewat PCare-SISRUTE.
//
// Sumber data: node `rujukanKompetensi` di CLOB sktxn_rjhdrs.datadaftarpolirj_json.
// BPJS tidak menyediakan endpoint "daftar rujukan keluar milik saya" untuk FKTP,
// jadi layar ini murni membaca jejak yang kita simpan sendiri saat pengiriman.
//
// Penyaringan dua lapis, disengaja:
//   1. rentang tanggal  → membatasi baris yang CLOB-nya perlu disentuh sama sekali
//      (sktxn_rjhdrs besar; menyapu seluruhnya tidak sehat),
//   2. INSTR '"rujukanKompetensi"' → memetik kandidat. Tanda kutip PENUTUP wajib
//      ditulis supaya pencocokan tidak melebar ke kunci lain yang berawalan sama.
// Oracle 10g tidak punya JSON_VALUE (lihat skill oracle-quirks), jadi isi node baru
// dibaca setelah CLOB di-decode di PHP.
//
// Data TIDAK dimuat saat halaman dibuka — sapuan CLOB berat. Petugas menekan
// "Muat Data" (atau Refresh di toolbar) setelah menentukan rentang tanggal.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\OracleLob;

new class extends Component {
    use WithPagination;

    /** Rentang tanggal kunjungan RJ (format Y-m-d, input type=date). */
    public string $dariTanggal = '';
    public string $sampaiTanggal = '';

    /** '' | terkirim | dibatalkan | draft */
    public string $filterStatus = '';

    /** poli_id asal kunjungan; '' = semua. */
    public string $filterPoli = '';

    public string $searchKeyword = '';
    public int $itemsPerPage = 25;

    /** Baris hasil bacaan CLOB (bukan model) — dipakai ulang lintas render. */
    public array $daftarRujukan = [];

    public bool $sudahMuat = false;
    public string $pesan = '';
    public string $waktuMuat = '';

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasAnyRole(['Admin', 'Dokter', 'Mr']), 403);

        $this->dariTanggal = Carbon::now(config('app.timezone'))->startOfMonth()->format('Y-m-d');
        $this->sampaiTanggal = Carbon::now(config('app.timezone'))->format('Y-m-d');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPoli(): void
    {
        $this->resetPage();
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Reset filter sekaligus mengosongkan hasil muat — rentang tanggal berubah
     * berarti data lama tidak lagi mewakili apa yang diminta petugas.
     */
    public function resetFilters(): void
    {
        $this->reset(['filterStatus', 'filterPoli', 'searchKeyword', 'daftarRujukan', 'pesan', 'waktuMuat']);
        $this->sudahMuat = false;
        $this->itemsPerPage = 25;
        $this->dariTanggal = Carbon::now(config('app.timezone'))->startOfMonth()->format('Y-m-d');
        $this->sampaiTanggal = Carbon::now(config('app.timezone'))->format('Y-m-d');
        $this->resetPage();
    }

    /**
     * Sapuan CLOB. Dipanggil eksplisit lewat tombol, bukan dari mount/render.
     */
    public function muatData(): void
    {
        $this->pesan = '';
        $this->resetPage();

        try {
            $rows = $this->queryHeader()->get();
        } catch (\Throwable $e) {
            $this->daftarRujukan = [];
            $this->sudahMuat = true;
            $this->pesan = 'Gagal membaca data rujukan: ' . Str::limit($e->getMessage(), 180);
            return;
        }

        $baris = [];
        foreach ($rows as $row) {
            // CLOB Oracle di-fetch sebagai locator lazy; baca lewat OracleLob supaya
            // tahan ORA-01555/ORA-22924 saat locator basi setelah EMR disimpan.
            $json = OracleLob::read(
                $row->datadaftarpolirj_json ?? null,
                'sktxn_rjhdrs',
                'rj_no',
                $row->rj_no,
                'datadaftarpolirj_json',
            );
            if ($json === '') {
                continue;
            }

            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $node = $data['rujukanKompetensi'] ?? null;
            if (!is_array($node) || $node === []) {
                continue;
            }

            $baris[] = $this->susunBaris($row, $node);
        }

        // Terbaru di atas: waktu kirim bila ada, selain itu tanggal kunjungan.
        usort($baris, fn($a, $b) => strcmp($b['urutKunci'], $a['urutKunci']));

        $this->daftarRujukan = $baris;
        $this->sudahMuat = true;
        $this->waktuMuat = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /** Muat ulang setelah anak (modal rincian) membatalkan rujukan. */
    #[On('rujukan-keluar.diperbarui')]
    public function muatUlang(): void
    {
        if ($this->sudahMuat) {
            $this->muatData();
        }
    }

    public function lihatRincian(string $rjNo): void
    {
        $this->dispatch('rujukan-keluar.detail.open', rjNo: $rjNo);
    }

    public function cetakSurat(string $rjNo): void
    {
        $this->dispatch('cetak-surat-rujukan-rj.open', rjNo: $rjNo);
    }

    /* ══════════════════════════════════
     | Kueri & pengolahan
    ══════════════════════════════════ */

    private function queryHeader()
    {
        return DB::table('sktxn_rjhdrs as h')
            ->join('skmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('skmst_polis as pol', 'pol.poli_id', '=', 'h.poli_id')
            ->leftJoin('skmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select([
                'h.rj_no',
                'h.reg_no',
                'p.reg_name',
                'h.poli_id',
                'pol.poli_desc',
                'd.dr_name',
                DB::raw("to_char(h.rj_date, 'dd/mm/yyyy') as tgl_kunjungan"),
                'h.datadaftarpolirj_json',
            ])
            ->whereRaw("h.rj_date >= to_date(?, 'yyyy-mm-dd')", [$this->tanggalAman($this->dariTanggal, 'awal')])
            ->whereRaw("h.rj_date < to_date(?, 'yyyy-mm-dd') + 1", [$this->tanggalAman($this->sampaiTanggal, 'akhir')])
            // Kutip penutup WAJIB — tanpa itu pencocokan melebar ke kunci berawalan sama.
            ->whereRaw("INSTR(h.datadaftarpolirj_json, '\"rujukanKompetensi\"') > 0")
            ->when($this->filterPoli !== '', fn($query) => $query->where('h.poli_id', $this->filterPoli))
            ->orderByDesc('h.rj_date');
    }

    /** Tanggal input bisa setengah jadi saat user mengetik; jangan biarkan Carbon melempar. */
    private function tanggalAman(string $nilai, string $jenis): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', trim($nilai))->format('Y-m-d');
        } catch (\Throwable) {
            $sekarang = Carbon::now(config('app.timezone'));

            return $jenis === 'awal'
                ? $sekarang->startOfMonth()->format('Y-m-d')
                : $sekarang->format('Y-m-d');
        }
    }

    private function susunBaris(object $row, array $node): array
    {
        $hasil = $node['hasil'] ?? [];
        $dibatalkan = $node['dibatalkan'] ?? null;

        $noSatuSehat = trim((string) ($hasil['noRujukanSatuSehat'] ?? ''));
        $noPcare = trim((string) ($hasil['noRujukanPcare'] ?? ''));

        if (is_array($dibatalkan) && $dibatalkan !== []) {
            $status = 'dibatalkan';
        } elseif ($noSatuSehat !== '') {
            $status = 'terkirim';
        } else {
            $status = 'draft';
        }

        $tglKirim = trim((string) ($hasil['tglRujukan'] ?? ($hasil['dikirimPada'] ?? '')));

        return [
            'rjNo' => (string) $row->rj_no,
            'regNo' => (string) $row->reg_no,
            'pasienNama' => (string) $row->reg_name,
            'poliId' => (string) ($row->poli_id ?? ''),
            'poliAsal' => (string) ($row->poli_desc ?? ''),
            'dokterAsal' => (string) ($row->dr_name ?? ''),
            'tglKunjungan' => (string) $row->tgl_kunjungan,
            'tglKirim' => $tglKirim,
            'diagnosa' => $this->gabung($node['kodeDiagnosa'] ?? '', $node['diagnosaDesc'] ?? ''),
            'kriteria' => $this->teksKriteria($node),
            'tujuanNama' => (string) ($hasil['tujuanNama'] ?? ''),
            'tujuanPpk' => (string) ($hasil['tujuanPpk'] ?? ''),
            'tujuanStrata' => (string) ($hasil['tujuanStrata'] ?? ''),
            'tujuanSatuSehat' => (string) ($hasil['tujuanSatuSehat'] ?? ''),
            'noRujukanPcare' => $noPcare,
            'noRujukanSatuSehat' => $noSatuSehat,
            'dikirimOleh' => (string) ($hasil['dikirimOleh'] ?? ''),
            'status' => $status,
            'urutKunci' => $this->kunciUrut($tglKirim, (string) $row->tgl_kunjungan, (string) $row->rj_no),
        ];
    }

    /** Kunci urut string "YmdHis|rjNo" supaya bisa dibandingkan dengan strcmp. */
    private function kunciUrut(string $tglKirim, string $tglKunjungan, string $rjNo): string
    {
        foreach (['d/m/Y H:i:s', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($tglKirim))->format('YmdHis') . '|' . str_pad($rjNo, 12, '0', STR_PAD_LEFT);
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        try {
            return Carbon::createFromFormat('d/m/Y', trim($tglKunjungan))->format('Ymd') . '000000|' . str_pad($rjNo, 12, '0', STR_PAD_LEFT);
        } catch (\Throwable) {
            return '00000000000000|' . str_pad($rjNo, 12, '0', STR_PAD_LEFT);
        }
    }

    /** Kriteria yang benar-benar dikirim — TEPAT SATU item dari kriteriaList. */
    private function teksKriteria(array $node): string
    {
        $terpilih = collect($node['kriteriaList'] ?? [])
            ->firstWhere('linkId', $node['kriteriaPilih'] ?? null);

        $teks = trim((string) ($terpilih['text'] ?? ''));
        $icd9 = trim((string) ($node['kriteriaIcd9'] ?? ''));

        if ($teks === '') {
            return '';
        }

        return $icd9 === '' ? $teks : $teks . ' (' . $icd9 . ')';
    }

    private function gabung(mixed $kode, mixed $nama): string
    {
        $kode = trim((string) $kode);
        $nama = trim((string) $nama);

        if ($kode !== '' && $nama !== '') {
            return $kode . ' — ' . $nama;
        }

        return $kode !== '' ? $kode : $nama;
    }

    /* ══════════════════════════════════
     | Computed
    ══════════════════════════════════ */

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $kataKunci = mb_strtoupper(trim($this->searchKeyword));

        $terpilih = array_values(array_filter($this->daftarRujukan, function (array $baris) use ($kataKunci) {
            if ($this->filterStatus !== '' && $baris['status'] !== $this->filterStatus) {
                return false;
            }

            if ($kataKunci === '') {
                return true;
            }

            $gabungan = mb_strtoupper(implode(' ', [
                $baris['rjNo'], $baris['regNo'], $baris['pasienNama'], $baris['diagnosa'],
                $baris['tujuanNama'], $baris['tujuanPpk'], $baris['noRujukanPcare'], $baris['noRujukanSatuSehat'],
            ]));

            return str_contains($gabungan, $kataKunci);
        }));

        $halaman = Paginator::resolveCurrentPage();
        $perHalaman = max(5, $this->itemsPerPage);

        return new LengthAwarePaginator(
            array_slice($terpilih, ($halaman - 1) * $perHalaman, $perHalaman),
            count($terpilih),
            $perHalaman,
            $halaman,
            ['path' => request()->url()],
        );
    }

    #[Computed]
    public function rekap(): array
    {
        $rekap = ['total' => count($this->daftarRujukan), 'terkirim' => 0, 'dibatalkan' => 0, 'draft' => 0];

        foreach ($this->daftarRujukan as $baris) {
            $rekap[$baris['status']] = ($rekap[$baris['status']] ?? 0) + 1;
        }

        return $rekap;
    }

    #[Computed]
    public function poliList()
    {
        return DB::table('skmst_polis')->select('poli_id', 'poli_desc')->orderBy('poli_desc')->get();
    }

    public function labelStatus(string $status): string
    {
        return ['terkirim' => 'Terkirim', 'dibatalkan' => 'Dibatalkan', 'draft' => 'Draft'][$status] ?? $status;
    }

    public function variantStatus(string $status): string
    {
        return ['terkirim' => 'success', 'dibatalkan' => 'danger', 'draft' => 'warning'][$status] ?? 'gray';
    }
};
?>

<div>
    <x-page-title title="Rujukan Keluar"
        subtitle="Pemantauan rujukan berbasis kompetensi yang dikirim dari EMR Rawat Jalan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- ══════════ TOOLBAR (sticky) ══════════ --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- PENCARIAN --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.400ms="searchKeyword" type="search"
                                class="block w-full pl-10"
                                placeholder="Cari No. RJ / No. RM / pasien / faskes tujuan / nomor rujukan" />
                        </div>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dari Tgl" />
                        <x-text-input type="date" wire:model="dariTanggal" class="block w-full mt-1 sm:w-40" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sampai Tgl" />
                        <x-text-input type="date" wire:model="sampaiTanggal" class="block w-full mt-1 sm:w-40" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-40">
                            <option value="">Semua Status</option>
                            <option value="terkirim">Terkirim</option>
                            <option value="dibatalkan">Dibatalkan</option>
                            <option value="draft">Draft</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Poli Asal" />
                        <x-select-input wire:model.live="filterPoli" class="w-full mt-1 sm:w-44">
                            <option value="">Semua Poli</option>
                            @foreach ($this->poliList as $poli)
                                <option value="{{ $poli->poli_id }}">{{ $poli->poli_desc }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <x-primary-button type="button" wire:click="muatData" wire:loading.attr="disabled"
                            wire:target="muatData">
                            <span wire:loading.remove wire:target="muatData">Muat Data</span>
                            <span wire:loading wire:target="muatData" class="inline-flex items-center gap-2">
                                <x-loading size="md" /> Memuat...
                            </span>
                        </x-primary-button>

                        <x-toolbar-refresh-reset :label="null" />

                        <div class="w-20">
                            <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Per halaman">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>
                </div>

                {{-- REKAP --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-gray-300">
                        Total <span class="font-bold">{{ $this->rekap['total'] }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                        Terkirim <span class="font-bold">{{ $this->rekap['terkirim'] }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-amber-800 rounded-full bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                        Draft <span class="font-bold">{{ $this->rekap['draft'] }}</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full dark:bg-red-900/30 dark:text-red-300">
                        Dibatalkan <span class="font-bold">{{ $this->rekap['dibatalkan'] }}</span>
                    </span>
                    @if ($waktuMuat !== '')
                        <span class="text-xs text-gray-500 dark:text-gray-400">Dimuat: {{ $waktuMuat }}</span>
                    @endif
                </div>

                @if ($pesan !== '')
                    <div
                        class="px-3 py-2 mt-3 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-900/50 dark:text-red-300">
                        {{ $pesan }}
                    </div>
                @endif
            </div>

            {{-- ══════════ TABEL ══════════ --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table ds-table-rapat">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th>Tgl Kirim</th>
                                <th>No RJ</th>
                                <th>Pasien</th>
                                <th>Diagnosa</th>
                                <th>Kriteria</th>
                                <th>Tujuan</th>
                                <th>No Rujukan PCare</th>
                                <th>No Rujukan SATUSEHAT</th>
                                <th class="ds-c">Status</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $baris)
                                <tr wire:key="rujukan-keluar-{{ $baris['rjNo'] }}">
                                    <td class="whitespace-nowrap">
                                        <div class="font-medium">{{ $baris['tglKirim'] ?: '-' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Kunjungan {{ $baris['tglKunjungan'] }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <div class="font-mono font-semibold">{{ $baris['rjNo'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $baris['poliAsal'] ?: '-' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-medium">{{ strtoupper($baris['pasienNama']) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            RM {{ $baris['regNo'] }}
                                        </div>
                                    </td>
                                    <td class="text-xs">{{ $baris['diagnosa'] ?: '-' }}</td>
                                    <td class="text-xs">{{ $baris['kriteria'] ?: '-' }}</td>
                                    <td class="text-xs">
                                        <div class="font-medium">{{ $baris['tujuanNama'] ?: '-' }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $baris['tujuanPpk'] ?: '-' }}
                                            @if ($baris['tujuanStrata'])
                                                &bull; {{ $baris['tujuanStrata'] }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="font-mono text-xs whitespace-nowrap">
                                        {{ $baris['noRujukanPcare'] ?: '-' }}</td>
                                    <td class="font-mono text-xs whitespace-nowrap">
                                        {{ $baris['noRujukanSatuSehat'] ?: '-' }}</td>
                                    <td class="ds-c">
                                        <x-badge :variant="$this->variantStatus($baris['status'])">
                                            {{ $this->labelStatus($baris['status']) }}
                                        </x-badge>
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex items-center justify-center gap-1">
                                            <x-lihat-button wire:click="lihatRincian('{{ $baris['rjNo'] }}')"
                                                title="Rincian rujukan" />
                                            @if ($baris['status'] === 'terkirim')
                                                <x-cetak-button wire:click="cetakSurat('{{ $baris['rjNo'] }}')"
                                                    title="Cetak surat pengantar rujukan" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            @if (!$sudahMuat)
                                                <span>Tentukan rentang tanggal lalu tekan <b>Muat Data</b>.</span>
                                                <span class="text-xs">Kueri menyapu kolom CLOB, jadi tidak dijalankan
                                                    otomatis saat halaman dibuka.</span>
                                            @else
                                                <span>Tidak ada rujukan keluar pada rentang & filter ini.</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal rincian rujukan --}}
    <livewire:pages::transaksi.rujukan.rujukan-keluar.rujukan-keluar-actions wire:key="rujukan-keluar-actions" />

    {{-- Cetak Surat Rujukan PDF (headless: listen event cetak-surat-rujukan-rj.open) --}}
    <livewire:pages::components.modul-dokumen.rj.surat-rujukan.cetak-surat-rujukan-rj
        wire:key="cetak-surat-rujukan-rj-keluar" />
</div>
