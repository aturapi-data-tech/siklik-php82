{{-- Partial: Alur 6 langkah + aturan payload yang paling mudah dilanggar. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">04 — Alur &amp; API</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Alur 6 Langkah &amp; Aturan</h1>

<p class="max-w-3xl mb-5 text-sm leading-relaxed">
    Urutannya mengikat: tiap langkah memakai hasil langkah sebelumnya. Panel EMR menyimpan state tiap langkah ke
    node JSON, jadi gangguan pusat di tengah jalan tidak menghapus isian — petugas cukup menekan ulang tombol terakhir.
</p>

<div class="max-w-3xl space-y-3 text-sm">

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">0. Prasyarat — Encounter sudah terkirim</div>
        <p>Node <code>satusehat.encounterId</code> terisi. Seluruh langkah membawa referensi Encounter itu;
            tanpa encounter, pusat tidak tahu episode mana yang dirujuk.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">1. Minta kriteria — <code>POST Sisrute/GetKriteriaRujukan</code></div>
        <p class="mb-2">Kirim <code>kodeFaskesSatuSehat</code>, <code>kodeDiagnosa</code> (ICD-10), dan
            <code>encounter.reference</code> berbentuk <code>"Encounter/&lt;uuid&gt;"</code>.</p>
        <p>Balasannya tiga kriteria (Terapi / Tindakan Medis / Upaya Diagnosis) dengan <code>linkId</code> yang
            <b>berbeda tiap diagnosa</b>, plus <code>JejaringWilayah</code> (34 provinsi + ±508 kabupaten/kota).</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">2. Dokter mengisi form</div>
        <ul class="space-y-1 list-disc list-inside">
            <li><b>Tepat satu</b> kriteria. Terapi &amp; Upaya Diagnosis memakai <code>valueBoolean: true</code>;
                Tindakan Medis memakai <code>valueString</code> berisi <b>ICD-9-CM yang valid</b> (dipilih lewat LOV
                prosedur, bukan diketik).</li>
            <li>Wilayah tujuan: provinsi (<b>2 digit angka</b>) dan kabupaten/kota dari <code>JejaringWilayah</code>.</li>
            <li>Estimasi tanggal rujuk — boleh hari ini.</li>
            <li>Spesialis &rarr; subspesialis dari referensi PCare (<code>getSpesialis</code>,
                <code>getReferensiSubSpesialis</code>), sarana dari <code>getSarana</code>. Sarana boleh kosong.</li>
        </ul>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">3. Minta kandidat — <code>POST Sisrute/GetFaskesRujukan</code></div>
        <p class="mb-2">Kirim isian langkah 2 + <code>kriteriaRujukan</code> berbentuk objek
            <code>{ item: [ … ] }</code> dengan <code>linkId</code> persis dari langkah 1, dan
            <code>estimasiRujuk</code> berformat <code>DD-MM-YYYY</code>.</p>
        <p>Balasannya <code>count</code> + <code>list[]</code>: <code>kdppk</code>, <code>nmppk</code>,
            <code>strataSatuSehat</code>, <code>kelas</code>, <code>distance</code>, <code>jadwal</code>,
            <code>jmlRujuk</code>, <code>kapasitas</code>, dan <code>kodeFaskesSatuSehat</code> yang di sini
            <b>berprefix</b> <code>Organization/</code>.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">4. Pilih satu faskes</div>
        <p>Pilihan dikunci ke daftar kandidat — SIM tidak menyediakan cara mengetik faskes sendiri.
            <code>kdppk</code> dan <code>kodeFaskesSatuSehat</code> diambil dari <b>baris yang sama</b>.</p>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">5. Kirim — <code>POST Sisrute/postKunjungan</code></div>
        <p class="mb-2">Payload = payload kunjungan PCare biasa + <code>kdStatusPulang "4"</code> +
            <code>rujukLanjut</code> + <code>satuSehatRujukan</code>. BPJS yang kemudian membentuk CarePlan &amp;
            ServiceRequest di SATUSEHAT.</p>
        <p>Sukses menghasilkan <code>noKunjungan</code> (sekaligus No. Rujukan PCare), <code>serviceRequestId</code>,
            dan <code>noRujukanSatuSehat</code>. Kunjungan ini <b>tidak boleh</b> juga dikirim lewat endpoint
            <code>kunjungan</code> PCare lama.</p>
    </div>

    <div class="p-4 border rounded-xl border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-200">
        <div class="mb-1 font-semibold">6. Batal — <code>DELETE Sisrute/deleteKunjungan</code> (destruktif)</div>
        <p>Menghapus <b>sampai pendaftaran PCare</b>, bukan hanya rujukannya, dan tidak ada work-around.
            Sesudahnya pasien harus didaftarkan ulang dari PCare; memaksa kirim ulang menghasilkan
            <code>Pendaftaran tidak valid</code>. UI wajib memperingatkan keras sebelum tombol ini ditekan.</p>
    </div>
</div>

<h2 class="mt-6 mb-2 text-lg font-semibold text-gray-900 dark:text-white">Aturan payload yang paling mudah dilanggar</h2>
<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Aturan</th>
                <th class="px-4 py-2">Rinci</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-semibold">Bentuk <code>encounter.reference</code> berbeda per langkah</td>
                <td class="px-4 py-2">Langkah 1 &amp; 3 (GetKriteria/GetFaskes): <code>"Encounter/&lt;uuid&gt;"</code>.
                    Di dalam <code>satuSehatRujukan</code> pada <code>postKunjungan</code>: <b>UUID polos</b>.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold"><code>kodeFaskesSatuSehat</code> tanpa prefix</td>
                <td class="px-4 py-2">Kirim <code>"100006775"</code>. Prefix <code>Organization/</code> hanya ada di
                    response dan wajib dibuang sebelum dipakai lagi.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold"><code>kriteriaRujukan</code> objek, satu item</td>
                <td class="px-4 py-2"><code>{ "item": [ … ] }</code>, bukan array polos. Dua item terisi ditolak:
                    &ldquo;hanya boleh mengisi salah satu dari Terapi, Tindakan Medis, atau Upaya Diagnosis&rdquo;.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold"><code>linkId</code> dinamis</td>
                <td class="px-4 py-2">Selalu dari response <code>GetKriteriaRujukan</code> pada diagnosa yang sama.
                    Jangan di-cache lintas diagnosa, jangan dikarang.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Tanggal ke BPJS <code>DD-MM-YYYY</code></td>
                <td class="px-4 py-2"><code>estimasiRujuk</code> &amp; <code>tglEstRujuk</code>. Node JSON internal dan
                    tampilan tetap <code>d/m/Y</code>.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold"><code>kdppk</code> &amp; Org ID satu baris</td>
                <td class="px-4 py-2">Beda baris &rarr; &ldquo;Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK
                    Dirujuk&rdquo;.</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold"><code>kdDokter</code> = kode BPJS</td>
                <td class="px-4 py-2">Bukan kode SATUSEHAT (yang itu masuk <code>kdDokterSatuSehat</code>).</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">Ganti isian &rarr; kandidat hangus</td>
                <td class="px-4 py-2">Mengubah diagnosa/kriteria/wilayah/subspesialis/tanggal mengosongkan
                    <code>kandidatList</code> &amp; <code>kandidatIdx</code>; daftar lama sudah tidak sah.</td>
            </tr>
        </tbody>
    </table>
</div>
