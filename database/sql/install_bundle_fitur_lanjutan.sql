-- =============================================================================
-- File   : install_bundle_fitur_lanjutan.sql
-- Tujuan : Bundle SEMUA SQL fitur lanjutan siklik-php82 (Juni 2026) dalam 1 file.
--          Gabungan idempotent dari 4 file referensi:
--            01  create_tkmst_signa_catatans.sql   — LOV catatan khusus signa e-resep
--            02  create_penerimaan_non_medis.sql   — master + penerimaan + hutang non-medis
--            03  create_kartu_stock_non_medis.sql  — saldo awal + opname + view mutasi non-medis
--            04  alter_users_add_last_seen.sql     — kolom tracking utk halaman User Online
--
--          Catatan stok non-medis: stok TUNGGAL di TKMST_PRODUCTNONS.QTY_BOX
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
-- SECTION 01 — TKMST_SIGNA_CATATANS (LOV catatan khusus signa e-resep)
-- =============================================================================
PROMPT
PROMPT ─── [1/4] TKMST_SIGNA_CATATANS ───────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKMST_SIGNA_CATATANS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tkmst_signa_catatans (
                catatan         VARCHAR2(255)   NOT NULL,
                active_status   VARCHAR2(1)     DEFAULT '1' NOT NULL,
                CONSTRAINT pk_tkmst_signa_catatans PRIMARY KEY (catatan)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  + TKMST_SIGNA_CATATANS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKMST_SIGNA_CATATANS sudah ada — skip');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_indexes WHERE index_name = 'IDX_TKMST_SIGNA_CATATANS_ACTIVE';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX idx_tkmst_signa_catatans_active ON tkmst_signa_catatans (active_status)';
        DBMS_OUTPUT.PUT_LINE('  + index active_status dibuat');
    END IF;

    EXECUTE IMMEDIATE q'[COMMENT ON TABLE tkmst_signa_catatans IS 'LOV catatan khusus signa e-resep rawat jalan']';
    EXECUTE IMMEDIATE q'[COMMENT ON COLUMN tkmst_signa_catatans.catatan IS 'Teks catatan khusus yang muncul di dropdown (PK)']';
    EXECUTE IMMEDIATE q'[COMMENT ON COLUMN tkmst_signa_catatans.active_status IS '1 = aktif, 0 = nonaktif']';
END;
/


-- =============================================================================
-- SECTION 02 — Penerimaan Barang NON-MEDIS
--   Master + header/detail penerimaan + riwayat pembayaran + cashout hutang.
--   Pola klinik: kasir_id + cb_id, TANPA shift/sp_no/batch/ED.
--   rcv_status: H=hutang, L=lunas, F=batal, A=daftar tunggu/rollback.
-- =============================================================================
PROMPT
PROMPT ─── [2/4] Penerimaan Non-Medis (6 tabel + 5 sequence) ────────

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
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKMST_PRODUCTNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tkmst_productnons (
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
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE tkmst_productnons IS 'Master barang non-medis (ATK/RT) — stok tunggal di qty_box']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN tkmst_productnons.qty_box IS 'Stok berjalan — di-update aplikasi saat posting/hapus penerimaan & opname']';
        DBMS_OUTPUT.PUT_LINE('  + TKMST_PRODUCTNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKMST_PRODUCTNONS sudah ada — skip');
    END IF;

    ensure_seq('productnon_seq');

    -- ── 2b. Header penerimaan ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_RCVHDRNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_rcvhdrnons (
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
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE tktxn_rcvhdrnons IS 'Header penerimaan barang non-medis (rcv_no = MAX+1 per tabel ini; status H/L/F/A)']';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_RCVHDRNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_RCVHDRNONS sudah ada — skip');
    END IF;

    -- ── 2c. Detail penerimaan ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_RCVDTLNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_rcvdtlnons (
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
                CONSTRAINT fk_rcvdtlnons_hdr FOREIGN KEY (rcv_no)     REFERENCES tktxn_rcvhdrnons (rcv_no),
                CONSTRAINT fk_rcvdtlnons_prd FOREIGN KEY (product_id) REFERENCES tkmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_rcvdtlnons_rcvno ON tktxn_rcvdtlnons (rcv_no)';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_RCVDTLNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_RCVDTLNONS sudah ada — skip');
    END IF;

    ensure_seq('rcvdtlnon_seq');

    -- ── 2d. Riwayat pembayaran (cicilan/pelunasan) ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_RCVPAYMENTNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_rcvpaymentnons (
                rcvp_no    NUMBER  NOT NULL,
                rcv_no     NUMBER  NOT NULL,
                rcvp_date  DATE    NOT NULL,
                rcvp_value NUMBER  DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_rcvpaymentnons PRIMARY KEY (rcvp_no),
                CONSTRAINT fk_rcvpaynons_hdr FOREIGN KEY (rcv_no) REFERENCES tktxn_rcvhdrnons (rcv_no)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_RCVPAYMENTNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_RCVPAYMENTNONS sudah ada — skip');
    END IF;

    ensure_seq('rcvpnon_seq');

    -- ── 2e. Pengeluaran kas utk pembayaran hutang non-medis ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_CASHOUTHDRNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_cashouthdrnons (
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
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_CASHOUTHDRNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_CASHOUTHDRNONS sudah ada — skip');
    END IF;

    ensure_seq('cashoutnon_seq');

    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_CASHOUTDTLNONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_cashoutdtlnons (
                cashout_dtl NUMBER NOT NULL,
                cashout_no  NUMBER NOT NULL,
                rcv_no      NUMBER NOT NULL,
                CONSTRAINT pk_tktxn_cashoutdtlnons PRIMARY KEY (cashout_dtl),
                CONSTRAINT fk_codtlnons_hdr FOREIGN KEY (cashout_no) REFERENCES tktxn_cashouthdrnons (cashout_no),
                CONSTRAINT fk_codtlnons_rcv FOREIGN KEY (rcv_no)     REFERENCES tktxn_rcvhdrnons (rcv_no)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_codtlnons_rcvno ON tktxn_cashoutdtlnons (rcv_no)';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_CASHOUTDTLNONS dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_CASHOUTDTLNONS sudah ada — skip');
    END IF;

    ensure_seq('codtlnon_seq');
END;
/


-- =============================================================================
-- SECTION 03 — Kartu Stock NON-MEDIS (saldo awal + opname + view mutasi)
-- =============================================================================
PROMPT
PROMPT ─── [3/4] Kartu Stock Non-Medis ──────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    -- ── 3a. Saldo awal stok per tahun ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_SALDOAWALSTOCKSNON';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_saldoawalstocksnon (
                sa_year     VARCHAR2(4)  NOT NULL,
                product_id  NUMBER       NOT NULL,
                sa_stockwh  NUMBER       DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_saldoawalstocksnon PRIMARY KEY (sa_year, product_id),
                CONSTRAINT fk_saldonon_prd FOREIGN KEY (product_id) REFERENCES tkmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE tktxn_saldoawalstocksnon IS 'Saldo awal stok barang non-medis per tahun (closing/input manual)']';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_SALDOAWALSTOCKSNON dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_SALDOAWALSTOCKSNON sudah ada — skip');
    END IF;

    -- ── 3b. Stock opname non-medis ──
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'TKTXN_SOWHSNON';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE tktxn_sowhsnon (
                so_no       NUMBER        NOT NULL,
                product_id  NUMBER        NOT NULL,
                so_date     DATE          NOT NULL,
                kasir_id    VARCHAR2(20),
                so_desc     VARCHAR2(100),
                so_d        NUMBER        DEFAULT 0 NOT NULL,
                so_k        NUMBER        DEFAULT 0 NOT NULL,
                CONSTRAINT pk_tktxn_sowhsnon PRIMARY KEY (so_no),
                CONSTRAINT fk_sowhsnon_prd FOREIGN KEY (product_id) REFERENCES tkmst_productnons (product_id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_sowhsnon_prd ON tktxn_sowhsnon (product_id)';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE tktxn_sowhsnon IS 'Stock opname non-medis — selisih dicatat sbg mutasi SO; aplikasi ikut update qty_box']';
        DBMS_OUTPUT.PUT_LINE('  + TKTXN_SOWHSNON dibuat');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  = TKTXN_SOWHSNON sudah ada — skip (data opname preserved)');
    END IF;
END;
/

-- ── 3c. View mutasi in/out non-medis — selalu di-recreate (fresh) ──
--    Kontrak kolom = TKVIEW_IOSTOCKWHS medis: product_id, txn_date, txn_no,
--    txn_status ('RCV'/'SO'), qty_d (masuk), qty_k (keluar).
--    Modul pengeluaran/pemakaian non-medis ke depan tinggal UNION ALL di sini.
PROMPT   ~ re-create view TKVIEW_IOSTOCKWHSNON

CREATE OR REPLACE VIEW tkview_iostockwhsnon AS
SELECT d.product_id,
       h.rcv_date     AS txn_date,
       h.rcv_no       AS txn_no,
       'RCV'          AS txn_status,
       d.qty          AS qty_d,
       0              AS qty_k
  FROM tktxn_rcvdtlnons d
  JOIN tktxn_rcvhdrnons h ON h.rcv_no = d.rcv_no
 WHERE NVL(h.rcv_status, 'A') <> 'F'
UNION ALL
SELECT s.product_id,
       s.so_date      AS txn_date,
       s.so_no        AS txn_no,
       'SO'           AS txn_status,
       s.so_d         AS qty_d,
       s.so_k         AS qty_k
  FROM tktxn_sowhsnon s;

COMMENT ON TABLE tkview_iostockwhsnon IS 'View mutasi stok non-medis: RCV (penerimaan) + SO (opname)';


-- =============================================================================
-- SECTION 04 — USERS: kolom tracking aktivitas (halaman User Online)
-- =============================================================================
PROMPT
PROMPT ─── [4/4] USERS.LAST_SEEN_AT + LAST_SEEN_ROUTE ───────────────

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

PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  SIKLIK-PHP82 INSTALL BUNDLE FITUR LANJUTAN — SELESAI ✓    ║
PROMPT ║                                                            ║
PROMPT ║  Verifikasi cepat:                                         ║
PROMPT ║    SELECT COUNT(*) FROM tkmst_signa_catatans;              ║
PROMPT ║    SELECT COUNT(*) FROM tkmst_productnons;                 ║
PROMPT ║    SELECT COUNT(*) FROM tkview_iostockwhsnon;              ║
PROMPT ║    SELECT last_seen_at FROM users WHERE ROWNUM = 1;        ║
PROMPT ╚════════════════════════════════════════════════════════════╝
