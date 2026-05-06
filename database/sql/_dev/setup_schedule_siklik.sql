-- ==========================================================================
-- setup_schedule_siklik.sql
--
-- Setup tabel jadwal dokter di Oracle siklik. Diperlukan oleh komponen
-- Livewire booking-rj (port dari sirus-php82) untuk validasi quota saat
-- pasien daftar antrean Mobile JKN.
--
-- 3 objek yang dibuat:
--   1. SCMST_SCDAYS   — master hari (Senin–Minggu)
--   2. SCMST_SCPOLIS  — master jadwal poli per dokter per hari
--   3. SCVIEW_SCPOLIS — view denormalized untuk lookup quota cepat
--
-- Cara deploy:
--   sqlplus siklik/siklik @database/sql/_dev/setup_schedule_siklik.sql
--
-- Dependency: RSMST_DOCTORS, RSMST_POLIS sudah ada di siklik (untuk subselect view).
-- Idempoten: TIDAK — re-run akan kena ORA-00955 (object exists).
--           Drop manual dulu kalau perlu re-deploy.
-- ==========================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE;
SET DEFINE OFF;

-- =========================================================
-- 1. SCMST_SCDAYS — master hari
-- =========================================================
CREATE TABLE SCMST_SCDAYS (
    DAY_ID    NUMBER(1)     NOT NULL,
    DAY_DESC  VARCHAR2(20),
    CONSTRAINT PK_SCMST_SCDAYS PRIMARY KEY (DAY_ID)
);

INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (1, 'SENIN');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (2, 'SELASA');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (3, 'RABU');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (4, 'KAMIS');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (5, 'JUMAT');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (6, 'SABTU');
INSERT INTO SCMST_SCDAYS (DAY_ID, DAY_DESC) VALUES (7, 'MINGGU');

-- =========================================================
-- 2. SCMST_SCPOLIS — master jadwal poli per dokter
--
-- Catatan kolom:
--   SC_POLI_STATUS_      '1' = aktif (siklik convention)
--   SC_POLI_KET          label range jam, mis. '08:00-12:00'
--   DAY_ID               FK ke SCMST_SCDAYS
--   POLI_ID              FK ke RSMST_POLIS
--   DR_ID                FK ke RSMST_DOCTORS (VARCHAR — leading zero possible)
--   SHIFT                1=pagi, 2=sore (ikut RSTXN_SHIFTCTLS)
--   MULAI_PRAKTEK        format 'HH:MM:SS' (VARCHAR — bukan DATE/TIMESTAMP,
--                        supaya bisa direct compare ke string di app)
--   SELESAI_PRAKTEK      format 'HH:MM:SS'
--   PELAYANAN_PERP_ASIEN menit per pasien (boleh NULL)
--   NO_URUT              sequence kalau dokter punya >1 slot di hari yg sama
--   KUOTA                quota antrean per slot
-- =========================================================
CREATE TABLE SCMST_SCPOLIS (
    SC_POLI_STATUS_       VARCHAR2(3),
    SC_POLI_KET           VARCHAR2(50),
    DAY_ID                NUMBER(1)     NOT NULL,
    POLI_ID               NUMBER        NOT NULL,
    DR_ID                 VARCHAR2(15)  NOT NULL,
    SHIFT                 NUMBER(1),
    MULAI_PRAKTEK         VARCHAR2(8),
    PELAYANAN_PERP_ASIEN  NUMBER,
    NO_URUT               NUMBER,
    KUOTA                 NUMBER,
    SELESAI_PRAKTEK       VARCHAR2(8)
);

CREATE INDEX IX_SCMST_SCPOLIS_LOOKUP
    ON SCMST_SCPOLIS (POLI_ID, DR_ID, DAY_ID, MULAI_PRAKTEK, SELESAI_PRAKTEK);

-- Seed data — 32 jadwal sample dari sirus.
-- (Ganti / tambah sesuai jadwal real klinik kamu)
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-12:00', 5,  3, '1111', 1, '08:00:00', NULL, 1, 40, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-12:00', 6, 11, '041',  1, '08:00:00', NULL, 1, 40, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '10:15-13:15', 4, 11, '063',  1, '10:15:00', NULL, 1, 30, '13:15:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '10:15-13:15', 1, 11, '063',  1, '10:15:00', NULL, 1, 30, '13:15:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '09:00-12:30', 5,  3, '055',  1, '09:00:00', NULL, 1, 35, '12:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-12:00', 7, 16, '104',  1, '08:00:00', NULL, 1, 40, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-15:00', 5,  5, '010',  1, '14:00:00', NULL, 1, 10, '15:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-18:00', 4,  3, '055',  1, '14:00:00', NULL, 1, 40, '18:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-11:30', 6, 16, '104',  1, '08:00:00', NULL, 1, 35, '11:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-12:00', 6, 16, '067',  1, '08:00:00', NULL, 1, 40, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '06:00-12:00', 7, 25, '107',  1, '06:00:00', NULL, 1, 50, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '10:00-13:00', 1,  6, '085',  1, '10:00:00', NULL, 1, 30, '13:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:00-19:00', 1,  6, '090',  2, '16:00:00', NULL, 1, 30, '19:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:20-21:00', 2,  7, '112',  2, '16:20:00', NULL, 1, 40, '21:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '15:30-19:30', 3, 24, '089',  2, '15:30:00', NULL, 1, 40, '19:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:15-23:55', 4, 14, '045',  2, '16:15:00', NULL, 1, 47, '23:55:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:00-18:00', 5, 15, '086',  2, '16:00:00', NULL, 1, 20, '18:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:00-21:00', 7,  1, '008',  2, '16:00:00', NULL, 1, 50, '21:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-16:00', 2,  5, '010',  1, '14:00:00', NULL, 1, 20, '16:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '11:00-15:00', 3,  3, '055',  1, '11:00:00', NULL, 1, 40, '15:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:30-20:00', 3,  3, '106',  2, '16:30:00', NULL, 1, 35, '20:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '19:10-21:00', 4, 12, '082',  2, '19:10:00', NULL, 1, 18, '21:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:30-21:00', 5, 16, '067',  2, '16:30:00', NULL, 1, 45, '21:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-17:00', 6, 25, '107',  2, '14:00:00', NULL, 2, 30, '17:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '16:30-21:30', 1,  7, '088',  2, '16:30:00', NULL, 1, 38, '21:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '15:30-19:30', 2, 24, '089',  2, '15:30:00', NULL, 1, 40, '19:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:30-19:30', 5,  4, '015',  2, '14:30:00', NULL, 1, 50, '19:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '07:00-14:00', 6,  1, '078',  1, '07:00:00', NULL, 1, 70, '14:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '08:00-12:00', 6,  1, '077',  1, '08:00:00', NULL, 1, 40, '12:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-16:00', 1,  5, '010',  1, '14:00:00', NULL, 1, 20, '16:00:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '14:00-17:30', 2,  3, '055',  1, '14:00:00', NULL, 1, 35, '17:30:00');
INSERT INTO SCMST_SCPOLIS VALUES ('1', '19:10-21:00', 3, 12, '082',  2, '19:10:00', NULL, 1, 18, '21:00:00');

-- =========================================================
-- 3. SCVIEW_SCPOLIS — view denormalized
-- (mirror persis dari sirus, dependency: RSMST_DOCTORS + RSMST_POLIS)
-- =========================================================
CREATE OR REPLACE FORCE VIEW SCVIEW_SCPOLIS (
    SC_POLI_STATUS_, SC_POLI_KET, DAY_ID, DAY_DESC, POLI_ID, DR_ID, SHIFT,
    MULAI_PRAKTEK, SELESAI_PRAKTEK, PELAYANAN_PERP_ASIEN, NO_URUT, KUOTA,
    KD_POLI_BPJS, KD_DR_BPJS, DR_NAME, POLI_DESC
) AS
SELECT
    a.SC_POLI_STATUS_,
    a.SC_POLI_KET,
    a.DAY_ID,
    b.DAY_DESC,
    a.POLI_ID,
    a.DR_ID,
    a.SHIFT,
    a.MULAI_PRAKTEK,
    a.SELESAI_PRAKTEK,
    a.PELAYANAN_PERP_ASIEN,
    a.NO_URUT,
    a.KUOTA,
    (SELECT kd_poli_bpjs FROM rsmst_polis   WHERE poli_id = a.poli_id) kd_poli_bpjs,
    (SELECT kd_dr_bpjs   FROM rsmst_doctors WHERE dr_id   = a.dr_id)   kd_dr_bpjs,
    (SELECT dr_name      FROM rsmst_doctors WHERE dr_id   = a.dr_id)   dr_name,
    (SELECT poli_desc    FROM rsmst_polis   WHERE poli_id = a.poli_id) poli_desc
FROM SCMST_SCPOLIS a, SCMST_SCDAYS b
WHERE a.day_id = b.day_id;

COMMIT;

PROMPT ===========================================================
PROMPT  Setup schedule siklik selesai.
PROMPT  Verifikasi:
PROMPT    SELECT COUNT(*) FROM SCMST_SCDAYS;     -- expect 7
PROMPT    SELECT COUNT(*) FROM SCMST_SCPOLIS;    -- expect 32
PROMPT    SELECT COUNT(*) FROM SCVIEW_SCPOLIS;   -- expect 32
PROMPT ===========================================================

EXIT;
