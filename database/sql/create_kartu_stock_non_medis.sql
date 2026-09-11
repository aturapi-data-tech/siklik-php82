-- ============================================================
-- Modul  : Kartu Stock NON-MEDIS (pelengkap penerimaan non-medis)
-- Depends: create_penerimaan_non_medis.sql (skmst_productnons,
--          sktxn_rcvhdrnons, sktxn_rcvdtlnons) — JALANKAN ITU DULU.
-- Pola   : mirror kartu stock medis klinik (sktxn_saldoawalstocks /
--          sktxn_sowhs / skview_iostockwhs) dgn suffix NON.
-- Catatan: tabel baru TANPA trigger legacy — stock opname (SO) di
--          aplikasi WAJIB ikut menyesuaikan skmst_productnons.qty_box
--          dalam transaksi yang sama.
-- Database : Oracle
-- ============================================================

-- ── 1. Saldo awal stok per tahun ────────────────────────────
CREATE TABLE sktxn_saldoawalstocksnon (
    sa_year     VARCHAR2(4)  NOT NULL,
    product_id  NUMBER       NOT NULL,
    sa_stockwh  NUMBER       DEFAULT 0 NOT NULL,
    CONSTRAINT pk_tktxn_saldoawalstocksnon PRIMARY KEY (sa_year, product_id),
    CONSTRAINT fk_saldonon_prd FOREIGN KEY (product_id) REFERENCES skmst_productnons (product_id)
);

COMMENT ON TABLE sktxn_saldoawalstocksnon IS 'Saldo awal stok barang non-medis per tahun (closing/input manual)';

-- ── 2. Stock opname non-medis (selisih fisik vs catatan) ────
CREATE TABLE sktxn_sowhsnon (
    so_no       NUMBER        NOT NULL,            -- MAX+1 per tabel ini
    product_id  NUMBER        NOT NULL,
    so_date     DATE          NOT NULL,
    kasir_id    VARCHAR2(20),                      -- FK logis skmst_kasirs (konvensi klinik, bukan emp_id)
    so_desc     VARCHAR2(100),
    so_d        NUMBER        DEFAULT 0 NOT NULL,  -- penyesuaian masuk (fisik > catatan)
    so_k        NUMBER        DEFAULT 0 NOT NULL,  -- penyesuaian keluar (fisik < catatan)
    CONSTRAINT pk_tktxn_sowhsnon PRIMARY KEY (so_no),
    CONSTRAINT fk_sowhsnon_prd FOREIGN KEY (product_id) REFERENCES skmst_productnons (product_id)
);

CREATE INDEX idx_sowhsnon_prd ON sktxn_sowhsnon (product_id);

COMMENT ON TABLE sktxn_sowhsnon IS 'Stock opname non-medis — selisih dicatat sbg mutasi SO; aplikasi ikut update qty_box';

-- ── 3. View mutasi in/out non-medis (kontrak = skview_iostockwhs) ──
--     Kolom: product_id, txn_date, txn_no, txn_status, qty_d (masuk), qty_k (keluar)
--     Sumber saat ini: RCV (penerimaan, exclude batal 'F') + SO (opname).
--     Modul pengeluaran/pemakaian non-medis di masa depan tinggal UNION ALL di sini.
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

COMMIT;
