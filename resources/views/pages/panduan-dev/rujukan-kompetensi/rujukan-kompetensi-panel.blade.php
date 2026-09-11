{{-- Partial: Lokasi panel & node data — matriks komponen ↔ node ↔ berkas. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">06 — Di siklik</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Lokasi Panel &amp; Node Data</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Seluruh jejak rujukan hidup di satu tempat: key root <code>rujukanKompetensi</code> pada CLOB
    <code>sktxn_rjhdrs.datadaftarpolirj_json</code>. Tidak ada tabel baru — Oracle siklik adalah sumber kebenaran dan
    dokumen per kunjungan memang disimpan sebagai JSON (lihat panduan Struktur Tabel &sect; Konvensi Tabel Baru).
</p>

<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Bagian</th>
                <th class="px-4 py-2">Muncul di</th>
                <th class="px-4 py-2">Node / sumber</th>
                <th class="px-4 py-2">Berkas</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-semibold">Panel kirim rujukan</td>
                <td class="px-4 py-2">EMR RJ &rarr; Perencanaan &rarr; tab <b>Tindak Lanjut</b>, hanya saat status
                    pulang &ldquo;dirujuk&rdquo; (<code>kdStatusPulang = "4"</code>)</td>
                <td class="px-4 py-2 font-mono text-xs">rujukanKompetensi</td>
                <td class="px-4 py-2 font-mono text-xs">transaksi/rj/emr-rj/rujukan-kompetensi/&#9889;rm-rujukan-kompetensi-rj-actions</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Komponen tampilan bersama</td>
                <td class="px-4 py-2">Dipakai panel: identitas kiriman, tabel kandidat, panduan kirim</td>
                <td class="px-4 py-2">—</td>
                <td class="px-4 py-2 font-mono text-xs">components/rujukan-kompetensi/{identitas-kiriman, kandidat-tabel, panduan-kirim}</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Cetak Surat Rujukan</td>
                <td class="px-4 py-2">Headless — dipicu
                    <code>dispatch('cetak-surat-rujukan-rj.open', rjNo: …)</code> dari panel EMR maupun layar pantau</td>
                <td class="px-4 py-2 font-mono text-xs">rujukanKompetensi + node EMR (anamnesa, pemeriksaan, diagnosis,
                    procedure, eresep)</td>
                <td class="px-4 py-2 font-mono text-xs">components/modul-dokumen/rj/surat-rujukan/&#9889;cetak-surat-rujukan-rj (+ -print)</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Layar pemantauan</td>
                <td class="px-4 py-2">Menu <b>Rujukan &rarr; Rujukan Keluar</b> (<code>/rujukan/keluar</code>)</td>
                <td class="px-4 py-2 font-mono text-xs">rujukanKompetensi (sapuan CLOB)</td>
                <td class="px-4 py-2 font-mono text-xs">transaksi/rujukan/rujukan-keluar/&#9889;rujukan-keluar (+ -actions)</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Trait endpoint</td>
                <td class="px-4 py-2">Dipakai panel lewat <code>use PcareSisruteTrait;</code></td>
                <td class="px-4 py-2">web_log_status</td>
                <td class="px-4 py-2 font-mono text-xs">app/Http/Traits/BPJS/PcareSisruteTrait.php</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Pilihan &amp; penyaji</td>
                <td class="px-4 py-2">Kriteria, status pulang rujuk, baris kandidat, penyaring jarak/waktu</td>
                <td class="px-4 py-2">—</td>
                <td class="px-4 py-2 font-mono text-xs">app/Support/Rujukan/{RujukanKompetensiOptions, RujukanKompetensiTampil}.php</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Bentuk node</h2>
@verbatim
    <pre class="p-4 mb-4 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">rujukanKompetensi: {
  kodeDiagnosa, diagnosaDesc, encounterId,
  kriteriaList: [{ linkId, text, type }], kriteriaPilih: "&lt;linkId&gt;", kriteriaIcd9, kriteriaIcd9Desc,
  wilayahList, kodePropinsi, namaPropinsi, kodeKabupaten, namaKabupaten,
  kodeSpesialis, namaSpesialis, kodeSubSpesialis, namaSubSpesialis, kodeSarana, namaSarana,
  estimasiRujuk: "d/m/Y", catatan,
  kandidatList: [ &lt;baris mentah response GetFaskesRujukan&gt; ], kandidatIdx: null|int,
  hasil: { noRujukanPcare, noRujukanSatuSehat, serviceRequestId, traceId,
           tglRujukan: "d/m/Y H:i:s", tujuanNama, tujuanPpk, tujuanSatuSehat,
           dikirimOleh, dikirimPada, noKunjunganPcare },
  dibatalkan: { oleh, pada, alasan } | null
}</pre>
@endverbatim

<div class="max-w-3xl space-y-3 text-sm">
    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Cara menulis node</div>
        <p>Pola <code>EmrRJTrait</code>: <code>DB::transaction</code> + <code>lockRJRow()</code> +
            <code>findDataRJ()</code> (baca ulang segar) + <code>updateJsonRJ()</code> +
            <code>appendAdminLogRJ(&hellip;, 'MR')</code>. Membaca tanpa mengunci lalu menulis akan menimpa
            perubahan petugas lain di kunjungan yang sama.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Siapa boleh apa</div>
        <p>Gate di <code>App\Support\AksiRole</code>: <code>RUJUKAN_KIRIM</code> = Dokter &amp; Admin
            (&rarr; <code>@@can('rujukan.kirim')</code>), <code>RUJUKAN_BATAL</code> = Admin &amp; Mr
            (&rarr; <code>@@can('rujukan.batal')</code>). Pembatalan sengaja dipisah karena dampaknya destruktif.
            Layar <code>/rujukan/keluar</code> hanya membaca; pengiriman &amp; pembatalan tetap dari panel EMR.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Jebakan kueri layar pemantauan</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>Filter <code>INSTR(datadaftarpolirj_json, '"rujukanKompetensi"')</code> wajib memakai
                <b>kutip penutup</b> supaya pencocokan tidak melebar ke kunci lain yang berawalan sama.</li>
            <li>Rentang tanggal adalah penjaga performa, bukan sekadar kenyamanan — sapuan CLOB itu berat.
                Karena itu data <b>tidak dimuat otomatis</b> saat halaman dibuka.</li>
            <li>CLOB dibaca lewat <code>App\Support\OracleLob::read()</code> supaya tahan
                <code>ORA-01555</code>/<code>ORA-22924</code> saat locator basi setelah EMR disimpan.</li>
            <li>Oracle 10g tidak punya <code>JSON_VALUE</code> — penyaringan isi node dilakukan di PHP setelah
                <code>json_decode()</code>, bukan di SQL.</li>
            <li>Baris dengan <code>hasil.noRujukanSatuSehat</code> kosong adalah <b>draft</b>: isian tersimpan tapi
                rujukan belum terbit. Tombol cetak sengaja tidak muncul untuk baris seperti itu.</li>
        </ul>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">Jejak panggilan</div>
        <p>Setiap panggilan (payload + response mentah) tercatat di <code>WEB_LOG_STATUS</code> dan bisa dibaca lewat
            menu <b>Sistem &rarr; Log BPJS API</b>. Perhatikan: tabel di siklik <b>tidak punya</b> kolom
            <code>http_payload</code> — payload disimpan di dalam kolom <code>response</code> pada key
            <code>payload</code>. Ini bukti wajib saat melapor ke Issue Tracker BPJS.</p>
    </div>
</div>
