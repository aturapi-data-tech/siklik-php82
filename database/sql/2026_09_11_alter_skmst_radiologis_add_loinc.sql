-- =============================================================================
-- File   : 2026_09_11_alter_skmst_radiologis_add_loinc.sql
-- Tujuan : Kolom pemetaan Master Radiologi → LOINC untuk kiriman SATUSEHAT.
--
--          SKMST_RADIOLOGIS.LOINC_CODE     kode LOINC pemeriksaan
--          SKMST_RADIOLOGIS.LOINC_DISPLAY  nama resmi LOINC (display FHIR)
--
-- Kenapa : Kartu ⚡kirim-radiologi memakai kode LOINC per pemeriksaan untuk
--          ServiceRequest.code, Observation.code dan DiagnosticReport.code. Tanpa
--          pemetaan, SEMUA pemeriksaan terkirim sebagai LOINC generik 18748-4
--          "Diagnostic imaging study" — sah di mata validator, tapi tak bisa dibedakan
--          satu sama lain di rekam nasional. Kartu melaporkan berapa order yang jatuh
--          ke kode generik supaya kekurangannya kelihatan, bukan hilang diam-diam.
--
-- PENTING : Pada schema Oracle siklik dev (dicek 11/09/2026) kedua kolom ini SUDAH ADA —
--           ditambahkan oleh install_bundle_satusehat.sql (section SKMST_RADIOLOGIS,
--           lengkap dengan ~150 UPDATE mapping). File ini disediakan untuk schema yang
--           belum pernah menjalankan bundle SatuSehat, dan jadi no-op di schema yang
--           sudah. Isi pemetaannya tetap ada di install_bundle_satusehat.sql — file ini
--           HANYA membuat kolomnya.
--
-- Cara pakai:
--   sqlplus siklik/<password>@//<host>:1521/<service> @2026_09_11_alter_skmst_radiologis_add_loinc.sql
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
PROMPT ─── SKMST_RADIOLOGIS: kolom pemetaan LOINC ───────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKMST_RADIOLOGIS' AND column_name = 'LOINC_CODE';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE skmst_radiologis ADD (loinc_code VARCHAR2(20))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_radiologis.loinc_code IS 'Kode LOINC pemeriksaan radiologi (SATUSEHAT) — kosong = dikirim dengan kode generik 18748-4']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKMST_RADIOLOGIS.LOINC_CODE ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_RADIOLOGIS.LOINC_CODE sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKMST_RADIOLOGIS' AND column_name = 'LOINC_DISPLAY';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE skmst_radiologis ADD (loinc_display VARCHAR2(250))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_radiologis.loinc_display IS 'Nama resmi LOINC — dipakai sebagai display code FHIR; kosong = pakai rad_desc']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKMST_RADIOLOGIS.LOINC_DISPLAY ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_RADIOLOGIS.LOINC_DISPLAY sudah ada — skip');
    END IF;
END;
/

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_indexes WHERE index_name = 'SKMST_RADIOLOGIS_LOINC_IX';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX skmst_radiologis_loinc_ix ON skmst_radiologis (loinc_code)';
        DBMS_OUTPUT.PUT_LINE('  + index SKMST_RADIOLOGIS_LOINC_IX dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = index SKMST_RADIOLOGIS_LOINC_IX sudah ada — skip');
    END IF;
END;
/

COMMIT;

PROMPT
PROMPT Verifikasi:
PROMPT   SELECT column_name FROM user_tab_columns
PROMPT    WHERE table_name = 'SKMST_RADIOLOGIS'
PROMPT      AND column_name IN ('LOINC_CODE','LOINC_DISPLAY');
PROMPT   SELECT COUNT(*) FROM skmst_radiologis WHERE loinc_code IS NOT NULL;
PROMPT
