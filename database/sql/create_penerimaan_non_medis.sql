-- ============================================================
-- Modul  : Penerimaan Barang NON-MEDIS (ATK / Rumah Tangga / dll)
-- Pola   : klinik (TKMST/TKTXN + kasir_id + cb_id, TANPA shift/sp_no)
-- Stok   : TUNGGAL langsung di master (tkmst_productnons.qty_box).
--          Tidak ada lokasi / transfer stok (kebijakan klinik).
--          qty_box di-update APLIKASI saat posting/edit/hapus penerimaan
--          (tabel baru — tidak ada trigger legacy seperti tabel medis).
-- Hutang : terpisah dari medis (rcv_no medis & non-medis bisa tabrakan,
--          maka cashout & payment punya tabel *NONS sendiri).
-- Database : Oracle
-- ============================================================

-- ── 1. Master barang non-medis ──────────────────────────────
CREATE TABLE tkmst_productnons (
    product_id     NUMBER          NOT NULL,
    product_name   VARCHAR2(150)   NOT NULL,
    uom_id         VARCHAR2(20),                          -- FK logis tkmst_uoms
    cost_price     NUMBER          DEFAULT 0 NOT NULL,
    qty_box        NUMBER          DEFAULT 0 NOT NULL,    -- stok berjalan (single location)
    limit_stock    NUMBER          DEFAULT 0,
    active_status  VARCHAR2(1)     DEFAULT '1' NOT NULL,  -- '1' aktif / '0' nonaktif
    CONSTRAINT pk_tkmst_productnons PRIMARY KEY (product_id)
);

CREATE SEQUENCE productnon_seq START WITH 1 INCREMENT BY 1 NOCACHE;

COMMENT ON TABLE  tkmst_productnons          IS 'Master barang non-medis (ATK/RT) — stok tunggal di qty_box';
COMMENT ON COLUMN tkmst_productnons.qty_box  IS 'Stok berjalan — di-update aplikasi saat posting/hapus penerimaan';

-- ── 2. Header penerimaan non-medis ──────────────────────────
CREATE TABLE tktxn_rcvhdrnons (
    rcv_no         NUMBER          NOT NULL,
    rcv_date       DATE            NOT NULL,
    supp_id        VARCHAR2(20),                          -- FK logis tkmst_suppliers
    kasir_id       VARCHAR2(20),                          -- FK logis tkmst_kasirs
    cb_id          VARCHAR2(20),                          -- FK logis tkacc_carabayars
    rcv_desc       VARCHAR2(400),
    rcv_status     VARCHAR2(1)     DEFAULT 'A' NOT NULL,  -- H=hutang, L=lunas, F=batal, A=daftar tunggu/rollback (aplikasi selalu set eksplisit)
    pay_date       DATE,
    rcv_bayar      NUMBER          DEFAULT 0,
    due_date       DATE,
    rcv_diskon     NUMBER          DEFAULT 0,
    rcv_ppn        NUMBER          DEFAULT 0,
    rcv_ppn_status VARCHAR2(1)     DEFAULT '0',
    rcv_materai    NUMBER          DEFAULT 0,
    CONSTRAINT pk_tktxn_rcvhdrnons PRIMARY KEY (rcv_no)
);

COMMENT ON TABLE tktxn_rcvhdrnons IS 'Header penerimaan barang non-medis dari supplier (rcv_no = MAX+1 per tabel ini)';

-- ── 3. Detail penerimaan non-medis ──────────────────────────
CREATE TABLE tktxn_rcvdtlnons (
    rcv_dtl      NUMBER  NOT NULL,
    rcv_no       NUMBER  NOT NULL,
    product_id   NUMBER  NOT NULL,
    qty          NUMBER  DEFAULT 0 NOT NULL,
    cost_price   NUMBER  DEFAULT 0 NOT NULL,
    dtl_diskon   NUMBER  DEFAULT 0,   -- nominal diskon 1 per item
    dtl_diskon1  NUMBER  DEFAULT 0,   -- nominal diskon 2 per item
    dtl_persen   NUMBER  DEFAULT 0,   -- persen diskon 1
    dtl_persen1  NUMBER  DEFAULT 0,   -- persen diskon 2
    CONSTRAINT pk_tktxn_rcvdtlnons PRIMARY KEY (rcv_dtl),
    CONSTRAINT fk_rcvdtlnons_hdr FOREIGN KEY (rcv_no)     REFERENCES tktxn_rcvhdrnons (rcv_no),
    CONSTRAINT fk_rcvdtlnons_prd FOREIGN KEY (product_id) REFERENCES tkmst_productnons (product_id)
);

CREATE SEQUENCE rcvdtlnon_seq START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE INDEX idx_rcvdtlnons_rcvno ON tktxn_rcvdtlnons (rcv_no);

-- ── 4. Riwayat pembayaran penerimaan (cicilan/pelunasan) ────
CREATE TABLE tktxn_rcvpaymentnons (
    rcvp_no    NUMBER  NOT NULL,
    rcv_no     NUMBER  NOT NULL,
    rcvp_date  DATE    NOT NULL,
    rcvp_value NUMBER  DEFAULT 0 NOT NULL,
    CONSTRAINT pk_tktxn_rcvpaymentnons PRIMARY KEY (rcvp_no),
    CONSTRAINT fk_rcvpaynons_hdr FOREIGN KEY (rcv_no) REFERENCES tktxn_rcvhdrnons (rcv_no)
);

CREATE SEQUENCE rcvpnon_seq START WITH 1 INCREMENT BY 1 NOCACHE;

-- ── 5. Pengeluaran kas utk pembayaran non-medis ─────────────
CREATE TABLE tktxn_cashouthdrnons (
    cashout_no    NUMBER         NOT NULL,
    cashout_date  DATE           NOT NULL,
    kasir_id      VARCHAR2(20),
    supp_id       VARCHAR2(20),
    cb_id         VARCHAR2(20),
    cashout_desc  VARCHAR2(400),
    cashout_value NUMBER         DEFAULT 0 NOT NULL,
    CONSTRAINT pk_tktxn_cashouthdrnons PRIMARY KEY (cashout_no)
);

CREATE SEQUENCE cashoutnon_seq START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TABLE tktxn_cashoutdtlnons (
    cashout_dtl NUMBER NOT NULL,
    cashout_no  NUMBER NOT NULL,
    rcv_no      NUMBER NOT NULL,
    CONSTRAINT pk_tktxn_cashoutdtlnons PRIMARY KEY (cashout_dtl),
    CONSTRAINT fk_codtlnons_hdr FOREIGN KEY (cashout_no) REFERENCES tktxn_cashouthdrnons (cashout_no),
    CONSTRAINT fk_codtlnons_rcv FOREIGN KEY (rcv_no)     REFERENCES tktxn_rcvhdrnons (rcv_no)
);

CREATE SEQUENCE codtlnon_seq START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE INDEX idx_codtlnons_rcvno ON tktxn_cashoutdtlnons (rcv_no);

COMMIT;
