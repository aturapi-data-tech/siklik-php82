{{-- Partial: Cara Eksekusi Rename — urutan skrip database/sql/rename-siklik/ --}}
<div class="mb-2 text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">05 — Operasional</div>
<h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Cara Eksekusi Rename di Server Lain</h1>
<p class="max-w-3xl mb-4 text-sm">
    Rename sudah dijalankan di Oracle pengembangan pada 11 September 2026. Untuk server lain (produksi, cadangan),
    ikuti urutan ini. Semua skrip dibuat generator, <b>jangan diedit manual</b>: ubah generator lalu jalankan ulang.
</p>

<ol class="max-w-3xl mb-6 space-y-3 text-sm list-decimal list-inside">
    <li><b>Arahkan koneksi ke DB target</b> (<code>SIKLIK_DB_*</code> di <code>.env</code>), lalu jalankan generator.
        Daftar objek dibaca dari data dictionary DB yang terhubung, jadi hasil dari DB lain bisa kurang lengkap.
        <pre class="p-3 mt-2 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">php artisan siklik:ddl-rename --prefix=SK
# output: database/sql/rename-siklik/  → tinjau peta_nama.csv</pre></li>
    <li><b>Backup</b> schema dan hentikan aplikasi yang menulis.
        <pre class="p-3 mt-2 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">exp siklik/&lt;pwd&gt;@//host:1521/orcl file=backup_sebelum_rename.dmp owner=siklik statistics=none</pre></li>
    <li><code>01_rename_tabel_view.sql</code> — rename tabel & view. Setelah ini semua view INVALID (teksnya masih nama lama).</li>
    <li><code>02_synonym_kompat_legacy.sql</code> — synonym nama lama → baru + compile view. Sejak sini siklik-lite legacy sudah jalan lagi.</li>
    <li><code>03_recreate_view.sql</code> — buat ulang view dengan nama baru di dalam teksnya (melepas ketergantungan pada synonym).
        Kalau langkah 01 sudah lewat dan berkas 03 perlu dibuat ulang, pakai <code>--hanya-view</code>.
        <pre class="p-3 mt-2 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-lg">php artisan siklik:ddl-rename --prefix=SK --hanya-view</pre></li>
    <li><code>04_rename_constraint_index.sql</code> — opsional, hanya merapikan nama constraint/index ke pola <code>AKAR_KOLOM_PK/FK/UK/IX</code>.</li>
    <li>Deploy siklik-php82 versi yang sudah memakai nama baru, lalu jalankan <code>install_bundle*.sql</code> bila schema-nya baru.</li>
    <li><b>Nanti</b>, setelah legacy pensiun: <code>05_drop_synonym_legacy.sql</code>.</li>
</ol>

<div class="max-w-3xl mb-6 overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900 dark:text-gray-400">Menjalankan skrip</div>
    <pre class="p-4 overflow-x-auto text-xs text-gray-100 bg-gray-900">cd database/sql/rename-siklik
sqlplus -S -L siklik/&lt;pwd&gt;@//127.0.0.1:1521/orcl @01_rename_tabel_view.sql
sqlplus -S -L siklik/&lt;pwd&gt;@//127.0.0.1:1521/orcl @02_synonym_kompat_legacy.sql
sqlplus -S -L siklik/&lt;pwd&gt;@//127.0.0.1:1521/orcl @03_recreate_view.sql
sqlplus -S -L siklik/&lt;pwd&gt;@//127.0.0.1:1521/orcl @04_rename_constraint_index.sql</pre>
    <div class="px-4 py-2 text-xs text-gray-600 border-t border-gray-200 dark:border-gray-700 dark:text-gray-300">
        Pakai <b>SQL*Plus</b>, bukan DBeaver: skrip 03 dan 99 memakai terminator <code>/</code> karena beberapa view
        (mis. <code>SKVIEW_ACCOUNTS</code>) berisi komentar <code>--</code> yang akan menelan <code>;</code>.
    </div>
</div>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Verifikasi sesudah eksekusi</h2>
<pre class="max-w-3xl p-4 mb-6 overflow-x-auto text-xs text-gray-100 bg-gray-900 rounded-xl">-- tidak boleh ada tabel berpola lama
SELECT table_name FROM user_tables
 WHERE REGEXP_LIKE(table_name, '^(DI|IM|LB|RS|SC|TK)(MST|TXN|ACC|VIEW)_');

-- semua view VALID, synonym boleh sempat INVALID (valid lagi saat dipakai)
SELECT object_type, status, COUNT(*) FROM user_objects
 WHERE object_type IN ('TABLE','VIEW','SYNONYM') GROUP BY object_type, status;

-- tidak ada view yang masih bergantung synonym
SELECT name, referenced_name FROM user_dependencies
 WHERE type = 'VIEW' AND referenced_type = 'SYNONYM';</pre>

<h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Kalau gagal di tengah</h2>
<p class="max-w-3xl mb-2 text-sm">
    <code>99_rollback.sql</code> mengembalikan semua nama (drop synonym → constraint/index → view → tabel → teks view asli).
    Skrip ini memakai <code>WHENEVER SQLERROR CONTINUE</code>, jadi baris untuk objek yang belum sempat diubah memang
    akan error dan boleh diabaikan.
</p>
<p class="max-w-3xl text-sm">
    Catatan pengalaman 11/09/2026: langkah 03 versi awal gagal <code>ORA-00998</code> karena teks view di dictionary tidak
    menyimpan daftar kolom, sedangkan view lama dibuat dengan kolom eksplisit. Generator sekarang selalu menulis
    <code>CREATE OR REPLACE VIEW nama ("KOLOM1", "KOLOM2", …) AS …</code>.
</p>
