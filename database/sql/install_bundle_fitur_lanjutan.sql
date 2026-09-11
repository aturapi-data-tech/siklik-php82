-- =============================================================================
-- File   : install_bundle_fitur_lanjutan.sql
-- Tujuan : Bundle SEMUA SQL fitur lanjutan siklik-php82 (Juni 2026) dalam 1 file.
--          Gabungan idempotent dari 7 file referensi:
--            01  create_tkmst_signa_catatans.sql   — LOV catatan khusus signa e-resep
--            02  create_penerimaan_non_medis.sql   — master + penerimaan + hutang non-medis
--            03  create_kartu_stock_non_medis.sql  — saldo awal + opname + view mutasi non-medis
--            04  alter_users_add_last_seen.sql     — kolom tracking utk halaman User Online
--            05  2026_09_11_alter_skmst_products_add_satusehat.sql  — kolom KFA master obat
--            06  2026_09_11_alter_skmst_radiologis_add_loinc.sql    — kolom LOINC master radiologi
--            07  2026_09_11_alter_penunjang_add_klinis_desc.sql     — kolom Diagnosis/Ket. Klinis order penunjang
--
--          Catatan stok non-medis: stok TUNGGAL di SKMST_PRODUCTNONS.QTY_BOX
--          (tanpa lokasi/transfer). Tabel ini TANPA trigger legacy — qty_box
--          di-update APLIKASI (modul penerimaan & opname) dlm transaksi yg sama.
--
-- Cara pakai (di server):
--   sqlplus siklik/<password>@//<host>:1521/<service> @install_bundle_fitur_lanjutan.sql
--
-- Idempotent  ✅ — semua section di-guard dgn existence check
--               (view di-CREATE OR REPLACE — selalu fresh).
-- Aman re-run :)
-- =============================================================================

SET SERVEROUTPUT ON SIZE UNLIMITED;
SET DEFINE OFF;
SET FEEDBACK ON;
SET ECHO OFF;
SET LINESIZE 200;
SET PAGESIZE 100;
SET SQLBLANKLINES ON;

PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  SIKLIK-PHP82 INSTALL BUNDLE FITUR LANJUTAN — START        ║
PROMPT ╚════════════════════════════════════════════════════════════╝


-- =============================================================================
-- SECTION 01 — SKMST_SIGNA_CATATANS (LOV catatan khusus signa e-resep)
-- =============================================================================
PROMPT
PROMPT ─── [1/7] SKMST_SIGNA_CATATANS ───────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_SIGNA_CATATANS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE skmst_signa_catatans (
                catatan         VARCHAR2(255)   NOT NULL,
                active_status   VARCHAR2(1)     DEFAULT '1' NOT NULL,
                CONSTRAINT pk_tkmst_signa_catatans PRIMARY KEY (catatan)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  + SKMST_SIGNA_CATATANS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_SIGNA_CATATANS sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_indexes WHERE index_name = 'SIGNA_CATATANS_ACTIVE_IX';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX signa_catatans_active_ix ON skmst_signa_catatans (active_status)';
        DBMS_OUTPUT.PUT_LINE('  + index active_status dibuat');
    END IF;

    EXECUTE IMMEDIATE q'[COMMENT ON TABLE skmst_signa_catatans IS 'LOV catatan khusus signa e-resep rawat jalan']';
    EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_signa_catatans.catatan IS 'Teks catatan khusus yang muncul di dropdown (PK)']';
    EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_signa_catatans.active_status IS '1 = aktif, 0 = nonaktif']';
END;
/


-- =============================================================================
-- SECTION 02 — Penerimaan Barang NON-MEDIS
--   Master + header/detail penerimaan + riwayat pembayaran + cashout hutang.
--   Pola klinik: kasir_id + cb_id, TANPA shift/sp_no/batch/ED.
--   rcv_status: H=hutang, L=lunas, F=batal, A=daftar tunggu/rollback.
-- =============================================================================
PROMPT
PROMPT ─── [2/7] Penerimaan Non-Medis (6 tabel + 5 sequence) ────────

DECLARE
    v_count NUMBER;

    PROCEDURE ensure_seq(p_name VARCHAR2) IS
        v NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v FROM user_sequences WHERE sequence_name = UPPER(p_name);
        IF v = 0 THEN
            EXECUTE IMMEDIATE 'CREATE SEQUENCE ' || p_name || ' START WITH 1 INCREMENT BY 1 NOCACHE';
            DBMS_OUTPUT.PUT_LINE('  + sequence ' || UPPER(p_name) || ' dibuat');
        ELSE
            DBMS_OUTPUT.PUT_LINE('  = sequence ' || UPPER(p_name) || ' sudah ada — skip');
        END IF;
    END;
BEGIN
    -- ── 2a. Master barang non-medis ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_PRODUCTNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE skmst_productnons (
                product_id     NUMBER          NOT NULL,
                product_name   VARCHAR2(150)   NOT NULL,
                uom_id         VARCHAR2(20),
                cost_price     NUMBER          DEFAULT 0 NOT NULL,
                qty_box        NUMBER          DEFAULT 0 NOT NULL,
                limit_stock    NUMBER          DEFAULT 0,
                active_status  VARCHAR2(1)     DEFAULT '1' NOT NULL,
                CONSTRAINT pk_tkmst_productnons PRIMARY KEY (product_id)
            )
        ]';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE skmst_productnons IS 'Master barang non-medis (ATK/RT) — stok tunggal di qty_box']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_productnons.qty_box IS 'Stok berjalan — di-update aplikasi saat posting/hapus penerimaan & opname']';
        DBMS_OUTPUT.PUT_LINE('  + SKMST_PRODUCTNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKMST_PRODUCTNONS sudah ada — skip');
    END IF;

    ensure_seq('productnon_seq');

    -- ── 2b. Header penerimaan ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_RCVHDRNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_rcvhdrnons (
                rcv_no         NUMBER          NOT NULL,
                rcv_date       DATE            NOT NULL,
                supp_id        VARCHAR2(20),
                kasir_id       VARCHAR2(20),
                cb_id          VARCHAR2(20),
                rcv_desc       VARCHAR2(400),
                rcv_status     VARCHAR2(1)     DEFAULT 'A' NOT NULL,
                pay_date       DATE,
                rcv_bayar      NUMBER          DEFAULT 0,
                due_date       DATE,
                rcv_diskon     NUMBER          DEFAULT 0,
                rcv_ppn        NUMBER          DEFAULT 0,
                rcv_ppn_status VARCHAR2(1)     DEFAULT '0',
                rcv_materai    NUMBER          DEFAULT 0,
                CONSTRAINT pk_tktxn_rcvhdrnons PRIMARY KEY (rcv_no)
            )
        ]';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE sktxn_rcvhdrnons IS 'Header penerimaan barang non-medis (rcv_no = MAX+1 per tabel ini; status H/L/F/A)']';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_RCVHDRNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_RCVHDRNONS sudah ada — skip');
    END IF;

    -- ── 2c. Detail penerimaan ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_RCVDTLNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_rcvdtlnons (
                rcv_dtl      NUMBER  NOT NULL,
                rcv_no       NUMBER  NOT NULL,
                product_id   NUMBER  NOT NULL,
                qty          NUMBER  DEFAULT 0 NOT NULL,
                cost_price   NUMBER  DEFAULT 0 NOT NULL,
                dtl_diskon   NUMBER  DEFAULT 0,
                dtl_diskon1  NUMBER  DEFAULT 0,
                dtl_persen   NUMBER  DEFAULT 0,
                dtl_persen1  NUMBER  DEFAULT 0,
                CONSTRAINT pk_tktxn_rcvdtlnons PRIMARY KEY (rcv_dtl),
                CONSTRAINT fk_rcvdtlnons_hdr FOREIGN KEY (rcv_no)     REFERENCES sktxn_rcvhdrnons (rcv_no),
                CONSTRAINT fk_rcvdtlnons_prd FOREIGN KEY (product_id) REFERENCES skmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_rcvdtlnons_rcvno ON sktxn_rcvdtlnons (rcv_no)';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_RCVDTLNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_RCVDTLNONS sudah ada — skip');
    END IF;

    ensure_seq('rcvdtlnon_seq');

    -- ── 2d. Riwayat pembayaran (cicilan/pelunasan) ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_RCVPAYMENTNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_rcvpaymentnons (
                rcvp_no    NUMBER  NOT NULL,
                rcv_no     NUMBER  NOT NULL,
                rcvp_date  DATE    NOT NULL,
                rcvp_value NUMBER  DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_rcvpaymentnons PRIMARY KEY (rcvp_no),
                CONSTRAINT fk_rcvpaynons_hdr FOREIGN KEY (rcv_no) REFERENCES sktxn_rcvhdrnons (rcv_no)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_RCVPAYMENTNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_RCVPAYMENTNONS sudah ada — skip');
    END IF;

    ensure_seq('rcvpnon_seq');

    -- ── 2e. Pengeluaran kas utk pembayaran hutang non-medis ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_CASHOUTHDRNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_cashouthdrnons (
                cashout_no    NUMBER         NOT NULL,
                cashout_date  DATE           NOT NULL,
                kasir_id      VARCHAR2(20),
                supp_id       VARCHAR2(20),
                cb_id         VARCHAR2(20),
                cashout_desc  VARCHAR2(400),
                cashout_value NUMBER         DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_cashouthdrnons PRIMARY KEY (cashout_no)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_CASHOUTHDRNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_CASHOUTHDRNONS sudah ada — skip');
    END IF;

    ensure_seq('cashoutnon_seq');

    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_CASHOUTDTLNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_cashoutdtlnons (
                cashout_dtl NUMBER NOT NULL,
                cashout_no  NUMBER NOT NULL,
                rcv_no      NUMBER NOT NULL,
                CONSTRAINT pk_tktxn_cashoutdtlnons PRIMARY KEY (cashout_dtl),
                CONSTRAINT fk_codtlnons_hdr FOREIGN KEY (cashout_no) REFERENCES sktxn_cashouthdrnons (cashout_no),
                CONSTRAINT fk_codtlnons_rcv FOREIGN KEY (rcv_no)     REFERENCES sktxn_rcvhdrnons (rcv_no)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_codtlnons_rcvno ON sktxn_cashoutdtlnons (rcv_no)';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_CASHOUTDTLNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_CASHOUTDTLNONS sudah ada — skip');
    END IF;

    ensure_seq('codtlnon_seq');
END;
/


-- =============================================================================
-- SECTION 03 — Kartu Stock NON-MEDIS (saldo awal + opname + view mutasi)
-- =============================================================================
PROMPT
PROMPT ─── [3/7] Kartu Stock Non-Medis ──────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    -- ── 3a. Saldo awal stok per tahun ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_SALDOAWALSTOCKSNON';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_saldoawalstocksnon (
                sa_year     VARCHAR2(4)  NOT NULL,
                product_id  NUMBER       NOT NULL,
                sa_stockwh  NUMBER       DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_saldoawalstocksnon PRIMARY KEY (sa_year, product_id),
                CONSTRAINT fk_saldonon_prd FOREIGN KEY (product_id) REFERENCES skmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE sktxn_saldoawalstocksnon IS 'Saldo awal stok barang non-medis per tahun (closing/input manual)']';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_SALDOAWALSTOCKSNON dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_SALDOAWALSTOCKSNON sudah ada — skip');
    END IF;

    -- ── 3b. Stock opname non-medis ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKTXN_SOWHSNON';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sktxn_sowhsnon (
                so_no       NUMBER        NOT NULL,
                product_id  NUMBER        NOT NULL,
                so_date     DATE          NOT NULL,
                kasir_id    VARCHAR2(20),
                so_desc     VARCHAR2(100),
                so_d        NUMBER        DEFAULT 0 NOT NULL,
                so_k        NUMBER        DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_sowhsnon PRIMARY KEY (so_no),
                CONSTRAINT fk_sowhsnon_prd FOREIGN KEY (product_id) REFERENCES skmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_sowhsnon_prd ON sktxn_sowhsnon (product_id)';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE sktxn_sowhsnon IS 'Stock opname non-medis — selisih dicatat sbg mutasi SO; aplikasi ikut update qty_box']';
        DBMS_OUTPUT.PUT_LINE('  + SKTXN_SOWHSNON dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = SKTXN_SOWHSNON sudah ada — skip (data opname preserved)');
    END IF;
END;
/

-- ── 3c. View mutasi in/out non-medis — selalu di-recreate (fresh) ──
--    Kontrak kolom = SKVIEW_IOSTOCKWHS medis: product_id, txn_date, txn_no,
--    txn_status ('RCV'/'SO'), qty_d (masuk), qty_k (keluar).
--    Modul pengeluaran/pemakaian non-medis ke depan tinggal UNION ALL di sini.
PROMPT   ~ re-create view SKVIEW_IOSTOCKWHSNON

CREATE OR REPLACE VIEW skview_iostockwhsnon AS
SELECT d.product_id,
       h.rcv_date     AS txn_date,
       h.rcv_no       AS txn_no,
       'RCV'          AS txn_status,
       d.qty          AS qty_d,
       0              AS qty_k
  FROM sktxn_rcvdtlnons d
  JOIN sktxn_rcvhdrnons h ON h.rcv_no = d.rcv_no
 WHERE NVL(h.rcv_status, 'A') <> 'F'
UNION ALL
SELECT s.product_id,
       s.so_date      AS txn_date,
       s.so_no        AS txn_no,
       'SO'           AS txn_status,
       s.so_d         AS qty_d,
       s.so_k         AS qty_k
  FROM sktxn_sowhsnon s;

COMMENT ON TABLE skview_iostockwhsnon IS 'View mutasi stok non-medis: RCV (penerimaan) + SO (opname)';


-- =============================================================================
-- SECTION 04 — USERS: kolom tracking aktivitas (halaman User Online)
-- =============================================================================
PROMPT
PROMPT ─── [4/7] USERS.LAST_SEEN_AT + LAST_SEEN_ROUTE ───────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'USERS' AND column_name = 'LAST_SEEN_AT';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE users ADD (last_seen_at DATE)';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN users.last_seen_at IS 'Waktu aktivitas terakhir user (di-update middleware TrackUserActivity, throttle 1 menit)']';
        DBMS_OUTPUT.PUT_LINE('  + kolom USERS.LAST_SEEN_AT ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = USERS.LAST_SEEN_AT sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_tab_cols
     WHERE table_name = 'USERS' AND column_name = 'LAST_SEEN_ROUTE';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE users ADD (last_seen_route VARCHAR2(150))';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN users.last_seen_route IS 'Route name halaman terakhir yang dilihat user']';
        DBMS_OUTPUT.PUT_LINE('  + kolom USERS.LAST_SEEN_ROUTE ditambah');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = USERS.LAST_SEEN_ROUTE sudah ada — skip');
    END IF;
END;
/

COMMIT;


-- =============================================================================
-- SECTION 05 — SKMST_PRODUCTS: kolom pemetaan KFA SATUSEHAT
--   (file referensi: 2026_09_11_alter_skmst_products_add_satusehat.sql)
--
-- JSON e-resep siklik tidak menyimpan kode KFA sama sekali — yang ada `productId`.
-- Selama pasangan kolom ini belum ada & belum diisi, TIDAK ADA satu pun obat yang
-- bisa dikirim ke SATUSEHAT (MedicationRequest & MedicationDispense).
-- =============================================================================
PROMPT
PROMPT ─── [5/7] SKMST_PRODUCTS.PRODUCT_ID_SATUSEHAT + _NAME_ ───────

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


-- =============================================================================
-- SECTION 06 — SKMST_RADIOLOGIS: kolom pemetaan LOINC
--   (file referensi: 2026_09_11_alter_skmst_radiologis_add_loinc.sql)
--
-- CATATAN: kolom yang sama juga dibuat install_bundle_satusehat.sql — di sana
-- LENGKAP dengan ~150 UPDATE pemetaan kodenya. Section ini hanya menjamin kolomnya
-- ada untuk schema yang belum pernah menjalankan bundle SatuSehat; kalau sudah,
-- section ini jadi no-op. Tanpa isi pemetaan, kartu ⚡kirim-radiologi tetap jalan
-- tapi memakai LOINC generik 18748-4 untuk semua pemeriksaan (dan melaporkannya).
-- =============================================================================
PROMPT
PROMPT ─── [6/7] SKMST_RADIOLOGIS.LOINC_CODE + LOINC_DISPLAY ────────

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


-- =============================================================================
-- SECTION 07 — Order penunjang: kolom Diagnosis/Keterangan Klinis
--   SKTXN_CHECKUPHDRS.KLINIS_DESC  keterangan klinis order laboratorium (per header)
--   SKTXN_RJRADS.KLINIS_DESC       keterangan klinis order radiologi RJ (per baris)
--   (file referensi: 2026_09_11_alter_penunjang_add_klinis_desc.sql)
--
-- Form order lab & radiologi di EMR RJ MEWAJIBKAN field ini; layar petugas
-- (Daftar Laborat, Display Pasien Laborat, Radiologi RJ) menampilkannya.
-- Sebelum section ini jalan app tidak error — App\Support\KolomOpsional menahan
-- kolomnya keluar dari query/insert dan layar menampilkan "-".
--
-- Oracle dev 11/09/2026: SKTXN_RJRADS.KLINIS_DESC sudah ada (VARCHAR2(4000)) — bagian
-- radiologi jadi no-op & tidak mempersempit kolom; yang benar-benar dibuat di sini
-- SKTXN_CHECKUPHDRS.KLINIS_DESC.
-- =============================================================================
PROMPT
PROMPT ─── [7/7] SKTXN_CHECKUPHDRS + SKTXN_RJRADS: KLINIS_DESC ──────

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
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  SIKLIK-PHP82 INSTALL BUNDLE FITUR LANJUTAN — SELESAI ✓    ║
PROMPT ║                                                            ║
PROMPT ║  Verifikasi cepat:                                         ║
PROMPT ║    SELECT COUNT(*) FROM skmst_signa_catatans;              ║
PROMPT ║    SELECT COUNT(*) FROM skmst_productnons;                 ║
PROMPT ║    SELECT COUNT(*) FROM skview_iostockwhsnon;              ║
PROMPT ║    SELECT last_seen_at FROM users WHERE ROWNUM = 1;        ║
PROMPT ║    SELECT COUNT(*) FROM skmst_products                     ║
PROMPT ║     WHERE product_id_satusehat IS NOT NULL;                ║
PROMPT ║    SELECT COUNT(*) FROM skmst_radiologis                   ║
PROMPT ║     WHERE loinc_code IS NOT NULL;                          ║
PROMPT ║    SELECT column_name FROM user_tab_columns                ║
PROMPT ║     WHERE column_name = 'KLINIS_DESC';                     ║
PROMPT ╚════════════════════════════════════════════════════════════╝
