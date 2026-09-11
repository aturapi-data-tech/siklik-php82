{{-- Partial: bagian Pendahuluan & Aturan Nama. Dipanggil dari ⚡struktur-tabel.blade.php --}}
@php $r = $this->ringkasan; @endphp

<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">01 — Mulai</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Struktur Tabel siklik</h1>
<p class="max-w-3xl mb-4 text-sm leading-relaxed">
    Database siklik adalah schema Oracle 10g warisan dua aplikasi lama: <b>Tokoku</b> (prefix <code>TK</code>) dan
    <b>sirus</b> (prefix <code>RS</code>), ditambah lab (<code>LB</code>), dictionary aplikasi (<code>DI</code>),
    inventory medis (<code>IM</code>), dan jadwal (<code>SC</code>). Sejak <b>11 September 2026</b> semua huruf modul itu
    dilebur menjadi satu prefix program: <code>SK</code>. Halaman ini adalah peta bagi programmer: tabel mana yang
    di-rename, tabel masuk modul apa, dan bagaimana tabel saling terhubung.
</p>

<div class="grid grid-cols-2 gap-3 mb-6 md:grid-cols-5">
    @foreach ([['Tabel', $r['tabel']], ['View', $r['view']], ['Relasi FK', $r['relasi']], ['Relasi implisit', $r['implisit']], ['Objek di-rename', $r['rename']]] as [$label, $nilai])
        <div class="px-4 py-3 border border-gray-200 rounded-xl dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $nilai }}</div>
        </div>
    @endforeach
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Aturan nama</h2>
<p class="max-w-3xl mb-3 text-sm">
    Huruf modul lama diganti <code>SK</code>, jenis objek di tengah nama <b>dipertahankan</b>, sisa nama tidak diubah.
    Jadi <code>RSTXN_RJHDRS</code> menjadi <code>SKTXN_RJHDRS</code>, <code>TKMST_PRODUCTS</code> menjadi
    <code>SKMST_PRODUCTS</code>, <code>RSVIEW_RJKASIR</code> menjadi <code>SKVIEW_RJKASIR</code>.
</p>

<div class="grid grid-cols-1 gap-4 mb-6 lg:grid-cols-2">
    <div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">Prefix baru</div>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($this->prefixBaru() as $p => $ket)
                    <tr><td class="px-4 py-2 font-mono font-semibold whitespace-nowrap">{{ $p }}</td><td class="px-4 py-2">{{ $ket }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">Huruf modul lama yang dilebur</div>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($this->prefixLama() as $p => $ket)
                    <tr><td class="px-4 py-2 font-mono font-semibold whitespace-nowrap">{{ $p }}</td><td class="px-4 py-2">{{ $ket }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="px-4 py-3 mb-6 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-900/50 dark:text-amber-300">
    <b>Modul tidak lagi terbaca dari prefix.</b> Dulu <code>TK</code> berarti toko dan <code>RS</code> berarti klinik;
    sekarang pengelompokan modul ditentukan di <code>App\Support\Skema\ModulTabel</code>. Kalau membuat tabel baru,
    tambahkan pola namanya ke sana supaya muncul di modul yang benar di halaman ini.
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Yang sengaja tidak di-rename</h2>
<ul class="max-w-3xl mb-6 space-y-1 text-sm list-disc list-inside">
    <li>Tabel sistem Laravel & Spatie: <code>USERS</code>, <code>ROLES</code>, <code>PERMISSIONS</code>, <code>SESSIONS</code>, <code>CACHE</code>, <code>JOBS</code>, dst.</li>
    <li>Tabel BPJS & log: <code>PASIEN</code>, <code>REF_BPJS_TABLE</code>, <code>REFERENSI_MOBILEJKN_BPJS</code>, <code>WEB_LOG_STATUS</code>.</li>
    <li>Semua <b>sequence</b> (nama kriptik seperti <code>RR2_SEQ_1</code>) dan <b>trigger</b>: dipakai lewat <code>.nextval</code> di kode, tidak berprefix modul.</li>
</ul>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Kompatibilitas aplikasi lama</h2>
<p class="max-w-3xl text-sm">
    Setiap nama lama dibuatkan <b>synonym</b> ke nama baru (<code>CREATE SYNONYM RSMST_PASIENS FOR SKMST_PASIENS</code>),
    sehingga <b>siklik-lite</b> legacy tetap berjalan tanpa ubah kode. Kode siklik-php82 sendiri sudah memakai nama baru.
    Synonym dicabut (skrip 05) hanya setelah legacy pensiun.
</p>
