-- =============================================================================
-- File   : 2026_09_11_alter_skmst_products_add_satusehat.sql
-- Tujuan : Kolom pemetaan Master Obat → KFA (Kamus Farmasi & Alkes) SATUSEHAT.
--
--          SKMST_PRODUCTS.PRODUCT_ID_SATUSEHAT    kode KFA obat
--          SKMST_PRODUCTS.PRODUCT_NAME_SATUSEHAT  nama resmi KFA (untuk display FHIR)
--
-- Kenapa : JSON e-resep siklik TIDAK menyimpan kode KFA sama sekali — yang ada
--          `productId`. Selama pasangan kolom ini belum ada & belum diisi, TIDAK ADA
--          satu pun obat yang bisa dikirim: kartu MedicationRequest & MedicationDispense
--          melaporkan "0 item punya KFA" apa adanya. Racikan lebih ketat lagi —
--          SATUSEHAT menuntut KFA PER BAHAN (Medication.ingredient[]), dan bahan racikan
--          siklik bahkan tidak punya productId, jadi dicocokkan lewat NAMA ke master ini.
--
-- Dipakai : App\Support\KolomSatuSehat, App\Support\Terminologi\{ObatKfa,RacikanKfa},
--           halaman Master Produk Apotek, kartu ⚡kirim-medication-request / -dispense.
--
-- Cara pakai:
--   sqlplus siklik/<password>@//<host>:1521/<service> @2026_09_11_alter_skmst_products_add_satusehat.sql
--
-- Idempotent  ✅ — per-kolom existence check, aman di-run berkali-kali.
-- CATATAN     : section yang sama juga ada di install_bundle_fitur_lanjutan.sql
--               (SECTION 05). Menjalankan keduanya aman — yang kedua jadi no-op.
-- =============================================================================

SET SERVEROUTPUT ON SIZE UNLIMITED;
SET DEFINE OFF;
SET FEEDBACK ON;
SET ECHO OFF;
SET LINESIZE 200;
SET PAGESIZE 100;
SET SQLBLANKLINES ON;

PROMPT
PROMPT ─── SKMST_PRODUCTS: kolom pemetaan KFA SATUSEHAT ─────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKMST_PRODUCTS' AND column_name = 'PRODUCT_ID_SATUSEHAT';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE skmst_products ADD (product_id_satusehat VARCHAR2(50))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_products.product_id_satusehat IS 'Kode KFA SATUSEHAT (system http://sys-ids.kemkes.go.id/kfa) — kosong = obat tidak bisa dikirim ke SATUSEHAT']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKMST_PRODUCTS.PRODUCT_ID_SATUSEHAT ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_PRODUCTS.PRODUCT_ID_SATUSEHAT sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'SKMST_PRODUCTS' AND column_name = 'PRODUCT_NAME_SATUSEHAT';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE skmst_products ADD (product_name_satusehat VARCHAR2(250))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_products.product_name_satusehat IS 'Nama resmi KFA SATUSEHAT — dipakai sebagai display Medication.code; kosong = pakai product_name']';
        DBMS_OUTPUT.PUT_LINE('  + kolom SKMST_PRODUCTS.PRODUCT_NAME_SATUSEHAT ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_PRODUCTS.PRODUCT_NAME_SATUSEHAT sudah ada — skip');
    END IF;
END;
/

-- Index pencarian obat ber-KFA (dipakai laporan kelengkapan pemetaan & filter master).
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_indexes WHERE index_name = 'SKMST_PRODUCTS_KFA_IX';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX skmst_products_kfa_ix ON skmst_products (product_id_satusehat)';
        DBMS_OUTPUT.PUT_LINE('  + index SKMST_PRODUCTS_KFA_IX dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = index SKMST_PRODUCTS_KFA_IX sudah ada — skip');
    END IF;
END;
/

COMMIT;

PROMPT
PROMPT Verifikasi:
PROMPT   SELECT column_name FROM user_tab_columns
PROMPT    WHERE table_name = 'SKMST_PRODUCTS'
PROMPT      AND column_name IN ('PRODUCT_ID_SATUSEHAT','PRODUCT_NAME_SATUSEHAT');
PROMPT   SELECT COUNT(*) FROM skmst_products WHERE product_id_satusehat IS NOT NULL;
PROMPT
