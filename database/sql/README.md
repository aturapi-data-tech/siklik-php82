# SQL Setup — Siklik Oracle Database

Folder ini berisi 2 **bundle SQL** yang dijalankan **manual** ke Oracle siklik
buat menyiapkan database supaya kompatibel dengan Laravel siklik-php82
(+ opsional fitur SatuSehat).

Semua bundle bersifat **idempotent** — aman di-run berkali-kali, masing-masing
section pakai existence check sebelum DDL.

---

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
| `TKTXN_SOWHS` + view `TKVIEW_IOSTOCKWHS` | Stock opname warehouse + re-create view dgn UNION ALL block SO | ✅ Table skip kalau sudah ada (data opname preserved). View selalu di-recreate dgn `CREATE OR REPLACE` (grants ke DITOKOKU preserved). |
| `RSTXN_RJACCDOCS.DR_ID` | Tambah kolom + FK ke `RSMST_DOCTORS` | ✅ skip kalau sudah ada |

### 🌐 `install_bundle_satusehat.sql` — optional (SatuSehat / LOINC + SNOMED)

Run hanya kalau klinik mau aktifkan integrasi SatuSehat (kirim FHIR ke Kemenkes).

| Section | Object | Idempotency |
|---------|--------|-------------|
| SNOMED cache | `RSMST_SNOMED_CODES` + seed ~130 kode (condition / substance / procedure) | ✅ skip seed kalau table sudah ada datanya |
| LOINC cache | `RSMST_LOINC_CODES` + seed ~98 kode lab | ✅ skip seed kalau table sudah ada lab class rows |
| `LBMST_CLABITEMS` | Tambah 4 kolom (`LOINC_CODE`, `LOINC_DISPLAY`, `LOW_LIMIT_K`, `HIGH_LIMIT_K`) + index | ✅ per-kolom check |
| `RSMST_RADIOLOGIS` | Tambah 2 kolom (`LOINC_CODE`, `LOINC_DISPLAY`) + index + ~150 UPDATE mapping | ✅ per-kolom check; UPDATE inheren idempotent |
| LOINC radiologi seed | Insert ~62 kode RAD ke `RSMST_LOINC_CODES` | ✅ skip kalau RAD class rows sudah ada |
| `LBMST_CLABITEMS` LOINC mapping | ~150 UPDATE mapping | ✅ inheren idempotent |

> Untuk fitur SatuSehat aktif di app, butuh juga setup credentials di `.env` (`SATUSEHAT_*`).

### 🆕 `install_bundle_fitur_lanjutan.sql` — fitur lanjutan (Juni 2026)

Gabungan idempotent dari 4 file referensi (file aslinya tetap ada sbg dokumentasi:
`create_tkmst_signa_catatans.sql`, `create_penerimaan_non_medis.sql`,
`create_kartu_stock_non_medis.sql`, `alter_users_add_last_seen.sql`).

| Section | Object | Dipakai oleh | Idempotency |
|---------|--------|--------------|-------------|
| Signa catatan | `TKMST_SIGNA_CATATANS` + index | Master Catatan Signa + combobox e-resep | ✅ skip kalau sudah ada |
| Penerimaan non-medis | `TKMST_PRODUCTNONS`, `TKTXN_RCVHDRNONS`, `TKTXN_RCVDTLNONS`, `TKTXN_RCVPAYMENTNONS`, `TKTXN_CASHOUTHDRNONS`, `TKTXN_CASHOUTDTLNONS` + 5 sequence | Master Produk Non-Medis, Penerimaan Non-Medis, Pembayaran Hutang Non-Medis | ✅ skip per-object |
| Kartu stock non-medis | `TKTXN_SALDOAWALSTOCKSNON`, `TKTXN_SOWHSNON`, view `TKVIEW_IOSTOCKWHSNON` | Kartu Stock — Non-Medis | ✅ table skip; view selalu `CREATE OR REPLACE` |
| User tracking | `USERS.LAST_SEEN_AT` + `LAST_SEEN_ROUTE` | Sistem → User Online (middleware `TrackUserActivity`) | ✅ per-kolom check |

> Catatan stok non-medis: stok TUNGGAL di `TKMST_PRODUCTNONS.QTY_BOX` (tanpa
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

Bundle SatuSehat aman di-run setelah `install_bundle.sql` (atau independen, asal tabel core siklik `LBMST_CLABITEMS` & `RSMST_RADIOLOGIS` sudah ada).

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
                     'REF_BPJS_TABLE','TKTXN_SOWHS')
ORDER BY table_name;

-- Migrations count (harus minimal 18)
SELECT COUNT(*) FROM migrations;

-- USERS.KASIR_ID
SELECT column_name FROM user_tab_columns
WHERE table_name = 'USERS' AND column_name = 'KASIR_ID';

-- View opname valid + ada SO block
SELECT view_name, status FROM user_views WHERE view_name = 'TKVIEW_IOSTOCKWHS';
SELECT COUNT(*) FROM tkview_iostockwhs WHERE txn_status = 'SO';

-- RSTXN_RJACCDOCS.DR_ID
SELECT column_name FROM user_tab_columns
WHERE table_name = 'RSTXN_RJACCDOCS' AND column_name = 'DR_ID';

-- (SatuSehat) Tabel cache + kolom mapping
SELECT table_name FROM user_tables
WHERE table_name IN ('RSMST_SNOMED_CODES','RSMST_LOINC_CODES');
SELECT column_name FROM user_tab_columns
WHERE table_name = 'LBMST_CLABITEMS'
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
- **TKTXN_SOWHS data preservation**: Bundle skip table create kalau sudah ada (data opname preserved). View `TKVIEW_IOSTOCKWHS` selalu di-recreate via `CREATE OR REPLACE FORCE VIEW` — grants ke DITOKOKU otomatis ter-preserve. Aman re-run di klinik existing.
- **Oracle 10g compat**: Sqlplus 10g default `SQLBLANKLINES OFF` — blank line dianggap statement terminator. Bundle pakai `SET SQLBLANKLINES ON` di awal supaya formatting view-with-UNION ALL aman.
