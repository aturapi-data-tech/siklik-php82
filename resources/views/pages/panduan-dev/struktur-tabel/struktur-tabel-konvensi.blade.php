{{-- Partial: Konvensi Tabel Baru — aturan menambah tabel/kolom/constraint setelah rename. --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">06 — Operasional</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Konvensi Tabel Baru</h1>
<p class="max-w-3xl mb-4 text-sm">
    Aturan saat menambah tabel, kolom, atau constraint, supaya schema tetap seragam dan halaman ini tetap membacanya dengan benar.
</p>

<div class="max-w-3xl space-y-4 text-sm">
    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">1. Nama tabel</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>Prefix jenis: <code>SKMST_</code> master, <code>SKTXN_</code> transaksi, <code>SKACC_</code> akuntansi, <code>SKVIEW_</code> view.</li>
            <li>Nama jamak bahasa Inggris seperti tabel lama (<code>SKMST_PASIENS</code>, <code>SKTXN_RJHDRS</code>); huruf besar; maksimal 30 karakter (Oracle 10g).</li>
            <li>Header/detail transaksi: <code>…HDRS</code> / <code>…DTLS</code>.</li>
            <li>Tambahkan pola nama ke <code>App\Support\Skema\ModulTabel</code> supaya masuk modul yang benar.</li>
        </ul>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">2. Constraint & index</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>PK: <code>AKAR_PK</code>, FK: <code>AKAR_KOLOM_FK</code>, unik: <code>AKAR_KOLOM_UK</code>, index: <code>AKAR_KOLOM_IX</code>. <code>AKAR</code> = nama tabel tanpa prefix, akhiran <code>_ID</code> kolom dibuang.</li>
            <li>Contoh: <code>RJHDRS_PK</code>, <code>RJHDRS_KLAIM_FK</code>, <code>CHECKUPHDRS_KASIR_FK</code>, <code>PASIENS_REG_NAME_IX</code>.</li>
            <li>Deklarasikan FK bila induknya jelas. Relasi implisit (tanpa FK) hanya untuk data warisan yang belum bersih.</li>
        </ul>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">3. Kolom & tipe</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>Kunci: <code>NAMA_ID</code> untuk PK/FK, <code>…_NO</code> untuk nomor transaksi ber-sequence.</li>
            <li>Tanggal transaksi: <code>DATE</code>; epoch <code>NUMBER(10)</code> hanya untuk tabel sistem Laravel.</li>
            <li>Data dokumen per kunjungan yang bentuknya berubah-ubah: satu kolom CLOB JSON (pola <code>DATADAFTARPOLIRJ_JSON</code>), bukan puluhan kolom datar. Oracle 10g tidak punya <code>JSON_VALUE</code>, filter JSON hanya lewat <code>INSTR</code>.</li>
            <li>Hindari nama kolom kata kunci Oracle (<code>KEY</code>, <code>LEVEL</code>, <code>COMMENT</code>).</li>
        </ul>
    </div>

    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
        <div class="mb-1 font-semibold text-gray-900 dark:text-white">4. Cara deploy perubahan schema</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>Tidak ada migration Laravel untuk tabel bisnis. Perubahan ditulis sebagai SQL idempoten di <code>database/sql/</code> (lihat <code>README.md</code> di sana) dan dijalankan lewat SQL*Plus.</li>
            <li>Sequence baru: <code>NAMA_SEQ</code>, dipakai lewat <code>.nextval</code> di kode; Oracle 10g tidak punya identity column.</li>
            <li>Setelah deploy, buka halaman ini untuk memastikan tabel muncul di modul yang benar, lalu jalankan <code>php artisan siklik:dok-tabel</code> supaya <code>docs/struktur-tabel.md</code> ikut baru.</li>
        </ul>
    </div>

    <div class="p-4 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="mb-1 font-semibold">Jebakan Oracle yang sering kena</div>
        <ul class="space-y-1 list-disc list-inside">
            <li>Nama lama masih bisa dipakai lewat synonym, tetapi <b>kode baru wajib nama SK</b>. Jangan campur.</li>
            <li>Kolom mixed-case (dibuat dengan tanda kutip) harus selalu dikutip; lihat skill <code>oracle-quirks</code>.</li>
            <li><code>ORA-02292</code> saat hapus master = masih dirujuk tabel transaksi; tangkap dan beri pesan ramah (pola <code>x-action-delete</code>).</li>
        </ul>
    </div>
</div>
