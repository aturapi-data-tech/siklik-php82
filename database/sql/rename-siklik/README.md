# Rename prefix tabel siklik → `SKMST_ / SKTXN_ / SKACC_ / SKVIEW_`

Dibuat otomatis oleh `php artisan siklik:ddl-rename --prefix=SK`.
Jangan edit berkas SQL di folder ini secara manual; ubah generator lalu jalankan ulang.

## Aturan penamaan

Huruf modul lama (`DI IM LB RS SC TK`) diganti `SK`, jenis objek (`MST TXN ACC VIEW`) tetap,
sisa nama tidak berubah. Contoh:

| Lama                         | Baru                         |
|------------------------------|------------------------------|
| DIMST_APPLICATIONS           | SKMST_APPLICATIONS           |
| DIMST_IDENTITASES            | SKMST_IDENTITASES            |
| DIMST_USERAPPLICATIONS       | SKMST_USERAPPLICATIONS       |
| DIMST_USERS                  | SKMST_USERS                  |
| IMMST_CONTENTS               | SKMST_CONTENTS               |
| IMMST_PRODUCTCONTENTS        | SKMST_PRODUCTCONTENTS        |

Constraint & index diberi nama baru berpola `AKAR_KOLOM_PK` / `AKAR_KOLOM_FK` / `AKAR_KOLOM_UK` /
`AKAR_KOLOM_IX`, dengan `AKAR` = nama tabel tanpa prefix (mis. `PASIENS_KEC_FK`). Nama dipadatkan
agar ≤ 30 karakter (batas Oracle 10g).

Peta lengkap: `peta_nama.csv`.

## Cakupan

| Objek      | Jumlah |
|------------|--------|
| Tabel      | 93 |
| View       | 40 |
| Constraint | 182 |
| Index      | 111 |

Tidak disentuh: tabel sistem Laravel/Spatie, `PASIEN`, `REF_BPJS_TABLE`, `REFERENSI_MOBILEJKN_BPJS`,
`WEB_LOG_STATUS`, `INSTALL_TOKOKU`, semua sequence, semua trigger.

## Urutan eksekusi

0. **Jalankan generator ini terhadap DB TARGET** (`DB_CONNECTION` mengarah ke schema yang akan di-rename)
   tepat sebelum eksekusi, lalu tinjau `peta_nama.csv`. Daftar objek dibaca dari data dictionary DB yang
   terhubung, jadi hasil dari DB lokal bisa kurang lengkap (mis. tabel fitur lanjutan `TKTXN_SOWHSNON`).
1. **Backup** schema (`expdp` atau minimal `exp`), lalu hentikan aplikasi yang menulis ke schema ini.
2. `01_rename_tabel_view.sql` — rename tabel & view. Setelah ini semua view INVALID.
3. `02_synonym_kompat_legacy.sql` — synonym nama lama → baru, lalu compile view.
   Dengan ini **siklik-lite legacy tetap jalan tanpa ubah kode**.
4. `03_recreate_view.sql` — recreate view dengan nama baru di dalam teksnya.
5. `04_rename_constraint_index.sql` — opsional, hanya kosmetik nama.
6. Deploy siklik-php82. Kode, `database/sql/install_bundle*.sql`, docs, dan skill sudah memakai nama baru
   sejak 11 Sep 2026, jadi versi itu **tidak jalan** di schema yang belum di-rename (ORA-00942).
   Bundle instalasi untuk schema baru dijalankan SETELAH rename ini.
7. **Nanti**, setelah legacy pensiun: `05_drop_synonym_legacy.sql`.

Kalau ada yang gagal di tengah: `99_rollback.sql` (pakai `WHENEVER SQLERROR CONTINUE`,
baris yang objeknya belum diubah memang akan error dan boleh diabaikan).

## Verifikasi

```sql
SELECT object_type, status, COUNT(*) FROM user_objects
 WHERE object_type IN ('TABLE','VIEW','SYNONYM') GROUP BY object_type, status;
SELECT table_name FROM user_tables WHERE REGEXP_LIKE(table_name, '^(DI|IM|LB|RS|SC|TK)(MST|TXN|ACC|VIEW)_');
-- keduanya: tidak ada INVALID, dan query kedua kosong
```

## Catatan Oracle 10g

- `ALTER TABLE ... RENAME TO` mempertahankan data, constraint, index, grant, dan FK dari tabel lain.
- `RENAME view TO ...` sah untuk view & synonym privat. Teks view TIDAK ikut berubah → wajib langkah 03.
- Sequence tidak diprefix dan dipakai lewat `.nextval` di kode, jadi sengaja tidak di-rename di tahap ini.