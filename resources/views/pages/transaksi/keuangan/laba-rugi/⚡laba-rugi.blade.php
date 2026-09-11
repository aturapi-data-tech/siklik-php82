<?php

use App\Support\Keuangan\Hpp;
use App\Support\Keuangan\Jurnal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Laporan Laba Rugi — susunan pos dari template L1 (skacc_temlabarugineracahdrs →
 * skacc_temlabarugineracadtls → skacc_temaccountes), nilai per akun dari
 * App\Support\Keuangan\Jurnal (jurnal dibaca LANGSUNG dari tabel transaksi, bukan skview_accounts).
 *
 * Tanda tiap pos mengikuti dk_status GRUP AKUN baris template (skacc_gr_accountses),
 * bukan acc_dk_status master: K → kredit − debit, D → debit − kredit. Pos Harga Pokok
 * Penjualan dikecualikan — akun HPP (skacc_confacctxns conf_id 9) hanya mendapat cabang semu
 * dari App\Support\Keuangan\Hpp pada 1 Desember, jadi arus jurnalnya nol untuk bulan sebelum
 * Desember. Halaman memakai HPP TAHUNAN (Hpp::nilai) + rincian rumusnya, dan menyediakan
 * override manual (state komponen saja, tidak ditulis ke DB) karena stock opname belum rutin.
 * Laba kotor = Σ pendapatan − HPP; laba bersih = laba kotor − Σ beban.
 */
new class extends Component
{
    /** Format internal: 'YYYY-MM' */
    public string $periode = '';

    /** Format input user: 'MM/YYYY' */
    public string $periodeInput = '';

    /** Override HPP manual — hidup hanya selama sesi komponen, tidak disimpan ke DB. */
    public bool $hppManualAktif = false;
    public string $hppManualBulan = '';
    public string $hppManualYtd = '';

    public function mount(): void
    {
        $this->setPeriode(now()->format('Y-m'));
    }

    public function updatedPeriodeInput(string $value): void
    {
        if (! preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', trim($value), $cocok)) {
            $this->periode = '';

            return;
        }
        [, $bulan, $tahun] = $cocok;
        $this->periode = "{$tahun}-{$bulan}";
    }

    private function setPeriode(string $tahunBulan): void
    {
        $this->periode = $tahunBulan;
        $this->periodeInput = Carbon::parse("{$tahunBulan}-01")->format('m/Y');
    }

    public function prevMonth(): void
    {
        if ($this->periode !== '') {
            $this->setPeriode(Carbon::parse("{$this->periode}-01")->subMonth()->format('Y-m'));
        }
    }

    public function nextMonth(): void
    {
        if ($this->periode !== '') {
            $this->setPeriode(Carbon::parse("{$this->periode}-01")->addMonth()->format('Y-m'));
        }
    }

    #[Computed]
    public function tahun(): int
    {
        return $this->periode === '' ? (int) now()->format('Y') : (int) substr($this->periode, 0, 4);
    }

    #[Computed]
    public function bulanStart(): string
    {
        return $this->periode === '' ? '' : "{$this->periode}-01";
    }

    #[Computed]
    public function bulanEnd(): string
    {
        return $this->periode === '' ? '' : Carbon::parse("{$this->periode}-01")->endOfMonth()->toDateString();
    }

    #[Computed]
    public function ytdStart(): string
    {
        return $this->periode === '' ? '' : sprintf('%04d-01-01', $this->tahun);
    }

    #[Computed]
    public function labelRentang(): string
    {
        if ($this->periode === '') {
            return '';
        }
        $akhir = Carbon::parse($this->bulanEnd)->format('d/m/Y');

        return 'Bulan: '.Carbon::parse($this->bulanStart)->format('d/m/Y')." — {$akhir}"
            .' · YTD: '.Carbon::parse($this->ytdStart)->format('d/m/Y')." — {$akhir}";
    }

    /**
     * Pos template L1 urut temp_dtl_seq + daftar akunnya, tanpa nilai.
     * peran: 'pendapatan' (K), 'beban' (D), 'hpp' (pos yang memuat akun HPP conf 9).
     */
    #[Computed]
    public function struktur(): array
    {
        $grupAkunList = DB::table('skacc_gr_accountses')->get()->keyBy('gra_id');
        $akunHpp = Jurnal::akunKonfigurasiId(Hpp::CONF_HPP);

        $posList = [];
        $posRows = DB::table('skacc_temlabarugineracadtls')
            ->where('temp_id', 'L1')
            ->orderByRaw("to_number(nvl(temp_dtl_seq,'999')), temp_dtl")
            ->get();

        foreach ($posRows as $pos) {
            $accIdList = DB::table('skacc_temaccountes')
                ->where('temp_dtl', $pos->temp_dtl)
                ->orderByRaw("to_number(nvl(temacc_seq,'999')), acc_id")
                ->pluck('acc_id')
                ->map(fn ($accId) => (string) $accId)
                ->all();

            $grupAkun = $grupAkunList[(string) $pos->gra_id] ?? null;
            $dkStatus = (string) ($grupAkun->dk_status ?? 'D');
            $isHpp = $akunHpp !== null && in_array($akunHpp, $accIdList, true);

            $posList[] = [
                'id' => (string) $pos->temp_dtl,
                'desc' => (string) $pos->temp_dtl_desc,
                'graId' => (string) $pos->gra_id,
                'graDesc' => (string) ($grupAkun->gra_desc ?? ''),
                'dkStatus' => $dkStatus,
                'peran' => $isHpp ? 'hpp' : ($dkStatus === 'K' ? 'pendapatan' : 'beban'),
                'accIdList' => $accIdList,
            ];
        }

        return $posList;
    }

    /**
     * Pos bernilai + subtotal pendapatan/beban/HPP jurnal.
     * Dua pemindaian jurnal saja (bulan & YTD) lewat Jurnal::arusPerAkun — bukan per akun.
     */
    #[Computed]
    public function laporan(): array
    {
        $kosong = ['posList' => [], 'pendapatanBulan' => 0.0, 'pendapatanYtd' => 0.0,
            'bebanBulan' => 0.0, 'bebanYtd' => 0.0];
        if ($this->periode === '') {
            return $kosong;
        }

        $struktur = $this->struktur;
        $semuaAkun = [];
        foreach ($struktur as $pos) {
            array_push($semuaAkun, ...$pos['accIdList']);
        }
        $semuaAkun = array_values(array_unique($semuaAkun));

        // denganHpp=false: HPP ditangani di tingkat halaman (nilai tahunan + override), bukan lewat
        // cabang semu 1 Desember — supaya tidak dobel dan tidak kena ORA-01790 (lihat catatan bawah).
        $arusBulan = Jurnal::arusPerAkun($semuaAkun, $this->bulanStart, $this->bulanEnd, false);
        $arusYtd = Jurnal::arusPerAkun($semuaAkun, $this->ytdStart, $this->bulanEnd, false);
        $namaAkun = Jurnal::namaAkun($semuaAkun);
        $nol = ['debit' => 0.0, 'kredit' => 0.0];
        // Pos pendapatan bertanda kredit; pos beban dan pos HPP bertanda debit.
        $hitung = fn (array $arus, bool $sisiKredit) => $sisiKredit
            ? $arus['kredit'] - $arus['debit']
            : $arus['debit'] - $arus['kredit'];

        $laporan = $kosong;
        foreach ($struktur as $pos) {
            $sisiKredit = $pos['peran'] === 'pendapatan';
            $bulan = 0.0;
            $ytd = 0.0;
            $akunList = [];
            foreach ($pos['accIdList'] as $accId) {
                $nilaiBulan = $hitung($arusBulan[$accId] ?? $nol, $sisiKredit);
                $nilaiYtd = $hitung($arusYtd[$accId] ?? $nol, $sisiKredit);
                $bulan += $nilaiBulan;
                $ytd += $nilaiYtd;
                $akunList[] = ['accId' => $accId, 'nama' => (string) ($namaAkun[$accId] ?? ''),
                    'bulan' => $nilaiBulan, 'ytd' => $nilaiYtd];
            }
            $pos['akunList'] = $akunList;
            $pos['bulan'] = $bulan;
            $pos['ytd'] = $ytd;
            $laporan['posList'][] = $pos;

            if ($pos['peran'] === 'pendapatan') {
                $laporan['pendapatanBulan'] += $bulan;
                $laporan['pendapatanYtd'] += $ytd;
            }
            if ($pos['peran'] === 'beban') {
                $laporan['bebanBulan'] += $bulan;
                $laporan['bebanYtd'] += $ytd;
            }
        }

        return $laporan;
    }

    /** Rincian rumus HPP tahunan (saldo awal persediaan + arus − stok akhir). */
    #[Computed]
    public function rincianHpp(): array
    {
        return Hpp::rincian($this->tahun);
    }

    #[Computed]
    public function hppTahunan(): float
    {
        return (float) $this->rincianHpp['hpp'];
    }

    /** HPP negatif mustahil — tanda stok akhir / saldo awal persediaan masih kacau. */
    #[Computed]
    public function hppWajar(): bool
    {
        return $this->hppTahunan > 0;
    }

    #[Computed]
    public function hppBulan(): float
    {
        return $this->hppManualAktif
            ? (float) ($this->hppManualBulan ?: 0)
            : ($this->hppWajar ? $this->hppTahunan : 0.0);
    }

    #[Computed]
    public function hppYtd(): float
    {
        return $this->hppManualAktif
            ? (float) ($this->hppManualYtd ?: 0)
            : ($this->hppWajar ? $this->hppTahunan : 0.0);
    }

    #[Computed]
    public function labaKotorBulan(): float
    {
        return (float) $this->laporan['pendapatanBulan'] - $this->hppBulan;
    }

    #[Computed]
    public function labaKotorYtd(): float
    {
        return (float) $this->laporan['pendapatanYtd'] - $this->hppYtd;
    }

    #[Computed]
    public function labaBersihBulan(): float
    {
        return $this->labaKotorBulan - (float) $this->laporan['bebanBulan'];
    }

    #[Computed]
    public function labaBersihYtd(): float
    {
        return $this->labaKotorYtd - (float) $this->laporan['bebanYtd'];
    }
};
?>

{{-- tampilkanAkun & rincian HPP dipegang Alpine — baris tetap dirender, tidak memicu hitung ulang server --}}
<div x-data="{ tampilkanAkun: false, tampilkanHpp: false, caraPakai: false }">
    <x-page-title
        title="Laporan Laba Rugi"
        subtitle="Pendapatan, harga pokok penjualan, dan beban untuk bulan terpilih plus akumulasi tahun berjalan. Nilai dibaca langsung dari tabel transaksi." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-4 pb-6">

            @include('pages::transaksi.keuangan.laba-rugi.laba-rugi-catatan', [
                'tahun' => $this->tahun,
                'rincianHpp' => $this->rincianHpp,
                'hppWajar' => $this->hppWajar,
            ])

            @include('pages::transaksi.keuangan.laba-rugi.laba-rugi-toolbar', [
                'labelRentang' => $this->labelRentang,
                'labaBersihBulan' => $this->labaBersihBulan,
                'labaBersihYtd' => $this->labaBersihYtd,
            ])

            <div class="flex flex-col flex-1 min-h-0 mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl" wire:loading.class="opacity-50">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-28">KODE</th>
                                <th>URAIAN</th>
                                <th class="w-40 text-right">BULAN INI</th>
                                <th class="w-40 text-right">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($periode === '')
                                <tr><td colspan="4" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                    Atur periode untuk menampilkan laporan.
                                </td></tr>
                            @else
                                @forelse ($this->laporan['posList'] as $pos)
                                    <tr wire:key="lr-pos-{{ $pos['id'] }}" class="bg-gray-100 dark:bg-gray-800">
                                        <td class="ds-td-token">{{ $pos['id'] }}</td>
                                        <td class="text-xs font-bold tracking-wider uppercase">
                                            {{ $pos['desc'] }}
                                            <span class="px-1 ml-1 text-[9px] rounded {{ $pos['dkStatus'] === 'K' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}"
                                                title="grup akun {{ $pos['graId'] }} {{ $pos['graDesc'] }}">{{ $pos['dkStatus'] }}</span>
                                            @if ($pos['peran'] === 'hpp')
                                                <span class="px-1 ml-1 text-[9px] normal-case rounded bg-amber-200 text-amber-800">HPP tahunan</span>
                                            @endif
                                        </td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    @foreach ($pos['akunList'] as $akun)
                                        <tr wire:key="lr-acc-{{ $pos['id'] }}-{{ $akun['accId'] }}" x-show="tampilkanAkun" class="text-xs text-gray-500 dark:text-gray-400">
                                            <td class="ds-td-token">{{ $akun['accId'] }}</td>
                                            <td class="pl-8">{{ $akun['nama'] ?: '—' }}</td>
                                            <td class="text-right ds-td-token">{{ number_format($akun['bulan'], 0, ',', '.') }}</td>
                                            <td class="text-right ds-td-token">{{ number_format($akun['ytd'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach

                                    @if ($pos['peran'] === 'hpp')
                                        <tr wire:key="lr-hpp-rincian" x-show="tampilkanHpp" class="text-xs italic text-gray-500 dark:text-gray-400">
                                            <td></td>
                                            <td class="pl-8" colspan="3">
                                                HPP tahunan {{ $this->tahun }} = saldo awal persediaan
                                                {{ number_format($this->rincianHpp['saldoAwal'], 0, ',', '.') }}
                                                + arus persediaan {{ number_format($this->rincianHpp['arus'], 0, ',', '.') }}
                                                − stok akhir {{ number_format($this->rincianHpp['stokAkhir'], 0, ',', '.') }}
                                                = <span class="font-semibold">{{ number_format($this->hppTahunan, 0, ',', '.') }}</span>
                                                (akun persediaan {{ $this->rincianHpp['akunPersediaan'] ?? '—' }})
                                            </td>
                                        </tr>
                                    @endif

                                    <tr wire:key="lr-sub-{{ $pos['id'] }}" class="font-semibold {{ $pos['peran'] === 'hpp' && $hppManualAktif ? 'bg-amber-100 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-800/40' }}">
                                        <td></td>
                                        <td class="text-xs uppercase">
                                            Subtotal {{ $pos['desc'] }}
                                            @if ($pos['peran'] === 'hpp')
                                                <span class="ml-1 text-[10px] normal-case text-amber-700 dark:text-amber-300">
                                                    @if ($hppManualAktif)
                                                        (override manual)
                                                    @elseif ($this->hppWajar)
                                                        (HPP tahunan {{ $this->tahun }})
                                                    @else
                                                        (HPP tahunan {{ $this->tahun }} negatif — dianggap 0, isi override manual)
                                                    @endif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-right ds-td-token">
                                            {{ number_format($pos['peran'] === 'hpp' ? $this->hppBulan : $pos['bulan'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-right ds-td-token">
                                            {{ number_format($pos['peran'] === 'hpp' ? $this->hppYtd : $pos['ytd'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-16 text-center text-gray-500 dark:text-gray-400">
                                        Template L1 belum punya susunan pos.
                                    </td></tr>
                                @endforelse

                                <tr class="font-bold bg-blue-50 dark:bg-blue-900/20">
                                    <td></td>
                                    <td class="text-sm uppercase">
                                        Laba Kotor (Pendapatan − HPP)
                                        <span class="ml-1 text-[10px] font-normal normal-case text-gray-500 dark:text-gray-400">
                                            pendapatan {{ number_format($this->laporan['pendapatanYtd'], 0, ',', '.') }} − HPP {{ number_format($this->hppYtd, 0, ',', '.') }} (YTD)
                                        </span>
                                    </td>
                                    <td class="text-right ds-td-token text-blue-800 dark:text-blue-200">{{ number_format($this->labaKotorBulan, 0, ',', '.') }}</td>
                                    <td class="text-right ds-td-token text-blue-800 dark:text-blue-200">{{ number_format($this->labaKotorYtd, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="font-bold {{ $this->labaBersihBulan < 0 ? 'bg-rose-50 dark:bg-rose-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20' }}">
                                    <td></td>
                                    <td class="text-sm uppercase">
                                        Laba (Rugi) Bersih
                                        <span class="ml-1 text-[10px] font-normal normal-case text-gray-500 dark:text-gray-400">
                                            laba kotor − beban {{ number_format($this->laporan['bebanYtd'], 0, ',', '.') }} (YTD)
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihBulan < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihBulan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-base text-right {{ $this->labaBersihYtd < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}">
                                        {{ number_format($this->labaBersihYtd, 0, ',', '.') }}
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
