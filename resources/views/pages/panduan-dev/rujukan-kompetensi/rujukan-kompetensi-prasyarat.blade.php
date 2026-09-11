{{-- Partial: Prasyarat & kredensial — yang tidak bisa diselesaikan dari kode. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">03 — Mulai</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Prasyarat &amp; Kredensial</h1>

<div
    class="px-4 py-3 mb-6 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-900/50 dark:text-amber-200">
    <b>Tiga hal berikut tidak bisa diselesaikan dari kode.</b> Bila salah satunya belum beres, semua panggilan akan
    gagal dengan pesan yang terdengar seperti bug aplikasi padahal bukan. Cek daftar ini lebih dulu sebelum
    membongkar payload.
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">1. Cons ID khusus <code>pcare-sisrute-rest</code></h2>
<p class="max-w-3xl mb-3 text-sm">
    Service SRBK didaftarkan <b>terpisah</b> dari PCare biasa. Memakai cons ID PCare produksi yang sudah ada dibalas
    <code>Unauthorized! You are not registered for this service!</code> — kejadian berulang di grup piloting.
    Pengajuan: <b>faskes &rarr; Kantor Cabang BPJS setempat</b>; untuk klinik ini KC Tulungagung (wilayah piloting).
    Kendala teknis pada cons ID yang sudah terbit diteruskan ke <b>IT Wilayah</b>. Cons ID dev punya masa berlaku —
    <code>Unauthorized! Consumer ID is expired!</code> berarti minta perpanjangan, bukan memperbaiki kode.
</p>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">2. Whitelist IP lewat ITSM BPJS</h2>
<p class="max-w-3xl mb-3 text-sm">
    API BPJS hanya melayani permintaan dari IP publik yang terdaftar (Formulir Pengajuan Akses Bridging SIM, diajukan
    lewat ITSM). Belum di-whitelist tampak sebagai <code>Connection timed out</code> / <code>refused</code> —
    bukan pesan yang menjelaskan. Bila lalu lintas keluar lewat VPS, nyalakan proxy egress
    (<code>BPJS_PROXY_AKTIF</code>, lihat <code>docs/bpjs-whitelist-ip-proxy.md</code>) supaya IP yang terlihat BPJS
    sama dengan yang didaftarkan.
</p>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">3. Data per pasien &amp; per dokter</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Sumber</th>
                <th class="px-4 py-2">Bila kosong</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-semibold">Encounter SATUSEHAT</td>
                <td class="px-4 py-2"><code>satusehat.encounterId</code> di node CLOB kunjungan — dikirim lebih dulu
                    lewat menu SATUSEHAT</td>
                <td class="px-4 py-2">Langkah 1 tidak bisa dijalankan; panel menahan tombol.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">IHS pasien (<code>patient_uuid</code>)</td>
                <td class="px-4 py-2"><code>skmst_pasiens.patient_uuid</code></td>
                <td class="px-4 py-2">Dipakai sebagai <code>idPasienSatuSehat</code> — tanpa itu pengiriman ditolak.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">IHS dokter (<code>dr_uuid</code>)</td>
                <td class="px-4 py-2"><code>skmst_doctors.dr_uuid</code></td>
                <td class="px-4 py-2">Dipakai sebagai <code>kdDokterSatuSehat</code>.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Kode dokter BPJS</td>
                <td class="px-4 py-2"><code>skmst_doctors.kd_dr_bpjs</code> (ikut dibawa <code>skview_rjkasir</code>)</td>
                <td class="px-4 py-2"><code>dokter tidak valid</code> — yang divalidasi kode BPJS, <b>bukan</b> IHS.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Diagnosa ICD-10</td>
                <td class="px-4 py-2">EMR Diagnosa (<code>skmst_mstdiags</code>)</td>
                <td class="px-4 py-2">Kriteria tidak bisa diminta. Pakai kode sespesifik mungkin: <code>A02</code>
                    kosong, <code>A02.9</code> jalan.</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Konfigurasi</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">config</th>
                <th class="px-4 py-2">env</th>
                <th class="px-4 py-2">Catatan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.url</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_URL</td>
                <td class="px-4 py-2">Dev: <code>https://dvlp.bpjs-kesehatan.go.id/pcare-sisrute-rest/api/v1.0</code>
                    (tanpa garis miring penutup).</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.cons_id / secret_key / user_key</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_*</td>
                <td class="px-4 py-2">Kosong = jatuh ke <code>bpjs.pcare.*</code>. Itu kemudahan lingkungan, bukan izin
                    memakai kredensial PCare untuk SRBK.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.username / password</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_*</td>
                <td class="px-4 py-2">Untuk header <code>X-authorization: Basic base64(user:pass:095)</code>.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.content_type</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_CONTENT_TYPE</td>
                <td class="px-4 py-2"><b>Kosong di dev</b> (server dvlp menolak permintaan yang membawa Content-Type),
                    <code>application/json</code> di produksi.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.kd_aplikasi</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_KD_APLIKASI</td>
                <td class="px-4 py-2"><code>095</code> — kode aplikasi PCare FKTP.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">bpjs.sisrute.simulasi</td>
                <td class="px-4 py-2 font-mono text-xs">SISRUTE_SIMULASI</td>
                <td class="px-4 py-2">Lihat bagian <b>Mode Simulasi</b>.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-mono text-xs">satusehat.organization_id</td>
                <td class="px-4 py-2 font-mono text-xs">SATUSEHAT_ORGANIZATION_ID</td>
                <td class="px-4 py-2">Dipakai sebagai <code>kodeFaskesSatuSehat</code> klinik — angkanya saja, tanpa
                    <code>Organization/</code>.</td>
            </tr>
        </tbody>
    </table>
</div>
