-- ============================================================
-- Alter  : USERS — kolom tracking aktivitas user
-- Dipakai: middleware TrackUserActivity (update throttled 1 menit)
--          + halaman Sistem → User Online
-- Database : Oracle
-- ============================================================

ALTER TABLE users ADD (
    last_seen_at    DATE,
    last_seen_route VARCHAR2(150)
);

COMMENT ON COLUMN users.last_seen_at    IS 'Waktu aktivitas terakhir user (di-update middleware, throttle 1 menit)';
COMMENT ON COLUMN users.last_seen_route IS 'Route name halaman terakhir yang dilihat user';

COMMIT;
