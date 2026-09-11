{{--
    Panel "Cara pakai" + catatan sumber data (partial ⚡laba-rugi).
    Butuh dari @include: $tahun, $rincianHpp, $hppWajar.
    Property komponen ($hppManualAktif) diwarisi otomatis dari view induk.
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
                        <strong>HPP hanya tersedia per TAHUN.</strong> Akun HPP baru terjurnal pada
                        <span class="font-mono">1 Desember</span> (cabang semu tahunan), jadi kolom Bulan Ini
                        maupun YTD memakai HPP tahunan {{ $tahun }}
                        = <span class="font-mono">{{ number_format($hppWajar ? $rincianHpp['hpp'] : 0, 0, ',', '.') }}</span>
                        — bukan HPP bulan berjalan.
                    </li>
                    @unless ($hppWajar)
                        <li class="font-semibold text-rose-700 dark:text-rose-300">
                            HPP tahunan {{ $tahun }} keluar NEGATIF
                            (<span class="font-mono">{{ number_format($rincianHpp['hpp'], 0, ',', '.') }}</span>)
                            karena nilai stok akhir
                            (<span class="font-mono">{{ number_format($rincianHpp['stokAkhir'], 0, ',', '.') }}</span>)
                            jauh melampaui saldo awal + arus persediaan. HPP mustahil negatif, jadi laporan memakai
                            0 dan menunggu angka dari override manual — jangan pakai laba di layar ini sebelum
                            HPP diisi.
                        </li>
                    @endunless
                    <li>
                        <strong>Stock opname belum rutin</strong> dan saldo awal persediaan belum lengkap,
                        sehingga HPP otomatis bisa menyimpang jauh. Pakai <em>Override HPP manual</em> di bawah
                        toolbar untuk memasukkan angka HPP yang benar
                        @if ($hppManualAktif)
                            <span class="px-1 rounded bg-amber-200 text-amber-900">sedang aktif</span>
                        @endif
                        — nilainya hanya hidup di layar, tidak ditulis ke database.
                    </li>
                    <li>
                        Tanda tiap pos mengikuti <span class="font-mono">dk_status</span> grup akun baris template
                        (Pendapatan = kredit − debit, Beban = debit − kredit), bukan
                        <span class="font-mono">acc_dk_status</span> master yang sering kosong.
                    </li>
                    <li>
                        Sumber data: <span class="font-mono">App\Support\Keuangan\Jurnal</span> (tabel transaksi
                        langsung, tanpa <span class="font-mono">skview_accounts</span>). Susunan pos: template
                        <span class="font-mono">L1</span> di <span class="font-mono">skacc_temlabarugineracadtls</span>
                        + <span class="font-mono">skacc_temaccountes</span>. Nama akun:
                        <span class="font-mono">Jurnal::namaAkun</span> (query terpisah, tanpa join di atas jurnal).
                        Jurnal dipanggil dengan <span class="font-mono">denganHpp = false</span> — cabang semu HPP
                        1 Desember dimatikan karena binding numeriknya memicu
                        <span class="font-mono">ORA-01790</span> di driver oci8, dan HPP sudah ditangani terpisah
                        di atas.
                    </li>
                </ul>

                <button type="button" x-on:click="caraPakai = !caraPakai"
                    class="mt-2 text-xs font-semibold underline text-amber-900 dark:text-amber-100">
                    <span x-text="caraPakai ? 'Sembunyikan cara pakai' : 'Cara pakai laporan ini'"></span>
                </button>

                <div x-show="caraPakai" x-cloak class="p-3 mt-2 text-xs bg-white border rounded border-amber-200 dark:bg-gray-900 dark:border-amber-800">
                    <ol class="ml-4 space-y-1 list-decimal">
                        <li>Pilih periode dengan tombol ◀ ▶ atau ketik <span class="font-mono">mm/yyyy</span>. Kolom
                            <strong>Bulan Ini</strong> memakai rentang 1 s/d akhir bulan; <strong>YTD</strong> dari
                            1 Januari tahun itu s/d akhir bulan yang sama.</li>
                        <li>Centang <strong>Tampilkan akun</strong> untuk membuka akun penyusun tiap pos, dan
                            <strong>Rincian HPP</strong> untuk melihat rumus HPP tahunan beserta arus jurnalnya.</li>
                        <li>Bandingkan angka pos PENJUALAN dengan buku besar akun yang sama; kalau cocok, selisih
                            laba yang tersisa berasal dari HPP atau dari data beban.</li>
                        <li>Kalau HPP otomatis tidak masuk akal, nyalakan <strong>Override HPP manual</strong> lalu
                            isi HPP Bulan Ini dan HPP YTD. Laba kotor dan laba bersih langsung memakai angka itu.</li>
                        <li>Rumus: laba kotor = Σ pendapatan − HPP; laba bersih = laba kotor − Σ beban.</li>
                    </ol>
                    <p class="mt-2">
                        Rincian HPP tahunan {{ $tahun }}: saldo awal persediaan
                        <span class="font-mono">{{ number_format($rincianHpp['saldoAwal'], 0, ',', '.') }}</span>
                        + arus persediaan <span class="font-mono">{{ number_format($rincianHpp['arus'], 0, ',', '.') }}</span>
                        − stok akhir <span class="font-mono">{{ number_format($rincianHpp['stokAkhir'], 0, ',', '.') }}</span>
                        = <span class="font-mono font-semibold">{{ number_format($rincianHpp['hpp'], 0, ',', '.') }}</span>
                        (akun persediaan <span class="font-mono">{{ $rincianHpp['akunPersediaan'] ?? '—' }}</span>).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
