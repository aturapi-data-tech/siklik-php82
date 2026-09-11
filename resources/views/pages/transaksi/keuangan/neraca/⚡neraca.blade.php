<?php

use App\Support\Keuangan\Hpp;
use App\Support\Keuangan\Jurnal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Laporan Neraca per tanggal cutoff — disusun dari GRUP AKUN master (skacc_gr_accountses:
 * 1 AKTIVA D, 2 HUTANG K, 3 EKUITAS K), bukan template N1 yang hanya memuat 24 akun pilihan.
 * Siklik tidak punya tabel sub-grup akun, jadi akun ditampilkan datar urut acc_id.
 *
 * Saldo akun = Jurnal::saldoAwalPerAkun (kedua sisi D/K) + Jurnal::arusPerAkun 1 Januari s/d
 * tanggal, bertanda natural per dk_status grup. Nilai dari App\Support\Keuangan\Jurnal —
 * tabel transaksi langsung, tanpa skview_accounts dan tanpa join di atas jurnal.
 *
 * Laba (Rugi) Tahun Berjalan = Σ (K − D) arus YTD SEMUA akun grup 4 PENDAPATAN & 5 BEBAN.
 * HPP baru terjurnal 1 Desember (cabang semu Hpp); untuk cutoff sebelum itu tersedia toggle
 * "sertakan HPP tahunan estimasi" yang menambahkan Hpp::nilai sebagai beban.
 */
new class extends Component
{
    public string $tanggal = '';
    /** Tambahkan HPP tahunan estimasi sebagai beban pada laba tahun berjalan. */
    public bool $sertakanHppEstimasi = false;

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    #[Computed]
    public function tanggalValid(): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal);
    }

    #[Computed]
    public function tahun(): int
    {
        return $this->tanggalValid ? (int) substr($this->tanggal, 0, 4) : (int) now()->format('Y');
    }

    #[Computed]
    public function awalTahun(): string
    {
        return sprintf('%04d-01-01', $this->tahun);
    }

    /** Tanggal jurnal HPP tahunan (1 Desember) sudah terlewati oleh cutoff. */
    #[Computed]
    public function hppSudahTerjurnal(): bool
    {
        return $this->tanggalValid && $this->tanggal >= sprintf('%04d-12-01', $this->tahun);
    }

    /** Akun persediaan (skacc_confacctxns conf_id 2) — lawan jurnal HPP. */
    #[Computed]
    public function akunPersediaan(): ?string
    {
        return Jurnal::akunKonfigurasiId(Hpp::CONF_PERSEDIAAN);
    }

    #[Computed]
    public function labelTanggal(): string
    {
        return $this->tanggalValid ? Carbon::parse($this->tanggal)->format('d/m/Y') : '';
    }

    #[Computed]
    public function hppTahunan(): float
    {
        return Hpp::nilai($this->tahun);
    }

    /** HPP negatif mustahil — tanda stok akhir / saldo awal persediaan masih kacau. */
    #[Computed]
    public function hppWajar(): bool
    {
        return $this->hppTahunan > 0;
    }

    /** Estimasi HPP hanya boleh ditambahkan bila nilainya wajar dan belum terjurnal. */
    #[Computed]
    public function hppEstimasiTersedia(): bool
    {
        return $this->hppWajar && $this->akunPersediaan !== null;
    }

    /** Estimasi HPP sedang dipakai — dijurnalkan semu: persediaan turun, laba berjalan turun. */
    #[Computed]
    public function hppEstimasiDipakai(): bool
    {
        return $this->sertakanHppEstimasi && $this->hppEstimasiTersedia;
    }

    /**
     * Neraca lengkap:
     * ['sisiList' => [kunci => ['judul','dkStatus','akunList','total']],
     *  'labaBerjalan', 'totalAktiva', 'totalPasiva', 'selisih'].
     * akun = ['accId','nama','aktif','saldoAwal','arusDebit','arusKredit','saldo'].
     */
    #[Computed]
    public function laporan(): array
    {
        $sisiList = [
            'aktiva' => ['judul' => 'AKTIVA', 'graId' => '1', 'dkStatus' => 'D', 'akunList' => [], 'total' => 0.0],
            'hutang' => ['judul' => 'HUTANG', 'graId' => '2', 'dkStatus' => 'K', 'akunList' => [], 'total' => 0.0],
            'ekuitas' => ['judul' => 'EKUITAS', 'graId' => '3', 'dkStatus' => 'K', 'akunList' => [], 'total' => 0.0],
        ];
        $kosong = ['sisiList' => $sisiList, 'labaBerjalan' => 0.0, 'totalAktiva' => 0.0,
            'totalPasiva' => 0.0, 'selisih' => 0.0];
        if (! $this->tanggalValid) {
            return $kosong;
        }

        $grupAkunList = DB::table('skacc_gr_accountses')->get()->keyBy('gra_id');
        $akunRows = DB::table('skacc_accountses')
            ->whereIn('gra_id', ['1', '2', '3'])
            ->orderBy('acc_id')
            ->get();
        $accIdList = $akunRows->pluck('acc_id')->map(fn ($accId) => (string) $accId)->all();

        // denganHpp=false: cabang semu HPP 1 Desember tidak dipakai (lihat catatan halaman);
        // HPP dijurnalkan semu di tingkat halaman lewat toggle estimasi, dua sisi sekaligus.
        $saldoAwalList = Jurnal::saldoAwalPerAkun($accIdList, $this->tahun);
        $arusList = Jurnal::arusPerAkun($accIdList, $this->awalTahun, $this->tanggal, false);
        $nol = ['debit' => 0.0, 'kredit' => 0.0];
        $petaSisi = ['1' => 'aktiva', '2' => 'hutang', '3' => 'ekuitas'];

        foreach ($akunRows as $akun) {
            $accId = (string) $akun->acc_id;
            $graId = (string) $akun->gra_id;
            $dkStatus = (string) ($grupAkunList[$graId]->dk_status ?? 'D');
            $saldoAwal = $saldoAwalList[$accId] ?? $nol;
            $arus = $arusList[$accId] ?? $nol;

            $saldoAwalNatural = $dkStatus === 'K'
                ? $saldoAwal['kredit'] - $saldoAwal['debit']
                : $saldoAwal['debit'] - $saldoAwal['kredit'];
            $mutasiNatural = $dkStatus === 'K'
                ? $arus['kredit'] - $arus['debit']
                : $arus['debit'] - $arus['kredit'];
            $saldo = $saldoAwalNatural + $mutasiNatural;
            $aktif = (string) ($akun->active_status ?? '1') === '1';
            // Jurnal HPP semu: persediaan dikredit sebesar HPP (sisi lawan dari beban HPP di ekuitas).
            $hppEstimasi = $this->hppEstimasiDipakai && $accId === $this->akunPersediaan ? $this->hppTahunan : 0.0;
            $saldo -= $hppEstimasi;

            // Akun nonaktif tanpa saldo dan tanpa mutasi tidak perlu tampil.
            if (! $aktif && abs($saldo) < 0.5 && abs($arus['debit']) < 0.5 && abs($arus['kredit']) < 0.5) {
                continue;
            }

            $kunci = $petaSisi[$graId];
            $sisiList[$kunci]['akunList'][] = [
                'accId' => $accId,
                'nama' => (string) ($akun->acc_desc ?? ''),
                'aktif' => $aktif,
                'saldoAwal' => $saldoAwalNatural,
                'arusDebit' => (float) $arus['debit'],
                'arusKredit' => (float) $arus['kredit'] + $hppEstimasi,
                'saldo' => $saldo,
                'hppEstimasi' => $hppEstimasi,
            ];
            $sisiList[$kunci]['total'] += $saldo;
        }

        $labaBerjalan = $this->hitungLabaBerjalan();
        $totalAktiva = (float) $sisiList['aktiva']['total'];
        $totalPasiva = (float) $sisiList['hutang']['total'] + (float) $sisiList['ekuitas']['total'] + $labaBerjalan;

        return ['sisiList' => $sisiList, 'labaBerjalan' => $labaBerjalan, 'totalAktiva' => $totalAktiva,
            'totalPasiva' => $totalPasiva, 'selisih' => $totalAktiva - $totalPasiva];
    }

    /** Σ (K − D) arus YTD semua akun grup 4 & 5, dikurangi HPP tahunan estimasi bila toggle aktif. */
    private function hitungLabaBerjalan(): float
    {
        $accIdLabaRugi = DB::table('skacc_accountses')
            ->whereIn('gra_id', ['4', '5'])
            ->pluck('acc_id')
            ->map(fn ($accId) => (string) $accId)
            ->all();

        $laba = 0.0;
        foreach (Jurnal::arusPerAkun($accIdLabaRugi, $this->awalTahun, $this->tanggal, false) as $arus) {
            $laba += $arus['kredit'] - $arus['debit'];
        }
        if ($this->hppEstimasiDipakai) {
            $laba -= $this->hppTahunan;
        }

        return $laba;
    }

    #[Computed]
    public function selisih(): float
    {
        return (float) $this->laporan['selisih'];
    }

    #[Computed]
    public function seimbang(): bool
    {
        return abs($this->selisih) < 0.5;
    }
};
?>

{{-- tampilkanRincian dipegang Alpine — kolom saldo awal & mutasi tetap dirender, cukup disembunyikan --}}
<div x-data="{ tampilkanRincian: false, caraPakai: false }">
    <x-page-title
        title="Laporan Neraca"
        subtitle="Posisi keuangan per tanggal cutoff: Aktiva berbanding Hutang + Ekuitas + Laba Tahun Berjalan. Disusun dari grup akun master, nilai dibaca langsung dari tabel transaksi." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-4 pb-6">

            @include('pages::transaksi.keuangan.neraca.neraca-catatan', [
                'tahun' => $this->tahun,
                'labelTanggal' => $this->labelTanggal,
                'hppSudahTerjurnal' => $this->hppSudahTerjurnal,
                'hppTahunan' => $this->hppTahunan,
                'hppWajar' => $this->hppWajar,
            ])

            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-52">
                            <x-input-label for="tanggal" value="Per Tanggal" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400" />
                            <x-text-input id="tanggal" type="date" wire:model.live="tanggal" class="block w-full" />
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 cursor-pointer select-none dark:text-gray-200">
                            <input type="checkbox" x-model="tampilkanRincian" class="w-4 h-4 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-900">
                            Tampilkan saldo awal &amp; mutasi
                        </label>
                        <div class="flex items-center gap-2 pb-1">
                            <x-toggle wire:model.live="sertakanHppEstimasi" :trueValue="true" :falseValue="false"
                                :disabled="! $this->hppEstimasiTersedia" />
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                Sertakan HPP tahunan estimasi
                                <span class="block text-[10px] text-gray-500 dark:text-gray-400">
                                    @if (! $this->hppWajar)
                                        HPP {{ $this->tahun }} keluar negatif — tidak dipakai
                                    @else
                                        beban {{ number_format($this->hppTahunan, 0, ',', '.') }},
                                        persediaan turun sebesar itu juga
                                    @endif
                                </span>
                            </span>
                        </div>
                        <button type="button" x-on:click="caraPakai = !caraPakai"
                            class="pb-2 text-sm text-left text-blue-700 underline dark:text-blue-300">Cara pakai</button>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-right">
                        <div class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/40 dark:border-gray-700">
                            <div class="text-[10px] tracking-wider text-gray-500 uppercase">Total Aktiva</div>
                            <div class="font-mono text-sm font-bold text-gray-800 dark:text-gray-100">{{ number_format($this->laporan['totalAktiva'], 0, ',', '.') }}</div>
                        </div>
                        <div class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800/40 dark:border-gray-700">
                            <div class="text-[10px] tracking-wider text-gray-500 uppercase">Total Pasiva</div>
                            <div class="font-mono text-sm font-bold text-gray-800 dark:text-gray-100">{{ number_format($this->laporan['totalPasiva'], 0, ',', '.') }}</div>
                        </div>
                        <div class="px-3 py-2 border rounded-lg {{ $this->seimbang ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800' }}">
                            <div class="text-[10px] tracking-wider uppercase {{ $this->seimbang ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                {{ $this->seimbang ? 'Seimbang' : 'Tidak seimbang' }}
                            </div>
                            <div class="font-mono text-sm font-bold {{ $this->seimbang ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200' }}">
                                {{ $this->seimbang ? '✓' : number_format($this->selisih, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col flex-1 min-h-0 mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl" wire:loading.class="opacity-50">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-28">KODE</th>
                                <th>URAIAN</th>
                                <th class="w-40 text-right" x-show="tampilkanRincian">SALDO AWAL</th>
                                <th class="w-36 text-right" x-show="tampilkanRincian">DEBIT YTD</th>
                                <th class="w-36 text-right" x-show="tampilkanRincian">KREDIT YTD</th>
                                <th class="w-44 text-right">SALDO</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (! $this->tanggalValid)
                                <tr><td colspan="6" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                    Atur tanggal cutoff untuk menampilkan neraca.
                                </td></tr>
                            @else
                                @foreach ($this->laporan['sisiList'] as $kunciSisi => $sisi)
                                    <tr wire:key="nrc-sisi-{{ $kunciSisi }}" class="bg-gray-100 dark:bg-gray-800">
                                        <td class="ds-td-token">{{ $sisi['graId'] }}</td>
                                        <td class="text-xs font-bold tracking-wider uppercase">
                                            {{ $sisi['judul'] }}
                                            <span class="px-1 ml-1 text-[9px] rounded {{ $sisi['dkStatus'] === 'K' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $sisi['dkStatus'] }}</span>
                                        </td>
                                        <td colspan="3" x-show="tampilkanRincian"></td>
                                        <td></td>
                                    </tr>
                                    @forelse ($sisi['akunList'] as $akun)
                                        <tr wire:key="nrc-acc-{{ $akun['accId'] }}" class="{{ $akun['aktif'] ? '' : 'text-gray-400' }}">
                                            <td class="ds-td-token">{{ $akun['accId'] }}</td>
                                            <td class="pl-8 text-xs">
                                                {{ $akun['nama'] ?: '—' }}
                                                @unless ($akun['aktif'])
                                                    <span class="px-1 ml-1 text-[9px] text-gray-600 bg-gray-200 rounded dark:bg-gray-700 dark:text-gray-300">nonaktif</span>
                                                @endunless
                                                @if ($akun['hppEstimasi'] > 0)
                                                    <span class="px-1 ml-1 text-[9px] rounded bg-amber-200 text-amber-800">− HPP estimasi {{ number_format($akun['hppEstimasi'], 0, ',', '.') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-xs text-right ds-td-token" x-show="tampilkanRincian">{{ number_format($akun['saldoAwal'], 0, ',', '.') }}</td>
                                            <td class="text-xs text-right ds-td-token" x-show="tampilkanRincian">{{ number_format($akun['arusDebit'], 0, ',', '.') }}</td>
                                            <td class="text-xs text-right ds-td-token" x-show="tampilkanRincian">{{ number_format($akun['arusKredit'], 0, ',', '.') }}</td>
                                            <td class="text-right ds-td-token {{ $akun['saldo'] < 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">
                                                @if (abs($akun['saldo']) > 0.001)
                                                    {{ number_format($akun['saldo'], 0, ',', '.') }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr wire:key="nrc-kosong-{{ $kunciSisi }}">
                                            <td colspan="6" class="pl-8 text-xs italic text-gray-400">(Tidak ada akun aktif bersaldo pada grup ini)</td>
                                        </tr>
                                    @endforelse

                                    @if ($kunciSisi === 'ekuitas')
                                        <tr wire:key="nrc-laba-berjalan" class="font-semibold bg-blue-50/60 dark:bg-blue-900/10">
                                            <td class="ds-td-token">LRB</td>
                                            <td class="pl-8 text-xs">
                                                Laba (Rugi) Tahun Berjalan s/d {{ $this->labelTanggal }}
                                                <span class="ml-1 text-[10px] font-normal text-gray-500 dark:text-gray-400">
                                                    Σ (K − D) arus YTD grup 4 &amp; 5
                                                    @if ($this->hppEstimasiDipakai)
                                                        · dikurangi HPP estimasi {{ number_format($this->hppTahunan, 0, ',', '.') }}
                                                    @else
                                                        · HPP {{ $this->tahun }} belum terjurnal
                                                    @endif
                                                </span>
                                            </td>
                                            <td colspan="3" x-show="tampilkanRincian"></td>
                                            <td class="text-right ds-td-token {{ $this->laporan['labaBerjalan'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-blue-800 dark:text-blue-200' }}">
                                                {{ number_format($this->laporan['labaBerjalan'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr wire:key="nrc-total-{{ $kunciSisi }}" class="font-bold bg-blue-50 dark:bg-blue-900/20">
                                        <td></td>
                                        <td class="text-sm uppercase">
                                            Total {{ $sisi['judul'] }}
                                            @if ($kunciSisi === 'ekuitas')
                                                <span class="ml-1 text-[10px] font-normal normal-case text-gray-500 dark:text-gray-400">termasuk laba tahun berjalan</span>
                                            @endif
                                        </td>
                                        <td colspan="3" x-show="tampilkanRincian"></td>
                                        <td class="text-right ds-td-token text-blue-800 dark:text-blue-200">
                                            {{ number_format($sisi['total'] + ($kunciSisi === 'ekuitas' ? $this->laporan['labaBerjalan'] : 0), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach

                                <tr class="font-bold {{ $this->seimbang ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20' }}">
                                    <td></td>
                                    <td class="text-sm uppercase">Total Pasiva (Hutang + Ekuitas + Laba Berjalan)</td>
                                    <td colspan="3" x-show="tampilkanRincian"></td>
                                    <td class="px-3 py-2 font-mono text-base text-right">{{ number_format($this->laporan['totalPasiva'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="font-bold {{ $this->seimbang ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-rose-50 dark:bg-rose-900/20' }}">
                                    <td></td>
                                    <td class="text-sm uppercase">
                                        Selisih (Aktiva − Pasiva)
                                        <span class="ml-2 text-[10px] font-normal normal-case {{ $this->seimbang ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                            {{ $this->seimbang ? 'neraca seimbang' : 'periksa saldo awal tahun & akun tanpa grup' }}
                                        </span>
                                    </td>
                                    <td colspan="3" x-show="tampilkanRincian"></td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->seimbang ? 'text-emerald-800 dark:text-emerald-200' : 'text-rose-800 dark:text-rose-200' }}">
                                        {{ number_format($this->selisih, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
