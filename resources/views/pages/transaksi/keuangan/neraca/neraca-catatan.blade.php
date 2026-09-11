{{--
    Panel "Cara pakai" + catatan sumber data (partial ⚡neraca).
    Butuh dari @include: $tahun, $labelTanggal, $hppSudahTerjurnal, $hppTahunan, $hppWajar.
    Butuh Alpine `caraPakai` dari x-data root halaman.
    Partial TIDAK mewarisi `use` blok atas — semua nilai sudah dihitung di komponen.
--}}
<div class="mb-4">
    <div class="p-4 border rounded-lg border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
        <div class="flex items-start gap-3">
            <span class="text-xl leading-none">⚠️</span>
            <div class="flex-1 text-sm text-amber-900 dark:text-amber-100">
                <p class="font-semibold">Verifikasi manual sebelum dipakai sebagai laporan resmi.</p>
                <ul class="mt-2 ml-5 space-y-0.5 text-xs list-disc">
                    <li>
                        <strong>Saldo awal tahun</strong> diambil dari
                        <span class="font-mono">sktxn_saldoawalakuns</span> tahun {{ $tahun }} (kedua sisi D dan K).
                        Saldo awal yang belum lengkap membuat neraca tidak seimbang — itu masalah DATA,
                        bukan rumus laporan.
                    </li>
                    <li>
                        <strong>HPP tidak ikut di jurnal halaman ini.</strong> Akun HPP hanya punya cabang semu
                        tahunan bertanggal <span class="font-mono">1 Desember</span>, dan cabang itu sengaja
                        dimatikan di sini (lihat butir terakhir), jadi Laba Tahun Berjalan s/d {{ $labelTanggal }}
                        belum dipotong HPP — angkanya terlalu besar.
                        @if (! $hppWajar)
                            HPP {{ $tahun }} sendiri keluar NEGATIF
                            (<span class="font-mono">{{ number_format($hppTahunan, 0, ',', '.') }}</span>) karena
                            nilai stok akhir jauh melampaui saldo awal + arus persediaan, jadi toggle estimasi
                            dimatikan; perbaiki stock opname / saldo awal persediaan dulu.
                        @else
                            Nyalakan toggle <em>Sertakan HPP tahunan estimasi</em> untuk menjurnalkan
                            <span class="font-mono">{{ number_format($hppTahunan, 0, ',', '.') }}</span>
                            sebagai beban DAN menurunkan persediaan sebesar itu juga, sehingga neraca tetap
                            seimbang.
                        @endif
                    </li>
                    <li>
                        <strong>Laba Tahun Berjalan</strong> = Σ (K − D) arus 1 Januari s/d cutoff untuk SEMUA akun
                        grup 4 Pendapatan &amp; 5 Beban — bukan dari template Laba Rugi, jadi bisa berbeda dari
                        halaman Laba Rugi yang hanya memuat akun template <span class="font-mono">L1</span>.
                    </li>
                    <li>
                        Susunan: grup akun master <span class="font-mono">skacc_gr_accountses</span>
                        (1 AKTIVA / 2 HUTANG / 3 EKUITAS) — siklik tidak punya tabel sub-grup akun, dan template
                        <span class="font-mono">N1</span> tidak dipakai karena hanya memuat sebagian akun.
                        Sumber nilai: <span class="font-mono">App\Support\Keuangan\Jurnal</span>
                        (tabel transaksi langsung, tanpa <span class="font-mono">skview_accounts</span>),
                        dipanggil dengan <span class="font-mono">denganHpp = false</span> — cabang semu HPP
                        1 Desember dimatikan karena binding numeriknya memicu
                        <span class="font-mono">ORA-01790</span> di driver oci8.
                    </li>
                </ul>

                <button type="button" x-on:click="caraPakai = !caraPakai"
                    class="mt-2 text-xs font-semibold underline text-amber-900 dark:text-amber-100">
                    <span x-text="caraPakai ? 'Sembunyikan cara pakai' : 'Cara pakai laporan ini'"></span>
                </button>

                <div x-show="caraPakai" x-cloak class="p-3 mt-2 text-xs bg-white border rounded border-amber-200 dark:bg-gray-900 dark:border-amber-800">
                    <ol class="ml-4 space-y-1 list-decimal">
                        <li>Pilih tanggal cutoff. Saldo tiap akun = saldo awal tahun + mutasi 1 Januari s/d tanggal
                            itu, bertanda natural menurut D/K grupnya (Aktiva D − K, Hutang &amp; Ekuitas K − D).</li>
                        <li>Centang <strong>Tampilkan saldo awal &amp; mutasi</strong> untuk membuka kolom saldo awal,
                            debit YTD, dan kredit YTD per akun — dipakai menelusuri akun mana yang bikin selisih.</li>
                        <li>Akun nonaktif yang tidak punya saldo maupun mutasi disembunyikan; akun nonaktif yang masih
                            bersaldo tetap tampil dengan label <em>nonaktif</em>.</li>
                        <li>Badge kanan atas menunjukkan Aktiva − (Hutang + Ekuitas + Laba Berjalan). Kalau tidak
                            seimbang, mulai dari saldo awal tahun di <span class="font-mono">sktxn_saldoawalakuns</span>,
                            lalu akun yang grupnya salah.</li>
                        <li>Kalau cutoff sebelum 1 Desember, pertimbangkan toggle HPP estimasi supaya ekuitas tidak
                            kelewat optimistis.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
