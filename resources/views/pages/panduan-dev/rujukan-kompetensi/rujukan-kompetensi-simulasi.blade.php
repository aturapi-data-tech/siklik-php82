{{-- Partial: Mode simulasi (SISRUTE_SIMULASI) — latihan tanpa jaringan. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">07 — Di siklik</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Mode Simulasi</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Cons ID <code>pcare-sisrute-rest</code> baru terbit setelah diajukan ke KC BPJS, sementara panel, cetakan, dan
    layar pemantauan perlu diuji &mdash; dan petugas perlu berlatih. Untuk itu ada mode simulasi: keempat method trait
    menjawab dari berkas contoh <b>tanpa menyentuh jaringan</b>.
</p>

@verbatim
    <pre class="p-4 mb-4 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg"># .env
SISRUTE_SIMULASI=true</pre>
@endverbatim

<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Method</th>
                <th class="px-4 py-2">Berkas jawaban</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-mono text-xs">sisruteGetKriteriaRujukan()</td>
                <td class="px-4 py-2 font-mono text-xs">database/fixtures/sisrute/get-kriteria-rujukan.json</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">sisruteGetFaskesRujukan()</td>
                <td class="px-4 py-2 font-mono text-xs">database/fixtures/sisrute/get-faskes-rujukan.json</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">sisrutePostKunjungan()</td>
                <td class="px-4 py-2 font-mono text-xs">database/fixtures/sisrute/post-kunjungan.json</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">sisruteDeleteKunjungan()</td>
                <td class="px-4 py-2 font-mono text-xs">database/fixtures/sisrute/delete-kunjungan.json</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Yang perlu diketahui</h2>
<ul class="max-w-3xl mb-6 space-y-2 text-sm list-disc list-inside">
    <li>Berkas menyimpan amplop gateway <code>{ metaData: { code, message }, response: { … } }</code> persis seperti
        balasan sungguhan; key <code>_catatan</code> di dalamnya hanya penjelasan dan diabaikan trait.</li>
    <li>Data pasien di dalamnya <b>dummy</b> — nama, NIK, nomor kartu, dan nomor rujukan semuanya karangan; nama faskes
        sengaja memakai kata &ldquo;CONTOH&rdquo; supaya tidak tertukar dengan data sungguhan.</li>
    <li><code>get-faskes-rujukan.json</code> memuat tiga kandidat dengan strata berbeda (Dasar / Madya / Utama), salah
        satunya tanpa jadwal &amp; kapasitas — untuk menguji tampilan nilai kosong.</li>
    <li>Panggilan simulasi <b>tetap dicatat</b> ke <code>WEB_LOG_STATUS</code>, dengan <code>http_req</code> dan pesan
        berawalan <code>[SIMULASI]</code>, supaya latihan tidak tersamar sebagai panggilan sungguhan.</li>
</ul>

<div
    class="max-w-3xl px-4 py-3 mb-6 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-900/50 dark:text-amber-200">
    <b>Mode simulasi tetap menulis ke node JSON kunjungan.</b> Nomor rujukan palsu akan tersimpan di
    <code>rujukanKompetensi.hasil</code> dan ikut tampil di layar <code>/rujukan/keluar</code> serta bisa dicetak.
    Jangan menyalakannya di lingkungan produksi, dan bersihkan node kunjungan yang dipakai latihan.
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Alur latihan yang disarankan</h2>
<ol class="max-w-3xl space-y-1 text-sm list-decimal list-inside">
    <li>Nyalakan <code>SISRUTE_SIMULASI=true</code>, jalankan <code>php artisan config:clear</code>.</li>
    <li>Pilih satu kunjungan RJ uji, set tindak lanjut menjadi <b>dirujuk</b>.</li>
    <li>Jalankan panel dari langkah 1 sampai kirim; perhatikan bentuk kandidat &amp; nilai kosong.</li>
    <li>Cetak Surat Rujukan — periksa dua halaman terbentuk (Surat Pengantar + Resume Klinis).</li>
    <li>Buka <code>/rujukan/keluar</code>, muat data, dan bandingkan isian di modal rincian dengan node JSON.</li>
    <li>Matikan simulasi kembali sebelum lingkungan dipakai sungguhan.</li>
</ol>
