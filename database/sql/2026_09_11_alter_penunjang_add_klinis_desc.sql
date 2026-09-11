-- =============================================================================
-- File   : 2026_09_11_alter_penunjang_add_klinis_desc.sql
-- Tujuan : Kolom Diagnosis/Keterangan Klinis pada order penunjang rawat jalan.
--
--          SKTXN_CHECKUPHDRS.KLINIS_DESC  keterangan klinis order laboratorium
--                                         (per header order — berlaku utk semua
--                                          item di SKTXN_CHECKUPDTLS)
--          SKTXN_RJRADS.KLINIS_DESC       keterangan klinis order radiologi RJ
--                                         (radiologi tak punya header, jadi
--                                          disimpan per baris order)
--
-- Kenapa : Petugas lab/radiologi menerima daftar item pemeriksaan tanpa tahu
--          indikasinya. Akreditasi menuntut permintaan pemeriksaan penunjang
--          disertai diagnosis kerja / keterangan klinis, dan secara praktis
--          keterangan itu yang menentukan cara petugas menyiapkan & membaca
--          pemeriksaan. Form order EMR RJ kini MEWAJIBKAN field ini.
--
--          Padanan di sirus-php82: LBTXN_CHECKUPHDRS.KLINIS_DESC dan
--          RSTXN_RJRADS/UGDRADS/RIRADIOLOGS.KLINIS_DESC (commit 938f72ee).
--
-- Perilaku app sebelum SQL ini dijalankan:
--          Halaman TIDAK error. App menjaga diri lewat App\Support\KolomOpsional
--          (cek USER_TAB_COLUMNS, cache per request): selama kolom belum ada,
--          form order tetap bisa menyimpan (key klinis_desc tidak ikut di-insert)
--          dan layar petugas menampilkan "-". Isian keterangan klinis baru
--          benar-benar tersimpan SETELAH skrip ini dijalankan.
--
-- Status schema Oracle siklik dev (dicek 11/09/2026):
--          SKTXN_RJRADS.KLINIS_DESC  SUDAH ADA — VARCHAR2(4000), warisan skema lama.
--                                    Section radiologi jadi no-op (kolom TIDAK dipersempit).
--          SKTXN_CHECKUPHDRS.KLINIS_DESC  BELUM ADA — ini yang benar-benar dibuat skrip ini.
--          Form order membatasi input 500 karakter di dua-duanya biar seragam.
--
-- Cara pakai:
--   sqlplus siklik/<password>@//<host>:1521/<service> @2026_09_11_alter_penunjang_add_klinis_desc.sql
--
-- Idempotent  ✅ — per-kolom existence check, aman di-run berkali-kali.
-- =============================================================================

SET SERVEROUTPUT ON SIZE UNLIMITED;
SET DEFINE OFF;
SET FEEDBACK ON;
SET ECHO OFF;
SET LINESIZE 200;
SET PAGESIZE 100;
SET SQLBLANKLINES ON;

PROMPT
PROMPT ─── Order penunjang: kolom KLINIS_DESC ───────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKTXN_CHECKUPHDRS' AND column_name = 'KLINIS_DESC';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE sktxn_checkuphdrs ADD (klinis_desc VARCHAR2(500))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN sktxn_checkuphdrs.klinis_desc IS 'Diagnosis kerja / keterangan klinis order laboratorium — wajib diisi dokter di form order EMR']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKTXN_CHECKUPHDRS.KLINIS_DESC ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_CHECKUPHDRS.KLINIS_DESC sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKTXN_RJRADS' AND column_name = 'KLINIS_DESC';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE sktxn_rjrads ADD (klinis_desc VARCHAR2(500))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN sktxn_rjrads.klinis_desc IS 'Diagnosis kerja / keterangan klinis order radiologi RJ — wajib diisi dokter di form order EMR']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKTXN_RJRADS.KLINIS_DESC ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_RJRADS.KLINIS_DESC sudah ada — skip');
    END IF;
END;
/

COMMIT;

PROMPT
PROMPT Verifikasi:
PROMPT   SELECT table_name, column_name, data_length FROM user_tab_columns
PROMPT    WHERE column_name = 'KLINIS_DESC'
PROMPT      AND table_name IN ('SKTXN_CHECKUPHDRS','SKTXN_RJRADS');
PROMPT   SELECT COUNT(*) FROM sktxn_checkuphdrs WHERE klinis_desc IS NOT NULL;
PROMPT   SELECT COUNT(*) FROM sktxn_rjrads       WHERE klinis_desc IS NOT NULL;
PROMPT
