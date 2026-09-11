{{-- Partial: Dokumen & sumber. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">10 — Referensi</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Dokumen &amp; Sumber</h1>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Di dalam repo</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">docs/rujukan-kompetensi.md</td>
                <td class="px-4 py-2">Catatan lapangan lengkap: katalog endpoint, body &amp; response, katalog error,
                    bukti tanggal dari grup piloting, dan perbedaan dengan sirus.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">.claude/skills/rujukan-kompetensi/</td>
                <td class="px-4 py-2">Skill repo — aturan payload yang paling mudah dilanggar. <b>Wajib dibaca</b>
                    sebelum menyentuh trait, panel, cetakan, atau layar pemantauan.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">app/Http/Traits/BPJS/PcareSisruteTrait.php</td>
                <td class="px-4 py-2">Empat endpoint + katalog hint + pembaca nomor rujukan
                    (<code>sisruteBacaNomorRujukan()</code>).</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">app/Support/Rujukan/</td>
                <td class="px-4 py-2"><code>RujukanKompetensiOptions</code> (kriteria, status pulang rujuk, pembangun
                    <code>kriteriaRujukan</code>) &amp; <code>RujukanKompetensiTampil</code>
                    (<code>kandidatBaris()</code>, <code>jarak()</code>, <code>waktu()</code>,
                    <code>infoTujuan()</code>).</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">database/fixtures/sisrute/</td>
                <td class="px-4 py-2">Jawaban mode simulasi (<code>SISRUTE_SIMULASI=true</code>).</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">config/bpjs.php &rarr; sisrute</td>
                <td class="px-4 py-2">Kredensial, mode simulasi, <code>content_type</code>, <code>kd_aplikasi</code>,
                    timeout, beserta catatan prasyarat administratif.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">docs/bpjs-whitelist-ip-proxy.md</td>
                <td class="px-4 py-2">Kebijakan whitelist IP BPJS &amp; jalur egress lewat proxy VPS.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">docs/ttd-pattern-pdf-print.md</td>
                <td class="px-4 py-2">Pola tanda tangan di cetakan PDF — dipakai Surat Pengantar Rujukan.</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Di luar repo</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="w-64 px-4 py-2 font-semibold">Permenkes 16/2024</td>
                <td class="px-4 py-2">Sistem rujukan terintegrasi &amp; rujukan elektronik; Pasal 17 = isi minimal
                    surat rujukan yang dicetak.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Surat himbauan 13 Mei 2026</td>
                <td class="px-4 py-2">Kemkes &amp; BPJS — persiapan bridging SRBK oleh fasyankes dan vendor SIM.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Grup resmi SATUSEHAT Rujukan &times; PCare &times; VClaim</td>
                <td class="px-4 py-2">Kanal tanya-jawab teknis Apr&ndash;Sep 2026; sumber sampel payload/response dan
                    hampir seluruh katalog error di halaman ini.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Postman &ldquo;30. Use Case - Rujukan Pasien&rdquo; (V30062026)</td>
                <td class="px-4 py-2">Koleksi contoh payload resmi — perhatikan bagian FKTP, sisanya FKRTL.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Formulir Pengajuan Akses Bridging SIM</td>
                <td class="px-4 py-2">Untuk whitelist IP lewat ITSM BPJS.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Issue Tracker BPJS</td>
                <td class="px-4 py-2">Kanal resmi pelaporan kendala; lampirkan payload + response + trace-id.</td>
            </tr>
        </tbody>
    </table>
</div>

<p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
    Wilayah piloting: Kota Bandung, Kota Makassar, <b>Kabupaten Tulungagung</b>, Kabupaten Muara Enim.
    Klinik ini berada di Tulungagung, sehingga pengajuan kredensial diarahkan ke KC Tulungagung.
</p>
