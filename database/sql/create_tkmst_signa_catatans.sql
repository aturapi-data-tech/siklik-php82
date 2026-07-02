-- ============================================================
-- Tabel: tkmst_signa_catatans
-- Deskripsi: LOV catatan khusus untuk signa pada e-resep
--            rawat jalan. Dipakai sebagai pilihan dropdown
--            saat dokter mengisi field catatan_khusus / rj_ket.
-- Database : Oracle
-- Porting  : dari sirus-php82 (struktur identik)
-- ============================================================

CREATE TABLE tkmst_signa_catatans (
    catatan         VARCHAR2(255)   NOT NULL,
    active_status   VARCHAR2(1)     DEFAULT '1' NOT NULL,
    CONSTRAINT pk_tkmst_signa_catatans PRIMARY KEY (catatan)
);

-- Index untuk filter aktif
CREATE INDEX idx_tkmst_signa_catatans_active ON tkmst_signa_catatans (active_status);

-- Komentar tabel/kolom
COMMENT ON TABLE  tkmst_signa_catatans               IS 'LOV catatan khusus signa e-resep rawat jalan';
COMMENT ON COLUMN tkmst_signa_catatans.catatan       IS 'Teks catatan khusus yang muncul di dropdown (PK)';
COMMENT ON COLUMN tkmst_signa_catatans.active_status IS '1 = aktif, 0 = nonaktif';

COMMIT;
