-- =============================================================================
-- File   : install_bundle.sql
-- Tujuan : Bundle SEMUA SQL wajib siklik-php82 dalam 1 file.
--          Cocok dipakai setelah refresh user/schema dari server prod.
--
--          Isi (urutan):
--            01  Laravel system tables (SESSIONS, CACHE, CACHE_LOCKS, JOBS, JOB_BATCHES)
--            02  Mark 18 migration sebagai sudah-jalan
--            09  REF_BPJS_TABLE (cache BPJS PCare)
--            11  USERS — rename EMP_ID → KASIR_ID (atau create kalau belum ada)
--            12  SKTXN_SOWHS + RE-CREATE view SKVIEW_IOSTOCKWHS
--            13  SKTXN_RJACCDOCS — tambah kolom DR_ID + FK
--
--          ❌ Tidak termasuk: SatuSehat (file 03–08). Run terpisah pakai
--             `install_bundle_satusehat.sql` kalau klinik mau aktifkan
--             integrasi SatuSehat (LOINC + SNOMED).
--
-- Cara pakai (di server):
--   sqlplus siklik/<password>@//<host>:1521/<service> @install_bundle.sql
--
-- Idempotent  ✅ — semua section di-guard dgn existence check.
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
PROMPT ║  SIKLIK-PHP82 INSTALL BUNDLE — START                       ║
PROMPT ╚════════════════════════════════════════════════════════════╝


-- =============================================================================
-- SECTION 01 — Laravel system tables (idempotent wrap)
-- =============================================================================
PROMPT
PROMPT ─── [1/6] Laravel system tables ──────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    -- SESSIONS
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SESSIONS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE sessions (
                id              VARCHAR2(255)  NOT NULL,
                user_id         NUMBER(19),
                ip_address      VARCHAR2(45),
                user_agent      CLOB,
                payload         CLOB           NOT NULL,
                last_activity   NUMBER(10)     NOT NULL,
                CONSTRAINT pk_sessions PRIMARY KEY (id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_sessions_user_id       ON sessions (user_id)';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_sessions_last_activity ON sessions (last_activity)';
        DBMS_OUTPUT.PUT_LINE('  ✓ SESSIONS created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ SESSIONS already exists — skip.');
    END IF;

    -- CACHE
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'CACHE';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE cache (
                "KEY"        VARCHAR2(255)  NOT NULL,
                value        CLOB           NOT NULL,
                expiration   NUMBER(10)     NOT NULL,
                CONSTRAINT pk_cache PRIMARY KEY ("KEY")
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  ✓ CACHE created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ CACHE already exists — skip.');
    END IF;

    -- CACHE_LOCKS
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'CACHE_LOCKS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE cache_locks (
                "KEY"        VARCHAR2(255)  NOT NULL,
                owner        VARCHAR2(255)  NOT NULL,
                expiration   NUMBER(10)     NOT NULL,
                CONSTRAINT pk_cache_locks PRIMARY KEY ("KEY")
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  ✓ CACHE_LOCKS created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ CACHE_LOCKS already exists — skip.');
    END IF;

    -- JOBS + sequence
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'JOBS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE jobs (
                id            NUMBER(19)     NOT NULL,
                queue         VARCHAR2(255)  NOT NULL,
                payload       CLOB           NOT NULL,
                attempts      NUMBER(3)      NOT NULL,
                reserved_at   NUMBER(10),
                available_at  NUMBER(10)     NOT NULL,
                created_at    NUMBER(10)     NOT NULL,
                CONSTRAINT pk_jobs PRIMARY KEY (id)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_jobs_queue ON jobs (queue)';
        DBMS_OUTPUT.PUT_LINE('  ✓ JOBS created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ JOBS already exists — skip.');
    END IF;

    SELECT COUNT(*) INTO v_count FROM user_sequences WHERE sequence_name = 'JOBS_SEQ';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE SEQUENCE jobs_seq START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE';
        DBMS_OUTPUT.PUT_LINE('  ✓ jobs_seq created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ jobs_seq already exists — skip.');
    END IF;

    -- JOB_BATCHES
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'JOB_BATCHES';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE job_batches (
                id              VARCHAR2(255)  NOT NULL,
                name            VARCHAR2(255)  NOT NULL,
                total_jobs      NUMBER(10)     NOT NULL,
                pending_jobs    NUMBER(10)     NOT NULL,
                failed_jobs     NUMBER(10)     NOT NULL,
                failed_job_ids  CLOB           NOT NULL,
                options         CLOB,
                cancelled_at    NUMBER(10),
                created_at      NUMBER(10)     NOT NULL,
                finished_at     NUMBER(10),
                CONSTRAINT pk_job_batches PRIMARY KEY (id)
            )
        ]';
        DBMS_OUTPUT.PUT_LINE('  ✓ JOB_BATCHES created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ JOB_BATCHES already exists — skip.');
    END IF;
END;
/

-- Trigger jobs_bi (CREATE OR REPLACE = native idempotent)
CREATE OR REPLACE TRIGGER jobs_bi
    BEFORE INSERT ON jobs
    FOR EACH ROW
BEGIN
    IF :NEW.id IS NULL THEN
        SELECT jobs_seq.NEXTVAL INTO :NEW.id FROM dual;
    END IF;
END;
/

COMMIT;


-- =============================================================================
-- SECTION 02 — Mark migrations as already-run
-- =============================================================================
PROMPT
PROMPT ─── [2/6] Mark migrations as run ─────────────────────────────

DECLARE
    v_max_id   NUMBER := 0;
    v_count    NUMBER := 0;
    v_inserted NUMBER := 0;

    TYPE name_array IS TABLE OF VARCHAR2(255);
    migs name_array := name_array(
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2026_01_27_091529_create_permission_tables',
        '2026_04_28_133318_create_rsmst_educations_table',
        '2026_04_28_140001_create_rsmst_jobs_table',
        '2026_04_28_140002_create_rsmst_klaimtypes_table',
        '2026_04_28_140003_create_rsmst_entrytypes_table',
        '2026_04_28_140004_create_rsmst_accdocs_table',
        '2026_04_28_140005_create_rsmst_actemps_table',
        '2026_04_28_140006_create_rsmst_actparamedics_table',
        '2026_04_28_140007_create_tkmst_categories_table',
        '2026_04_28_140008_create_tkmst_uoms_table',
        '2026_04_28_140009_create_tkmst_kasirs_table',
        '2026_04_28_150001_create_rsmst_outs_table',
        '2026_04_28_150002_create_rsmst_mstprocedures_table',
        '2026_04_28_150003_create_rsmst_parameters_table',
        '2026_04_28_150004_create_immst_contents_table'
    );
BEGIN
    SELECT NVL(MAX(id), 0) INTO v_max_id FROM migrations;
    DBMS_OUTPUT.PUT_LINE('  Starting from migrations.id=' || v_max_id);

    FOR i IN 1 .. migs.COUNT LOOP
        SELECT COUNT(*) INTO v_count FROM migrations WHERE migration = migs(i);
        IF v_count = 0 THEN
            v_max_id := v_max_id + 1;
            INSERT INTO migrations (id, migration, batch) VALUES (v_max_id, migs(i), 1);
            v_inserted := v_inserted + 1;
            DBMS_OUTPUT.PUT_LINE('  [INSERT] id=' || v_max_id || '  ' || migs(i));
        ELSE
            DBMS_OUTPUT.PUT_LINE('  [SKIP]   ' || migs(i));
        END IF;
    END LOOP;

    DBMS_OUTPUT.PUT_LINE('  Total inserted: ' || v_inserted || ' / ' || migs.COUNT);
    COMMIT;
END;
/


-- =============================================================================
-- SECTION 09 — REF_BPJS_TABLE (cache BPJS PCare)
-- =============================================================================
PROMPT
PROMPT ─── [3/6] REF_BPJS_TABLE ─────────────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'REF_BPJS_TABLE';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE ref_bpjs_table (
                ref_keterangan VARCHAR2(100 CHAR) NOT NULL,
                ref_json       CLOB,
                CONSTRAINT pk_ref_bpjs_table PRIMARY KEY (ref_keterangan)
            )
        ]';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE  ref_bpjs_table IS 'Cache lokal reference BPJS PCare (di-sync via Master > Ref BPJS).']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN ref_bpjs_table.ref_keterangan IS 'Label kategori, mis. "Kesadaran", "Alergi Makanan", "Prognosa", "PoliFktp".']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN ref_bpjs_table.ref_json       IS 'CLOB JSON-encoded list dari response BPJS PCare (response.list).']';
        DBMS_OUTPUT.PUT_LINE('  ✓ REF_BPJS_TABLE created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ REF_BPJS_TABLE already exists — skip.');
    END IF;
    COMMIT;
END;
/


-- =============================================================================
-- SECTION 11 — USERS rename EMP_ID → KASIR_ID
-- =============================================================================
PROMPT
PROMPT ─── [4/6] USERS.KASIR_ID ─────────────────────────────────────

DECLARE
    has_emp   NUMBER := 0;
    has_kasir NUMBER := 0;
BEGIN
    SELECT COUNT(*) INTO has_emp   FROM user_tab_columns WHERE table_name='USERS' AND column_name='EMP_ID';
    SELECT COUNT(*) INTO has_kasir FROM user_tab_columns WHERE table_name='USERS' AND column_name='KASIR_ID';

    IF has_emp = 1 AND has_kasir = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE users RENAME COLUMN emp_id TO kasir_id';
        DBMS_OUTPUT.PUT_LINE('  ✓ EMP_ID renamed → KASIR_ID.');
    ELSIF has_emp = 1 AND has_kasir = 1 THEN
        EXECUTE IMMEDIATE 'UPDATE users SET kasir_id = emp_id WHERE kasir_id IS NULL AND emp_id IS NOT NULL';
        EXECUTE IMMEDIATE 'ALTER TABLE users DROP COLUMN emp_id';
        DBMS_OUTPUT.PUT_LINE('  ✓ EMP_ID copied to KASIR_ID then dropped.');
    ELSIF has_emp = 0 AND has_kasir = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE users ADD (kasir_id VARCHAR2(25))';
        DBMS_OUTPUT.PUT_LINE('  ✓ KASIR_ID added.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ KASIR_ID already exists — skip.');
    END IF;

    EXECUTE IMMEDIATE q'[COMMENT ON COLUMN users.kasir_id IS 'FK ke SKMST_KASIRS.kasir_id (mapping user Laravel ke kasir). Nullable.']';
    COMMIT;
END;
/


-- =============================================================================
-- SECTION 12 — SKTXN_SOWHS + view SKVIEW_IOSTOCKWHS
-- =============================================================================
PROMPT
PROMPT ─── [5/6] SKTXN_SOWHS + SKVIEW_IOSTOCKWHS ───────────────────

-- Precondition check
DECLARE
    v_missing  VARCHAR2(500) := '';
    v_count    NUMBER;
    PROCEDURE check_table(p_name VARCHAR2) IS
    BEGIN
        SELECT COUNT(*) INTO v_count FROM USER_TABLES WHERE TABLE_NAME = p_name;
        IF v_count = 0 THEN
            v_missing := v_missing || p_name || ', ';
        END IF;
    END;
BEGIN
    check_table('SKMST_PRODUCTS');
    check_table('SKMST_KASIRS');
    check_table('SKTXN_RCVHDRS');
    check_table('SKTXN_RCVDTLS');
    check_table('SKTXN_SLSHDRS');
    check_table('SKTXN_SLSDTLS');
    check_table('SKTXN_RJHDRS');
    check_table('SKTXN_RJOBATS');

    IF LENGTH(v_missing) > 0 THEN
        RAISE_APPLICATION_ERROR(-20001,
            'Precondition gagal — tabel berikut belum ada: ' || RTRIM(v_missing, ', '));
    END IF;
    DBMS_OUTPUT.PUT_LINE('  ✓ Semua tabel reference sudah ada.');
END;
/

-- SKTXN_SOWHS — create kalau belum ada. Kalau sudah ada, SKIP (preserve data opname).
-- View di-recreate selalu (lihat di bawah) — grants ke DITOKOKU ter-preserve.
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM USER_TABLES WHERE TABLE_NAME = 'SKTXN_SOWHS';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE SKTXN_SOWHS (
                SO_NO       NUMBER          NOT NULL,
                PRODUCT_ID  VARCHAR2(20)    NOT NULL,
                SO_DATE     DATE            DEFAULT SYSDATE,
                SO_D        NUMBER          DEFAULT 0,
                SO_K        NUMBER          DEFAULT 0,
                KASIR_ID    VARCHAR2(20),
                SO_DESC     VARCHAR2(100)   DEFAULT 'SO',
                CONSTRAINT PK_TKTXN_SOWHS PRIMARY KEY (SO_NO)
            )
        ]';
        EXECUTE IMMEDIATE 'ALTER TABLE SKTXN_SOWHS ADD CONSTRAINT FK_TKTXN_SOWHS_PRODUCT FOREIGN KEY (PRODUCT_ID) REFERENCES SKMST_PRODUCTS(PRODUCT_ID)';
        EXECUTE IMMEDIATE 'ALTER TABLE SKTXN_SOWHS ADD CONSTRAINT FK_TKTXN_SOWHS_KASIR   FOREIGN KEY (KASIR_ID)   REFERENCES SKMST_KASIRS(KASIR_ID)';
        EXECUTE IMMEDIATE 'CREATE INDEX IDX_TKTXN_SOWHS_PRODUCT_DATE ON SKTXN_SOWHS(PRODUCT_ID, SO_DATE)';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE  SKTXN_SOWHS            IS 'Stock Opname Warehouse — catat selisih hasil opname per produk. INSERT-only.']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.SO_NO      IS 'PK auto-increment via NVL(MAX(so_no),0)+1 di app']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.PRODUCT_ID IS 'FK → SKMST_PRODUCTS']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.SO_DATE    IS 'Tanggal opname (default SYSDATE)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.SO_D       IS 'Debit/Masuk — fisik LEBIH dari catatan']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.SO_K       IS 'Kredit/Keluar — fisik KURANG dari catatan']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.KASIR_ID   IS 'FK → SKMST_KASIRS']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN SKTXN_SOWHS.SO_DESC    IS 'Default ''SO''']';
        DBMS_OUTPUT.PUT_LINE('  ✓ SKTXN_SOWHS created (table + 2 FK + index + comments).');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ SKTXN_SOWHS already exists — skip table create (data preserved).');
    END IF;
END;
/

-- View (CREATE OR REPLACE = idempotent native, grants ke DITOKOKU ter-preserve)
CREATE OR REPLACE FORCE VIEW SKVIEW_IOSTOCKWHS
    ("PRODUCT_ID", "TXN_STATUS", "QTY_D", "QTY_K", "TXN_DATE", "TXN_NO", "PRODUCT_NAME") AS
(
    SELECT b.product_id, 'RCV', SUM(qty), 0, rcv_date, a.rcv_no, product_name
    FROM SKTXN_RCVHDRS a, SKTXN_RCVDTLS b, SKMST_PRODUCTS c
    WHERE a.rcv_no = b.rcv_no
        AND rcv_status NOT IN ('A','F')
        AND b.product_id = c.product_id
    GROUP BY b.product_id, 'RCV', 0, rcv_date, a.rcv_no, product_name

    UNION ALL

    SELECT b.product_id, 'SLS', 0, SUM(qty), sls_date, a.sls_no, product_name
    FROM SKTXN_SLSDTLS b, SKTXN_SLSHDRS a, SKMST_PRODUCTS c
    WHERE a.sls_no = b.sls_no
        AND b.product_id = c.product_id
        AND sls_status NOT IN ('A','F')
    GROUP BY b.product_id, 'SLS', 0, sls_date, a.sls_no, product_name

    UNION ALL

    SELECT b.product_id, 'RJ', 0, SUM(qty), rj_date, a.rj_no, product_name
    FROM SKTXN_RJOBATS b, SKTXN_RJHDRS a, SKMST_PRODUCTS c
    WHERE a.rj_no = b.rj_no
        AND b.product_id = c.product_id
        AND rj_status NOT IN ('A','F')
    GROUP BY b.product_id, 'RJ', 0, rj_date, a.rj_no, product_name

    UNION ALL

    SELECT s.product_id, 'SO', NVL(s.so_d, 0), NVL(s.so_k, 0),
           s.so_date, s.so_no, c.product_name
    FROM SKTXN_SOWHS s, SKMST_PRODUCTS c
    WHERE s.product_id = c.product_id
);

COMMIT;


-- =============================================================================
-- SECTION 13 — SKTXN_RJACCDOCS tambah kolom DR_ID + FK
-- =============================================================================
PROMPT
PROMPT ─── [6/6] SKTXN_RJACCDOCS.DR_ID ──────────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    -- Precondition
    SELECT COUNT(*) INTO v_count FROM USER_TABLES WHERE TABLE_NAME = 'SKTXN_RJACCDOCS';
    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Tabel SKTXN_RJACCDOCS belum ada.');
    END IF;

    SELECT COUNT(*) INTO v_count FROM USER_TABLES WHERE TABLE_NAME = 'SKMST_DOCTORS';
    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Tabel SKMST_DOCTORS (FK target) belum ada.');
    END IF;

    -- Add column DR_ID
    SELECT COUNT(*) INTO v_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'SKTXN_RJACCDOCS' AND COLUMN_NAME = 'DR_ID';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE SKTXN_RJACCDOCS ADD (DR_ID VARCHAR2(9))';
        DBMS_OUTPUT.PUT_LINE('  ✓ Kolom DR_ID ditambahkan.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ Kolom DR_ID sudah ada — skip.');
    END IF;

    -- Add FK
    SELECT COUNT(*) INTO v_count
    FROM USER_CONSTRAINTS
    WHERE TABLE_NAME = 'SKTXN_RJACCDOCS' AND CONSTRAINT_NAME = 'FK_RSTXN_RJACCDOCS_DR';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE SKTXN_RJACCDOCS '
         || 'ADD CONSTRAINT FK_RSTXN_RJACCDOCS_DR '
         || 'FOREIGN KEY (DR_ID) REFERENCES SKMST_DOCTORS(DR_ID)';
        DBMS_OUTPUT.PUT_LINE('  ✓ FK FK_RSTXN_RJACCDOCS_DR ditambahkan.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ FK FK_RSTXN_RJACCDOCS_DR sudah ada — skip.');
    END IF;
END;
/

COMMENT ON COLUMN SKTXN_RJACCDOCS.DR_ID IS
    'FK → SKMST_DOCTORS.DR_ID — dokter yg lakukan jasa medik di detail RJ';

COMMIT;


-- =============================================================================
-- VERIFY — final sanity check
-- =============================================================================
PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  VERIFY                                                    ║
PROMPT ╚════════════════════════════════════════════════════════════╝

PROMPT
PROMPT === Tabel sistem (harus ada 7 baris: 5 Laravel + REF_BPJS + SKTXN_SOWHS) ===
SELECT table_name FROM user_tables
 WHERE table_name IN ('SESSIONS','CACHE','CACHE_LOCKS','JOBS','JOB_BATCHES',
                      'REF_BPJS_TABLE','SKTXN_SOWHS')
 ORDER BY table_name;

PROMPT
PROMPT === USERS.KASIR_ID (harus ada 1 baris) ===
SELECT column_name, data_type || '(' || data_length || ')' AS data_type
FROM user_tab_columns
WHERE table_name = 'USERS' AND column_name IN ('EMP_ID','KASIR_ID');

PROMPT
PROMPT === SKTXN_RJACCDOCS.DR_ID (harus ada 1 baris) ===
SELECT column_name, data_type || '(' || data_length || ')' AS data_type
FROM user_tab_columns
WHERE table_name = 'SKTXN_RJACCDOCS' AND column_name = 'DR_ID';

PROMPT
PROMPT === MIGRATIONS count (harus >= 18) ===
SELECT COUNT(*) AS migration_count FROM migrations;

PROMPT
PROMPT === SKVIEW_IOSTOCKWHS valid (harus VALID) ===
SELECT view_name, status FROM user_views u
JOIN user_objects o ON o.object_name = u.view_name AND o.object_type = 'VIEW'
WHERE u.view_name = 'SKVIEW_IOSTOCKWHS';

PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  ✓ INSTALL BUNDLE SELESAI                                  ║
PROMPT ╚════════════════════════════════════════════════════════════╝

EXIT
