# SQL Setup — Siklik Oracle Database

Folder ini berisi 2 **bundle SQL** yang dijalankan **manual** ke Oracle siklik
buat menyiapkan database supaya kompatibel dengan Laravel siklik-php82
(+ opsional fitur SatuSehat).

Semua bundle bersifat **idempotent** — aman di-run berkali-kali, masing-masing
section pakai existence check sebelum DDL.

---

## 🔗 Tahap 3 — FK relasi implisit & pembersihan skema (`skema-tahap3/`)

Dibuat `php artisan siklik:audit-skema` (laporan `docs/audit-skema.md`). Isi: `01_fk_implisit.sql` (FK untuk kolom yang
selama ini hanya relasi implisit + index kolom FK), `02_drop_objek_invalid.sql` (+ `_backup_objek_invalid.sql`),
`03_drop_sequence_tak_terpakai.sql`, `99_rollback.sql`. Jalankan lewat SQL*Plus berurutan setelah backup; di Oracle dev
01 & 02 sudah dijalankan 11 Sep 2026, 03 belum. Setelah eksekusi: `php artisan siklik:dok-tabel` + regenerasi dump `_dev/`.

## 🔤 Prefix tabel `SK` (sejak 11 Sep 2026)

Semua tabel/view bisnis kini berprefix `SKMST_ / SKTXN_ / SKACC_ / SKVIEW_` (huruf modul lama
`DI IM LB RS SC TK` dilebur). Bundle `install_bundle*.sql` di folder ini **sudah memakai nama baru**,
jadi untuk schema yang masih bernama lama urutannya: rename dulu, bundle kemudian.

- Skrip rename + README urutan eksekusi: `rename-siklik/` (dibuat `php artisan siklik:ddl-rename --prefix=SK`
  terhadap DB target; jalankan lewat SQL*Plus).
- Nama lama tetap hidup sebagai synonym untuk siklik-lite legacy; dicabut lewat `rename-siklik/05_drop_synonym_legacy.sql`
  setelah legacy pensiun.
- Peta lama→baru, modul, dan relasi antar tabel: `docs/struktur-tabel.md` atau menu **Sistem → Struktur Tabel**.
- Dump `_dev/columns_siklik.txt` & `_dev/fk_siklik.txt` sudah diregenerasi dari schema bernama baru.

## Prasyarat

- Database Oracle siklik sudah ada (host, port, service name, user/password sudah diset di `.env` siklik-php82).
- 103 tabel core siklik (`RSMST_*`, `RSTXN_*`, `TKMST_*`, `TKACC_*`, dll) sudah ada di schema `siklik`.
- Tabel Laravel sistem yang sudah ada: `USERS`, `MIGRATIONS`, `FAILED_JOBS`, `PASSWORD_RESET_TOKENS`, `PERSONAL_ACCESS_TOKENS`, plus 5 tabel Spatie permission.

---

## Bundle yang tersedia

### 🔧 + 🩺 `install_bundle.sql` — mandatory (Laravel system + klinik pratama)

| Section | Object | Idempotency |
|---------|--------|-------------|
| Laravel system | `SESSIONS`, `CACHE`, `CACHE_LOCKS`, `JOBS` (+ sequence & trigger), `JOB_BATCHES` | ✅ skip kalau sudah ada |
| Migrations marker | Insert ~18 entry ke `MIGRATIONS` biar `php artisan migrate` skip tabel core | ✅ skip per-row |
| `REF_BPJS_TABLE` | Cache reference BPJS PCare (alergi, kesadaran, prognosa, poli FKTP, pulang) | ✅ skip kalau sudah ada |
| `USERS.KASIR_ID` | Tambah/rename kolom `EMP_ID → KASIR_ID` (handle 4 kasus state) | ✅ aman re-run |
| `SKTXN_SOWHS` + view `SKVIEW_IOSTOCKWHS` | Stock opname warehouse + re-create view dgn UNION ALL block SO | ✅ Table skip kalau sudah ada (data opname preserved). View selalu di-recreate dgn `CREATE OR REPLACE` (grants ke DITOKOKU preserved). |
| `SKTXN_RJACCDOCS.DR_ID` | Tambah kolom + FK ke `SKMST_DOCTORS` | ✅ skip kalau sudah ada |

### 🌐 `install_bundle_satusehat.sql` — optional (SatuSehat / LOINC + SNOMED)

Run hanya kalau klinik mau aktifkan integrasi SatuSehat (kirim FHIR ke Kemenkes).

| Section | Object | Idempotency |
|---------|--------|-------------|
| SNOMED cache | `SKMST_SNOMED_CODES` + seed ~130 kode (condition / substance / procedure) | ✅ skip seed kalau table sudah ada datanya |
| LOINC cache | `SKMST_LOINC_CODES` + seed ~98 kode lab | ✅ skip seed kalau table sudah ada lab class rows |
| `SKMST_CLABITEMS` | Tambah 4 kolom (`LOINC_CODE`, `LOINC_DISPLAY`, `LOW_LIMIT_K`, `HIGH_LIMIT_K`) + index | ✅ per-kolom check |
| `SKMST_RADIOLOGIS` | Tambah 2 kolom (`LOINC_CODE`, `LOINC_DISPLAY`) + index + ~150 UPDATE mapping | ✅ per-kolom check; UPDATE inheren idempotent |
| LOINC radiologi seed | Insert ~62 kode RAD ke `SKMST_LOINC_CODES` | ✅ skip kalau RAD class rows sudah ada |
| `SKMST_CLABITEMS` LOINC mapping | ~150 UPDATE mapping | ✅ inheren idempotent |

> Untuk fitur SatuSehat aktif di app, butuh juga setup credentials di `.env` (`SATUSEHAT_*`).

### 🆕 `install_bundle_fitur_lanjutan.sql` — fitur lanjutan (Juni 2026)

Gabungan idempotent dari 4 file referensi (file aslinya tetap ada sbg dokumentasi:
`create_tkmst_signa_catatans.sql`, `create_penerimaan_non_medis.sql`,
`create_kartu_stock_non_medis.sql`, `alter_users_add_last_seen.sql`).

| Section | Object | Dipakai oleh | Idempotency |
|---------|--------|--------------|-------------|
| Signa catatan | `SKMST_SIGNA_CATATANS` + index | Master Catatan Signa + combobox e-resep | ✅ skip kalau sudah ada |
| Penerimaan non-medis | `SKMST_PRODUCTNONS`, `SKTXN_RCVHDRNONS`, `SKTXN_RCVDTLNONS`, `SKTXN_RCVPAYMENTNONS`, `SKTXN_CASHOUTHDRNONS`, `SKTXN_CASHOUTDTLNONS` + 5 sequence | Master Produk Non-Medis, Penerimaan Non-Medis, Pembayaran Hutang Non-Medis | ✅ skip per-object |
| Kartu stock non-medis | `SKTXN_SALDOAWALSTOCKSNON`, `SKTXN_SOWHSNON`, view `SKVIEW_IOSTOCKWHSNON` | Kartu Stock — Non-Medis | ✅ table skip; view selalu `CREATE OR REPLACE` |
| User tracking | `USERS.LAST_SEEN_AT` + `LAST_SEEN_ROUTE` | Sistem → User Online (middleware `TrackUserActivity`) | ✅ per-kolom check |

> Catatan stok non-medis: stok TUNGGAL di `SKMST_PRODUCTNONS.QTY_BOX` (tanpa
> lokasi/transfer). Tabel baru TANPA trigger legacy — `qty_box` di-update
> aplikasi (penerimaan & opname) dalam transaksi yang sama.

---

## Cara jalanin

### Pakai sqlplus (rekomendasi)

```bash
# Mandatory
sqlplus siklik/<pwd>@//<host>:1521/<service> @database/sql/install_bundle.sql

# Optional — SatuSehat
sqlplus siklik/<pwd>@//<host>:1521/<service> @database/sql/install_bundle_satusehat.sql

# Fitur lanjutan (signa e-resep, non-medis, user online)
sqlplus siklik/<pwd>@//<host>:1521/<service> @database/sql/install_bundle_fitur_lanjutan.sql
```

Bundle SatuSehat aman di-run setelah `install_bundle.sql` (atau independen, asal tabel core siklik `SKMST_CLABITEMS` & `SKMST_RADIOLOGIS` sudah ada).

### Pakai DBeaver / SQL Developer

Buka file → execute. Pastikan mode "Execute SQL Script" (`;` + `/` sebagai separator). Bundle support `SQLBLANKLINES ON` agar formatting blank-line tetap parsable.

### Deploy ke server klinik

Pakai script wrapper:
```bash
./scripts/deploy_sql_to_klinik.sh           # interactive konfirmasi
./scripts/deploy_sql_to_klinik.sh --yes     # skip konfirmasi
```
Default kirim 2 bundle + README ke `klinikmadinah@172.8.9.12:~/sql_deploy/`.

---

## Verify setelah jalan

```sql
-- Tabel sistem (harus ada 7 baris)
SELECT table_name FROM user_tables
WHERE table_name IN ('SESSIONS','CACHE','CACHE_LOCKS','JOBS','JOB_BATCHES',
                     'REF_BPJS_TABLE','SKTXN_SOWHS')
ORDER BY table_name;

-- Migrations count (harus minimal 18)
SELECT COUNT(*) FROM migrations;

-- USERS.KASIR_ID
SELECT column_name FROM user_tab_columns
WHERE table_name = 'USERS' AND column_name = 'KASIR_ID';

-- View opname valid + ada SO block
SELECT view_name, status FROM user_views WHERE view_name = 'SKVIEW_IOSTOCKWHS';
SELECT COUNT(*) FROM skview_iostockwhs WHERE txn_status = 'SO';

-- SKTXN_RJACCDOCS.DR_ID
SELECT column_name FROM user_tab_columns
WHERE table_name = 'SKTXN_RJACCDOCS' AND column_name = 'DR_ID';

-- (SatuSehat) Tabel cache + kolom mapping
SELECT table_name FROM user_tables
WHERE table_name IN ('SKMST_SNOMED_CODES','SKMST_LOINC_CODES');
SELECT column_name FROM user_tab_columns
WHERE table_name = 'SKMST_CLABITEMS'
  AND column_name IN ('LOINC_CODE','LOINC_DISPLAY','LOW_LIMIT_K','HIGH_LIMIT_K');
```

---

## Folder `_dev/` — bukan untuk server install

Subfolder `_dev/` berisi script introspection / debug untuk dev. **Jangan**
dijalankan ke server produksi sebagai bagian dari deployment.

| File | Fungsi |
|------|--------|
| `_dev/describe_master_tables.sql` | Dump struktur kolom + PK + FK + index dari semua master tables (`*MST_*`) supaya bisa diff dgn implementasi siklik-php82 |

---

## Catatan teknis

- **Reserved word `KEY`**: Kolom `KEY` di `CACHE` dan `CACHE_LOCKS` di-create dengan `"KEY"` (uppercase quoted) supaya match query yajra/oci8 driver. Lowercase `"key"` akan trigger ORA-00904.
- **Auto-increment**: `JOBS.id` pakai sequence `jobs_seq` + trigger `jobs_bi` (Oracle 11g style). Untuk Oracle 12c+ bisa pakai `GENERATED BY DEFAULT AS IDENTITY`.
- **Timestamp**: Kolom `last_activity`, `expiration`, `created_at`, dll di tabel sistem **bukan** Oracle DATE — itu UNIX epoch integer, jadi pakai `NUMBER(10)`.
- **SKTXN_SOWHS data preservation**: Bundle skip table create kalau sudah ada (data opname preserved). View `SKVIEW_IOSTOCKWHS` selalu di-recreate via `CREATE OR REPLACE FORCE VIEW` — grants ke DITOKOKU otomatis ter-preserve. Aman re-run di klinik existing.
- **Oracle 10g compat**: Sqlplus 10g default `SQLBLANKLINES OFF` — blank line dianggap statement terminator. Bundle pakai `SET SQLBLANKLINES ON` di awal supaya formatting view-with-UNION ALL aman.
