# Audit Skema Oracle siklik — Tahap 3

Dibuat otomatis `php artisan siklik:audit-skema` pada 11/09/2026 08:42 dari DB yang terhubung. DDL: `database/sql/skema-tahap3/`.

> **Status eksekusi (Oracle dev 127.0.0.1/orcl, 11 Sep 2026):** `01_fk_implisit.sql` ✅ (39 FK: 36 VALIDATE + 3 NOVALIDATE, 42 index),
> `02_drop_objek_invalid.sql` ✅ (27 objek PL/SQL warisan bridging RS/APEX di-drop; backup DDL di `_backup_objek_invalid.sql`;
> 4 objek lain pulih hanya dengan COMPILE — ADD_NUMBERS, ORACLE_TERBILANG, TK_BACKUP_DB, trigger USERS_ID_TRG),
> `03_drop_sequence_tak_terpakai.sql` ✅ (43 sequence yang belum pernah dipakai / sisa skema contoh DEMO_ di-drop setelah ditinjau;
> rollback CREATE ulang ada di `99_rollback.sql`; tersisa 21 sequence).
> Laporan di bawah adalah keadaan SEBELUM eksekusi. Jalankan ulang `php artisan siklik:audit-skema` untuk keadaan terkini.

## 1. Relasi implisit → FK

| Anak.kolom | Induk | Tipe | Baris | Yatim | Keputusan | Constraint | Index baru |
|---|---|---|---|---|---|---|---|
| `INSTALL_TOKOKU.ID_EX` | `SKMST_EX_IMS.ID_EX` | NUMBER | 0 | 0 | **LEWATI** — tabel warisan di luar aplikasi | `-` | - |
| `SKACC_CARABAYARS.ACC_ID` | `SKACC_ACCOUNTSES.ACC_ID` | VARCHAR2 | 5 | 0 | **VALIDATE** — semua baris cocok | `CARABAYARS_ACC_FK` | `CARABAYARS_ACC_IX` |
| `SKMST_ACCDOCOTHERS.ACCDOC_ID` | `SKMST_ACCDOCS.ACCDOC_ID` | VARCHAR2 | 1 | 1 | **LEWATI** — SEMUA baris yatim — kemungkinan bukan relasi nyata, periksa manual | `-` | - |
| `SKMST_ACCDOCOTHERS.OTHER_ID` | `SKMST_OTHERS.OTHER_ID` | VARCHAR2 | 1 | 0 | **VALIDATE** — semua baris cocok | `ACCDOCOTHERS_OTHER_FK` | `ACCDOCOTHERS_OTHER_IX` |
| `SKMST_ACCDOCPRODUCTS.ACCDOC_ID` | `SKMST_ACCDOCS.ACCDOC_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `ACCDOCPRODUCTS_ACCDOC_FK` | `ACCDOCPRODUCTS_ACCDOC_IX` |
| `SKMST_ACTEPRODS.ACTE_ID` | `SKMST_ACTEMPS.ACTE_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `ACTEPRODS_ACTE_FK` | `ACTEPRODS_ACTE_IX` |
| `SKMST_ACTPAROTHERS.OTHER_ID` | `SKMST_OTHERS.OTHER_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `ACTPAROTHERS_OTHER_FK` | `ACTPAROTHERS_OTHER_IX` |
| `SKMST_ACTPAROTHERS.PACT_ID` | `SKMST_ACTPARAMEDICS.PACT_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `ACTPAROTHERS_PACT_FK` | `ACTPAROTHERS_PACT_IX` |
| `SKMST_ACTPARPRODUCTS.PACT_ID` | `SKMST_ACTPARAMEDICS.PACT_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `ACTPARPRODUCTS_PACT_FK` | `ACTPARPRODUCTS_PACT_IX` |
| `SKMST_CLABITEMS.LOINC_CODE` | `SKMST_LOINC_CODES.LOINC_CODE` | VARCHAR2 | 108 | 1 | **NOVALIDATE** — ada baris yatim, hanya baris baru yang dijaga | `CLABITEMS_LOINC_CODE_FK` | - |
| `SKMST_CUSTOMERS.PROV_ID` | `SKMST_PROVS.PROV_ID` | VARCHAR2 | 7 | 0 | **VALIDATE** — semua baris cocok | `CUSTOMERS_PROV_FK` | `CUSTOMERS_PROV_IX` |
| `SKMST_PRODUCTCONTENTS.UOM_ID` | `SKMST_UOMS.UOM_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `PRODUCTCONTENTS_UOM_FK` | - |
| `SKMST_PRODUCTNONS.UOM_ID` | `SKMST_UOMS.UOM_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `PRODUCTNONS_UOM_FK` | `PRODUCTNONS_UOM_IX` |
| `SKMST_RADIOLOGIS.LOINC_CODE` | `SKMST_LOINC_CODES.LOINC_CODE` | VARCHAR2 | 127 | 0 | **VALIDATE** — semua baris cocok | `RADIOLOGIS_LOINC_CODE_FK` | - |
| `SKMST_SCPOLIS.DAY_ID` | `SKMST_SCDAYS.DAY_ID` | NUMBER | 0 | 0 | **VALIDATE** — semua baris cocok | `SCPOLIS_DAY_FK` | `SCPOLIS_DAY_IX` |
| `SKMST_SCPOLIS.DR_ID` | `SKMST_DOCTORS.DR_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `SCPOLIS_DR_FK` | `SCPOLIS_DR_IX` |
| `SKMST_SCPOLIS.POLI_ID` | `SKMST_POLIS.POLI_ID` | NUMBER | 0 | 0 | **VALIDATE** — semua baris cocok | `SCPOLIS_POLI_FK` | - |
| `SKTXN_CASHOUTHDRNONS.CB_ID` | `SKACC_CARABAYARS.CB_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `CASHOUTHDRNONS_CB_FK` | `CASHOUTHDRNONS_CB_IX` |
| `SKTXN_CASHOUTHDRNONS.KASIR_ID` | `SKMST_KASIRS.KASIR_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `CASHOUTHDRNONS_KASIR_FK` | `CASHOUTHDRNONS_KASIR_IX` |
| `SKTXN_CASHOUTHDRNONS.SUPP_ID` | `SKMST_SUPPLIERS.SUPP_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `CASHOUTHDRNONS_SUPP_FK` | `CASHOUTHDRNONS_SUPP_IX` |
| `SKTXN_CHECKUPDTLS.CHECKUP_NO` | `SKTXN_CHECKUPHDRS.CHECKUP_NO` | NUMBER | 5825 | 0 | **VALIDATE** — semua baris cocok | `CHECKUPDTLS_CHECKUP_NO_FK` | - |
| `SKTXN_CHECKUPHDRS.REG_NO` | `SKMST_PASIENS.REG_NO` | VARCHAR2 | 225 | 0 | **VALIDATE** — semua baris cocok | `CHECKUPHDRS_REG_NO_FK` | - |
| `SKTXN_CHECKUPOBATS.CHECKUP_NO` | `SKTXN_CHECKUPHDRS.CHECKUP_NO` | NUMBER | 6 | 0 | **VALIDATE** — semua baris cocok | `CHECKUPOBATS_CHECKUP_NO_FK` | - |
| `SKTXN_RCVHDRNONS.CB_ID` | `SKACC_CARABAYARS.CB_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `RCVHDRNONS_CB_FK` | `RCVHDRNONS_CB_IX` |
| `SKTXN_RCVHDRNONS.KASIR_ID` | `SKMST_KASIRS.KASIR_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `RCVHDRNONS_KASIR_FK` | `RCVHDRNONS_KASIR_IX` |
| `SKTXN_RCVHDRNONS.SUPP_ID` | `SKMST_SUPPLIERS.SUPP_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `RCVHDRNONS_SUPP_FK` | `RCVHDRNONS_SUPP_IX` |
| `SKTXN_RJACCDOCS.ACCDOC_ID` | `SKMST_ACCDOCS.ACCDOC_ID` | VARCHAR2 | 358 | 1 | **NOVALIDATE** — ada baris yatim, hanya baris baru yang dijaga | `RJACCDOCS_ACCDOC_FK` | - |
| `SKTXN_RJACCDOCS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 358 | 0 | **VALIDATE** — semua baris cocok | `RJACCDOCS_RJ_NO_FK` | - |
| `SKTXN_RJACTEMPS.ACTE_ID` | `SKMST_ACTEMPS.ACTE_ID` | VARCHAR2 | 8335 | 0 | **VALIDATE** — semua baris cocok | `RJACTEMPS_ACTE_FK` | - |
| `SKTXN_RJACTEMPS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 8335 | 0 | **VALIDATE** — semua baris cocok | `RJACTEMPS_RJ_NO_FK` | - |
| `SKTXN_RJDTLS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 30328 | 0 | **VALIDATE** — semua baris cocok | `RJDTLS_RJ_NO_FK` | - |
| `SKTXN_RJHDRS.REG_NO` | `SKMST_PASIENS.REG_NO` | VARCHAR2 | 24001 | 0 | **VALIDATE** — semua baris cocok | `RJHDRS_REG_NO_FK` | - |
| `SKTXN_RJLABS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 203 | 0 | **VALIDATE** — semua baris cocok | `RJLABS_RJ_NO_FK` | - |
| `SKTXN_RJOBATRACIKANS.ACTE_DTL` | `SKTXN_RJACTEMPS.ACTE_DTL` | NUMBER | 0 | 0 | **VALIDATE** — semua baris cocok | `RJOBATRACIKANS_ACTE_DTL_FK` | `RJOBATRACIKANS_ACTE_DTL_IX` |
| `SKTXN_RJOBATRACIKANS.CATATAN` | `SKMST_SIGNA_CATATANS.CATATAN` | VARCHAR2 | 0 | 0 | **LEWATI** — teks signa bebas; SKMST_SIGNA_CATATANS hanya LOV saran | `-` | - |
| `SKTXN_RJOBATRACIKANS.PACT_DTL` | `SKTXN_RJACTPARAMS.PACT_DTL` | NUMBER | 0 | 0 | **VALIDATE** — semua baris cocok | `RJOBATRACIKANS_PACT_DTL_FK` | `RJOBATRACIKANS_PACT_DTL_IX` |
| `SKTXN_RJOBATRACIKANS.RJHN_DTL` | `SKTXN_RJACCDOCS.RJHN_DTL` | NUMBER | 0 | 0 | **VALIDATE** — semua baris cocok | `RJOBATRACIKANS_RJHN_DTL_FK` | `RJOBATRACIKANS_RJHN_DTL_IX` |
| `SKTXN_RJOBATRACIKANS.RJOBAT_DTL` | `SKTXN_RJOBATS.RJOBAT_DTL` | NUMBER | 10518 | 48 | **NOVALIDATE** — ada baris yatim, hanya baris baru yang dijaga | `RJOBATRACIKANS_RJOBAT_DTL_FK` | `RJOBATRACIKANS_RJOBAT_DTL_IX` |
| `SKTXN_RJOBATRACIKANS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 10518 | 0 | **VALIDATE** — semua baris cocok | `RJOBATRACIKANS_RJ_NO_FK` | `RJOBATRACIKANS_RJ_NO_IX` |
| `SKTXN_RJOBATS.RJ_NO` | `SKTXN_RJHDRS.RJ_NO` | NUMBER | 64851 | 0 | **VALIDATE** — semua baris cocok | `RJOBATS_RJ_NO_FK` | - |
| `SKTXN_SLSKIRIMS.PROV_ID` | `SKMST_PROVS.PROV_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `SLSKIRIMS_PROV_FK` | `SLSKIRIMS_PROV_IX` |
| `SKTXN_SOWHSNON.KASIR_ID` | `SKMST_KASIRS.KASIR_ID` | VARCHAR2 | 0 | 0 | **VALIDATE** — semua baris cocok | `SOWHSNON_KASIR_FK` | `SOWHSNON_KASIR_IX` |

## 2. FK terdeklarasi tanpa index → index baru

| Tabel.kolom | Constraint | Index |
|---|---|---|
| `SKTXN_CASHOUTDTLNONS.CASHOUT_NO` | `FK_CODTLNONS_HDR` | `CASHOUTDTLNONS_CASHOUT_NO_IX` |
| `SKTXN_CASHOUTDTLS.CASHOUT_NO` | `CASHOUTDTLS_CASHOUT_NO_FK` | `CASHOUTDTLS_CASHOUT_NO_IX` |
| `SKTXN_CASHOUTDTLS.RCV_NO` | `CASHOUTDTLS_RCV_NO_FK` | `CASHOUTDTLS_RCV_NO_IX` |
| `SKTXN_CASHOUTHDRS.CB_ID` | `CASHOUTHDRS_CB_FK` | `CASHOUTHDRS_CB_IX` |
| `SKTXN_CASHOUTHDRS.KASIR_ID` | `CASHOUTHDRS_KASIR_FK` | `CASHOUTHDRS_KASIR_IX` |
| `SKTXN_CASHOUTHDRS.SUPP_ID` | `CASHOUTHDRS_SUPP_FK` | `CASHOUTHDRS_SUPP_IX` |
| `SKTXN_RCVDTLNONS.PRODUCT_ID` | `FK_RCVDTLNONS_PRD` | `RCVDTLNONS_PRODUCT_IX` |
| `SKTXN_RCVDTLS.PRODUCT_ID` | `RCVDTLS_PRODUCT_FK` | `RCVDTLS_PRODUCT_IX` |
| `SKTXN_RCVDTLS.RCV_NO` | `RCVDTLS_RCV_NO_FK` | `RCVDTLS_RCV_NO_IX` |
| `SKTXN_RCVHDRS.CB_ID` | `RCVHDRS_CB_FK` | `RCVHDRS_CB_IX` |
| `SKTXN_RCVHDRS.KASIR_ID` | `RCVHDRS_KASIR_FK` | `RCVHDRS_KASIR_IX` |
| `SKTXN_RCVHDRS.SUPP_ID` | `RCVHDRS_SUPP_FK` | `RCVHDRS_SUPP_IX` |
| `SKTXN_RCVPAYMENTNONS.RCV_NO` | `FK_RCVPAYNONS_HDR` | `RCVPAYMENTNONS_RCV_NO_IX` |
| `SKTXN_RCVPAYMENTS.RCV_NO` | `RCVPAYMENTS_RCV_NO_FK` | `RCVPAYMENTS_RCV_NO_IX` |
| `SKTXN_RJACCDOCS.DR_ID` | `RJACCDOCS_DR_FK` | `RJACCDOCS_DR_IX` |
| `SKTXN_RJHDRS.KASIR_ID` | `RJHDRS_KASIR_FK` | `RJHDRS_KASIR_IX` |
| `SKTXN_SALDOAWALSTOCKSNON.PRODUCT_ID` | `FK_SALDONON_PRD` | `SALDOAWALSTOCKSNON_PRODUCT_IX` |
| `SKTXN_SOWHS.KASIR_ID` | `SOWHS_KASIR_FK` | `SOWHS_KASIR_IX` |

## 3. Tabel tanpa primary key (laporan saja)

- `INSTALL_TOKOKU` — Lain-lain
- `PASIEN` — BPJS & Log
- `REFERENSI_MOBILEJKN_BPJS` — BPJS & Log
- `SKMST_ACCDOCOTHERS` — Tarif & Jasa
- `SKMST_ACCDOCPRODUCTS` — Tarif & Jasa
- `SKMST_ACTEPRODS` — Tarif & Jasa
- `SKMST_ACTPAROTHERS` — Tarif & Jasa
- `SKMST_ACTPARPRODUCTS` — Tarif & Jasa
- `SKMST_SCPOLIS` — Pasien & Klinik
- `SKTXN_RJOBATRACIKANS` — Rawat Jalan
- `SKTXN_SHIFTCTLS` — Rawat Jalan
- `WEB_LOG_STATUS` — BPJS & Log

## 4. Objek PL/SQL INVALID → drop (backup di `_backup_objek_invalid.sql`)

- FUNCTION `CUSTOM_AUTH` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_AREFHAPUSKMR` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_AREFKELAS` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_AREFTAMBAHKMR` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_AREFUPDATEKMR` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_CARI_KTSP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_CARI_NIK` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_CARI_NORUJUKAN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_CARI_NORUJUKANNOKA` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_CARI_SEP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_DELETERUJUKAN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_DELETESEP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_DIAGNOSA` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_DPJP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_FASKES` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_INSERTRUJUKAN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_INSERTSEP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_INSERT_TB` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_KABUPATEN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_KECAMATAN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_POLI` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_PROPINSI` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_RIWAYATPESERTA` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_UPDATERUJUKAN` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_UPDATESEP` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `BRPROC_UPDATETGLPULANG` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)
- PROCEDURE `CUSTOM_IMAGE_DISPLAY` — **DROP** (INVALID, tidak bisa compile, tidak dirujuk kode)

## 5. Sequence

| Sequence | last_number | Dipakai oleh | Keputusan |
|---|---|---|---|
| `AA9_SEQ` | 1 | - | **DROP** |
| `AB1_SEQ` | 21 | - | pertahankan — pernah dipakai, tidak dirujuk lagi, tinjau manual |
| `ACQ` | 1 | - | **DROP** |
| `ACTXNASSETDTL_SEQ` | 1 | - | **DROP** |
| `AUTOCUSTOMER_SEQ` | 1 | - | **DROP** |
| `AUTOPRODUCT_SEQ` | 1 | - | **DROP** |
| `AUTOSUPPLIER_SEQ` | 1 | - | **DROP** |
| `CASHIN_SEQ` | 1 | - | **DROP** |
| `CASHOUTNON_SEQ` | 1 | siklik-php82 | pertahankan |
| `CASHOUT_SEQ` | 61 | siklik-php82 | pertahankan |
| `CIDTL_SEQ` | 1 | - | **DROP** |
| `CODTLNON_SEQ` | 1 | siklik-php82 | pertahankan |
| `CODTL_SEQ` | 81 | siklik-php82 | pertahankan |
| `DEBTDTL_SEQ` | 1 | - | **DROP** |
| `DEBTHDR_SEQ` | 1 | - | **DROP** |
| `DEMO_CUST_SEQ` | 21 | - | **DROP** |
| `DEMO_IMAGES_SEQ` | 11 | - | **DROP** |
| `DEMO_ORDER_ITEMS_SEQ` | 21 | - | **DROP** |
| `DEMO_ORD_SEQ` | 11 | - | **DROP** |
| `DEMO_PROD_SEQ` | 187282 | PL/SQL | pertahankan |
| `DEMO_USERS_SEQ` | 21 | - | **DROP** |
| `FAILED_JOBS_ID_SEQ` | 1 | PL/SQL, Laravel | pertahankan |
| `ID_EX_SEQ` | 1 | - | **DROP** |
| `JOBS_SEQ` | 1 | siklik-php82, PL/SQL, Laravel | pertahankan |
| `JOURNALHDR_SEQ` | 1 | - | **DROP** |
| `JOURNAL_SEQ` | 1 | - | **DROP** |
| `KS2_SEQ` | 1 | - | **DROP** |
| `LC4_1_SEQ_1` | 1 | - | **DROP** |
| `LC4_SEQ_2` | 1 | - | **DROP** |
| `LOANDTL_SEQ` | 1 | - | **DROP** |
| `MIGRATIONS_ID_SEQ` | 21 | PL/SQL, Laravel | pertahankan |
| `PAYDEBTDTL_SEQ` | 1 | - | **DROP** |
| `PAYDEBTHDR_SEQ` | 1 | - | **DROP** |
| `PERMISSIONS_ID_SEQ` | 1 | PL/SQL, Laravel | pertahankan |
| `PERSONAL_ACCESS_TOKENS_ID_SEQ` | 1 | PL/SQL, Laravel | pertahankan |
| `PRODNO` | 1 | - | **DROP** |
| `PRODUCTNON_SEQ` | 1 | siklik-php82 | pertahankan |
| `RA1_SEQ` | 1 | - | **DROP** |
| `RA2_SEQ` | 1 | - | **DROP** |
| `RB1_SEQ_1` | 1 | - | **DROP** |
| `RCVDTLNON_SEQ` | 1 | siklik-php82 | pertahankan |
| `RCVDTL_SEQ` | 1 | siklik-php82 | pertahankan |
| `RCVPNON_SEQ` | 1 | siklik-php82 | pertahankan |
| `RCVP_SEQ` | 40 | siklik-php82 | pertahankan |
| `RD2_SEQ` | 1 | - | **DROP** |
| `RD_SEQ` | 1 | - | **DROP** |
| `RECEIVEEDS_SEQ` | 1 | - | **DROP** |
| `RIPAYPK_SEQ` | 1 | - | **DROP** |
| `RIPAYP_SEQ` | 1 | - | **DROP** |
| `RJCDTL_SEQ` | 86860 | siklik-php82 | pertahankan |
| `RJCKDTL_SEQ` | 1 | - | **DROP** |
| `ROLES_ID_SEQ` | 1 | PL/SQL, Laravel | pertahankan |
| `RR2_SEQ_1` | 1 | siklik-php82 | pertahankan |
| `RR3_SEQ_1` | 1 | - | **DROP** |
| `SEQSTOCK` | 1 | - | **DROP** |
| `SLSDTL_SEQ` | 3078 | - | pertahankan — pernah dipakai, tidak dirujuk lagi, tinjau manual |
| `SLSP_SEQ` | 1 | - | **DROP** |
| `TEI_SEQ` | 1 | - | **DROP** |
| `TS3_SEQ_1` | 1 | - | **DROP** |
| `TUCASHD_SEQ` | 1 | - | **DROP** |
| `TUCASHK_SEQ` | 1 | - | **DROP** |
| `UGDCDTL_SEQ` | 1 | - | **DROP** |
| `UGDOTHER_SEQ` | 1 | - | **DROP** |
| `USERS_ID_SEQ` | 161 | PL/SQL, Laravel | pertahankan |

## 6. Tabel yang tidak dirujuk kode mana pun (laporan saja, TIDAK di-drop)

| Tabel | Modul | Baris |
|---|---|---|

## Urutan eksekusi

1. Backup schema (`exp`). 2. `01_fk_implisit.sql`. 3. `02_drop_objek_invalid.sql` (setelah `_backup_objek_invalid.sql` disimpan). 4. `03_drop_sequence_tak_terpakai.sql`. Rollback: `99_rollback.sql`.
Setelah itu: `php artisan siklik:dok-tabel` dan regenerasi dump `database/sql/_dev/`.
