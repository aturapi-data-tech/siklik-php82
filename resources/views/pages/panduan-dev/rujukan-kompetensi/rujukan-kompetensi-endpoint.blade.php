{{-- Partial: Katalog endpoint & contoh JSON. Blok JSON dibungkus penanda verbatim
     supaya kurung kurawal tidak dibaca Blade sebagai ekspresi. --}}

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">05 — Alur &amp; API</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Katalog Endpoint &amp; Contoh JSON</h1>

<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Base URL dev: <code>https://dvlp.bpjs-kesehatan.go.id/pcare-sisrute-rest/api/v1.0</code> (tanpa garis miring
    penutup; trait menyambung <code>/Sisrute/&lt;endpoint&gt;</code>). Autentikasi mewarisi kontrak PCare:
    <code>X-cons-id</code>, <code>X-timestamp</code>, <code>X-signature</code>, <code>user_key</code>, dan
    <code>X-authorization: Basic base64(username:password:095)</code>. Response terbungkus amplop
    <code>{ metaData, response }</code>.
</p>

<div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700 mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr class="text-xs font-semibold text-left text-gray-500 uppercase dark:text-gray-400">
                <th class="px-4 py-2">Verb</th>
                <th class="px-4 py-2">Endpoint</th>
                <th class="px-4 py-2">Fungsi</th>
                <th class="px-4 py-2">Method trait</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-2 font-semibold">POST</td>
                <td class="px-4 py-2 font-mono text-xs">Sisrute/GetKriteriaRujukan</td>
                <td class="px-4 py-2">Kriteria + jejaring wilayah per ICD-10</td>
                <td class="px-4 py-2 font-mono text-xs">sisruteGetKriteriaRujukan()</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">POST</td>
                <td class="px-4 py-2 font-mono text-xs">Sisrute/GetFaskesRujukan</td>
                <td class="px-4 py-2">Daftar kandidat faskes tujuan</td>
                <td class="px-4 py-2 font-mono text-xs">sisruteGetFaskesRujukan()</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold">POST</td>
                <td class="px-4 py-2 font-mono text-xs">Sisrute/postKunjungan</td>
                <td class="px-4 py-2">Kirim kunjungan-rujuk; nomor rujukan terbit</td>
                <td class="px-4 py-2 font-mono text-xs">sisrutePostKunjungan()</td>
            </tr>
            <tr>
                <td class="px-4 py-2 font-semibold text-rose-600 dark:text-rose-400">DELETE</td>
                <td class="px-4 py-2 font-mono text-xs">Sisrute/deleteKunjungan</td>
                <td class="px-4 py-2">Batal — <b>menghapus sampai pendaftaran PCare</b></td>
                <td class="px-4 py-2 font-mono text-xs">sisruteDeleteKunjungan()</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- ── 1. GetKriteriaRujukan ── --}}
<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">1. <code>Sisrute/GetKriteriaRujukan</code></h2>
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Request</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{
  "kodeFaskesSatuSehat": "100028369",
  "kodeDiagnosa": "Z37.0",
  "encounter": { "reference": "Encounter/73d4d339-4b71-4b51-b83d-8569523b839b" }
}</pre>
@endverbatim
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Response (dipotong)</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{
  "kriteriaRujukan": [
    { "linkId": "51947,69587", "text": "Terapi",          "type": "boolean", "item": null },
    { "linkId": "27038,44678", "text": "Tindakan Medis",  "type": "text",    "item": null },
    { "linkId": "2129,19769",  "text": "Upaya Diagnosis", "type": "boolean", "item": null }
  ],
  "JejaringWilayah": [
    { "linkId": "1", "text": "Jejaring wilayah rujukan", "type": "group", "item": [
      { "linkId": "1.1", "text": "Provinsi",       "type": "choice",
        "answerOption": [ { "valueCoding": { "code": "35", "display": "JAWA TIMUR" } } ] },
      { "linkId": "1.2", "text": "Kabupaten/Kota", "type": "choice",
        "answerOption": [ { "valueCoding": { "code": "3504", "display": "TULUNGAGUNG" } } ] }
    ] }
  ]
}</pre>
@endverbatim
<ul class="max-w-3xl mb-6 space-y-1 text-sm list-disc list-inside">
    <li><code>linkId</code> aslinya deretan angka berkoma dan berubah tiap diagnosa.</li>
    <li><code>JejaringWilayah</code> berbentuk Questionnaire FHIR; diratakan oleh
        <code>sisruteWilayahDariKriteria()</code> menjadi <code>propinsiList</code> + <code>kabupatenList</code>.</li>
    <li>Diagnosa terlalu umum sering tidak punya kriteria (<code>A02</code> kosong, <code>A02.9</code> jalan) —
        tampilkan saran itu ke petugas, jangan diam-diam menggantinya.</li>
</ul>

{{-- ── 2. GetFaskesRujukan ── --}}
<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">2. <code>Sisrute/GetFaskesRujukan</code></h2>
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Request</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{
  "kodeFaskesSatuSehat": "100006766",
  "kodeSubSpesialis": "94",
  "kodeSarana": "",
  "kodeDiagnosa": "J15.9",
  "estimasiRujuk": "04-09-2026",
  "kriteriaRujukan": { "item": [
    { "linkId": "25129,26260,28256", "text": "Tindakan Medis", "answer": [ { "valueString": "33.22" } ] }
  ] },
  "codeJejaringWilayah": {
    "kodePropinsi": "35", "namaPropinsi": "JAWA TIMUR",
    "kodeKabupaten": "3504", "namaKabupaten": "TULUNGAGUNG"
  },
  "encounter": { "reference": "Encounter/99895e29-a642-43fb-b5f1-26389800594b" }
}</pre>
@endverbatim
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Response (satu baris kandidat)</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{ "count": 17, "list": [
  {
    "kodeFaskesSatuSehat": "Organization/100026379",
    "kdppk": "1801R017",
    "nmppk": "RSUD CONTOH",
    "strataSatuSehat": "Dasar",
    "alamatPpk": "...", "telpPpk": "...",
    "kelas": "B", "nmkc": "TULUNGAGUNG",
    "distance": 4.57, "jadwal": null,
    "jmlRujuk": 0, "kapasitas": 0, "persentase": 0
  }
] }</pre>
@endverbatim
<ul class="max-w-3xl mb-6 space-y-1 text-sm list-disc list-inside">
    <li>Tampilkan lewat <code>RujukanKompetensiTampil::kandidatBaris()</code> — ia membuang prefix
        <code>Organization/</code>, menormalkan strata, dan menyaring jarak/waktu mustahil
        (<code>1.7976931348623E+308</code> berarti &ldquo;tak terhitung&rdquo;, bukan jarak).</li>
    <li><code>jadwal: null</code> berarti tidak diinformasikan, bukan tutup. <code>jmlRujuk</code>/<code>kapasitas</code>
        pernah dilaporkan tidak ter-update — jangan dipakai sebagai angka keras.</li>
</ul>

{{-- ── 3. postKunjungan ── --}}
<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">3. <code>Sisrute/postKunjungan</code></h2>
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Request — payload kunjungan PCare biasa + tiga tambahan</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{
  "noKunjungan": null, "noKartu": "0002084343849", "tglDaftar": "03-09-2026", "kdPoli": "001",
  "...": "... field kunjungan PCare seperti biasa (keluhan, TTV, anamnesa, terapi, dst.) ...",

  "kdStatusPulang": "4",
  "kdDokter": "207209",
  "kdDiag1": "N40",

  "rujukLanjut": {
    "tglEstRujuk": "04-09-2026",
    "kdppk": "1801R001",
    "subSpesialis": { "kdSubSpesialis1": "16", "kdSarana": "" },
    "khusus": null
  },

  "satuSehatRujukan": {
    "kodeFaskesSatuSehat": "100006775",
    "idPasienSatuSehat": "P20396196444",
    "kdppkSatuSehatTujuanRujukan": "100025592",
    "kdDokterSatuSehat": "10010886691",
    "encounter": { "reference": "9aec5072-38bf-4ab1-b411-33d2c213784e" },
    "patientInstruction": "Rujukan",
    "kriteriaRujukan": { "item": [
      { "linkId": "27244", "text": "Tindakan Medis", "answer": [ { "valueString": "60.29" } ] }
    ] },
    "keteranganRujukan": "Rujukan",
    "codeJejaringWilayah": {
      "kodePropinsi": "35", "namaPropinsi": "JAWA TIMUR",
      "kodeKabupaten": "3504", "namaKabupaten": "TULUNGAGUNG"
    }
  }
}</pre>
@endverbatim
<p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Response sukses</p>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">{
  "noKunjungan": "180105060826Y000004",
  "serviceRequestId": "42cf...",
  "noRujukanSatuSehat": "73711802608211001"
}</pre>
@endverbatim
<div
    class="max-w-3xl px-4 py-3 mb-6 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-900/50 dark:text-amber-200">
    <b>No. Rujukan PCare = <code>noKunjungan</code>.</b> Bila BPJS justru membalas
    <code>Gagal mendapatkan nomor Rujukan Satu Sehat</code>, resource ServiceRequest biasanya ikut dilampirkan di pesan
    dan rujukan <b>mungkin sudah terbentuk</b>. Nomor-nomornya ada di <code>identifier[]</code>:
    <code>referral-number-pcare</code>, <code>referral-number-satusehat</code>,
    <code>servicerequest/&lt;orgId&gt;</code>, dan <code>meta.tag</code> berisi trace-id.
    <code>PcareSisruteTrait::sisruteBacaNomorRujukan()</code> memeriksa kelimanya justru supaya kasus
    &ldquo;gagal tapi sudah terbentuk&rdquo; tidak hilang. <b>Jangan kirim ulang sebelum memeriksa itu.</b>
</div>

{{-- ── 4. deleteKunjungan ── --}}
<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">4. <code>Sisrute/deleteKunjungan</code></h2>
@verbatim
    <pre class="p-4 mb-3 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">DELETE {url}/Sisrute/deleteKunjungan

{ "noKunjungan": "180105060826Y000004" }</pre>
@endverbatim
<p class="max-w-3xl text-sm">
    Belum ada sampel resmi di berkas pembagian grup; yang pasti verb-nya <b>DELETE</b> dan penghapusannya menjalar
    sampai pendaftaran PCare. Bentuk body di atas mengikuti pola endpoint <code>kunjungan</code> PCare
    (identifikasi lewat <code>noKunjungan</code>) dan akan disesuaikan begitu katalog resminya terbit.
</p>
