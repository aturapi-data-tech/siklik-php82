-- =============================================================================
-- File   : install_bundle_satusehat.sql
-- Tujuan : Bundle SQL fitur SatuSehat (LOINC + SNOMED) dalam 1 file.
--          Companion ke install_bundle.sql — run hanya kalau klinik mau
--          aktifkan integrasi SatuSehat (kirim FHIR ke Kemenkes).
--
--          Isi (urutan):
--            03  SKMST_SNOMED_CODES (cache SNOMED CT) + seed
--            04  SKMST_LOINC_CODES (cache LOINC) + seed lab
--            05  SKMST_CLABITEMS — tambah kolom LOINC + Kid range
--            06  SKMST_RADIOLOGIS — tambah kolom LOINC + mapping
--            07  SKMST_LOINC_CODES — seed entry radiologi
--            08  SKMST_CLABITEMS — mapping LOINC ke item lab
--
--          ❌ Tidak termasuk: 05b (patch lama untuk DB yg pakai 05 versi
--             pre-kid-range — sudah covered di section 03 bundle ini).
--
-- Cara pakai (di server):
--   sqlplus siklik/<password>@//<host>:1521/<service> @install_bundle_satusehat.sql
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
PROMPT ║  SIKLIK-PHP82 SATUSEHAT BUNDLE — START                     ║
PROMPT ╚════════════════════════════════════════════════════════════╝


-- =============================================================================
-- SECTION 03A — SKMST_SNOMED_CODES (table + indexes + comments)
-- =============================================================================
PROMPT
PROMPT ─── [1/6] SKMST_SNOMED_CODES (struct) ────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_SNOMED_CODES';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE skmst_snomed_codes (
                snomed_code   VARCHAR2(20)   NOT NULL,
                display_en    VARCHAR2(500)  NOT NULL,
                display_id    VARCHAR2(500),
                value_set     VARCHAR2(50)   DEFAULT 'condition-code' NOT NULL,
                created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_rsmst_snomed_codes PRIMARY KEY (snomed_code)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_snomed_value_set  ON skmst_snomed_codes (value_set)';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_snomed_display_en ON skmst_snomed_codes (UPPER(display_en))';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_snomed_display_id ON skmst_snomed_codes (UPPER(display_id))';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE  skmst_snomed_codes IS 'Cache data SNOMED CT dari FHIR server untuk LOV keluhan utama, alergi, dll']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_snomed_codes.snomed_code IS 'Kode SNOMED CT (contoh: 21522001)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_snomed_codes.display_en  IS 'Nama Inggris dari FHIR server (otomatis)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_snomed_codes.display_id  IS 'Nama Indonesia (diisi manual/admin)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_snomed_codes.value_set   IS 'Jenis: condition-code, procedure-code, substance-code, dll']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_snomed_codes.created_at  IS 'Waktu pertama kali di-cache']';
        DBMS_OUTPUT.PUT_LINE('  ✓ SKMST_SNOMED_CODES created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ SKMST_SNOMED_CODES already exists — skip create.');
    END IF;
END;
/


-- =============================================================================
-- SECTION 03B — SKMST_SNOMED_CODES seed (condition / substance / procedure)
-- =============================================================================
PROMPT
PROMPT ─── [1/6] SKMST_SNOMED_CODES (seed) ──────────────────────────

DECLARE
    v_count NUMBER;
    v_inserted NUMBER := 0;
BEGIN
    SELECT COUNT(*) INTO v_count FROM skmst_snomed_codes;
    IF v_count = 0 THEN
        -- Keluhan umum (condition-code)
        INSERT ALL
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('21522001',  'Abdominal pain',                        'Nyeri perut',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('25064002',  'Headache',                              'Sakit kepala',             'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386661006', 'Fever',                                 'Demam',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('49727002',  'Cough',                                 'Batuk',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267036007', 'Dyspnea',                               'Sesak napas',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('422587007', 'Nausea',                                'Mual',                     'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('422400008', 'Vomiting',                              'Muntah',                   'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('62315008',  'Diarrhea',                              'Diare',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271807003', 'Eruption of skin',                      'Ruam kulit',               'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('161891005', 'Backache',                              'Nyeri punggung',           'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('29857009',  'Chest pain',                            'Nyeri dada',               'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('84229001',  'Fatigue',                               'Kelelahan',                'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('404640003', 'Dizziness',                             'Pusing',                   'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('68962001',  'Muscle pain',                           'Nyeri otot',               'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('57676002',  'Joint pain',                            'Nyeri sendi',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162397003', 'Pain in throat',                        'Sakit tenggorokan',        'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('14760008',  'Constipation',                          'Sembelit',                 'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271825005', 'Respiratory distress',                  'Gangguan pernapasan',      'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('3006004',   'Disturbance of consciousness',          'Gangguan kesadaran',       'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267060006', 'Swelling of limb',                      'Bengkak ekstremitas',      'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271757001', 'Palpitations',                          'Jantung berdebar',         'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('193462001', 'Insomnia',                              'Susah tidur',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('79890006',  'Loss of appetite',                      'Tidak nafsu makan',        'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('44169009',  'Loss of sensation',                     'Mati rasa',                'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('246636008', 'Hematuria',                             'Kencing berdarah',         'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('82991003',  'Generalized aches and pains',           'Pegal-pegal',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91175000',  'Seizure',                               'Kejang',                   'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('23924001',  'Tight chest',                           'Dada terasa berat',        'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('248490000', 'Bloating of abdomen',                   'Perut kembung',            'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('267102003', 'Sore mouth',                            'Sariawan',                 'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('74776002',  'Itching of skin',                       'Gatal-gatal',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('64531003',  'Nasal discharge',                       'Pilek',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('56018004',  'Wheezing',                              'Mengi',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('60862001',  'Tinnitus',                              'Telinga berdenging',       'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('246677007', 'Blurred vision',                        'Penglihatan kabur',        'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('225549006', 'Difficulty walking',                    'Sulit berjalan',           'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('40739000',  'Dysphagia',                             'Sulit menelan',            'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('103001002', 'Feeling faint',                         'Rasa mau pingsan',         'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('95385002',  'Sneezing',                              'Bersin-bersin',            'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('301354004', 'Pain of lower limb',                    'Nyeri kaki',               'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162607003', 'Cough with sputum',                     'Batuk berdahak',           'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('247592009', 'Poor appetite',                         'Nafsu makan menurun',      'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('271681002', 'Stomachache',                           'Sakit perut',              'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('126485001', 'Urticaria',                             'Biduran',                  'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('409668002', 'Photophobia',                           'Silau',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('162116003', 'Loss of weight',                        'Berat badan turun',        'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('8943002',   'Weight gain',                           'Berat badan naik',         'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('22253000',  'Pain',                                  'Nyeri',                    'condition-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('182888003', 'Excessive sweating',                    'Keringat berlebih',        'condition-code', SYSDATE)
        SELECT 1 FROM DUAL;
        v_inserted := v_inserted + SQL%ROWCOUNT;

        -- Alergi umum (substance-code)
        INSERT ALL
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372687004', 'Amoxicillin',                           'Amoksisilin',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('7034005',   'Diclofenac',                            'Diklofenak',               'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387207008', 'Ibuprofen',                             'Ibuprofen',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387517004', 'Paracetamol',                           'Parasetamol',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('373270004', 'Penicillin',                            'Penisilin',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387170002', 'Ciprofloxacin',                         'Siprofloksasin',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372840008', 'Cephalosporin',                         'Sefalosporin',             'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387104009', 'Ceftriaxone',                           'Seftriakson',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('363246002', 'Erythromycin',                          'Eritromisin',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387293003', 'Metformin',                             'Metformin',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387362001', 'Amlodipine',                            'Amlodipin',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386872004', 'Captopril',                             'Kaptopril',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372756006', 'Sulfamethoxazole',                      'Sulfametoksazol',          'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387501005', 'Metronidazole',                         'Metronidazol',             'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372709008', 'Ketoconazole',                          'Ketokonazol',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387060005', 'Ranitidine',                            'Ranitidin',                'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('372665008', 'Aspirin',                               'Aspirin',                  'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('96067008',  'Seafood allergy',                       'Alergi seafood',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91935009',  'Allergy to peanut',                     'Alergi kacang',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91934008',  'Allergy to nut',                        'Alergi kacang-kacangan',   'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418689008', 'Allergy to grass pollen',               'Alergi serbuk sari',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232347008', 'Allergy to dust mite',                  'Alergi tungau debu',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('424213003', 'Allergy to latex',                      'Alergi lateks',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294505008', 'Allergy to contrast media',             'Alergi kontras',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91936005',  'Allergy to penicillin',                 'Alergi penisilin',         'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91930004',  'Allergy to egg',                        'Alergi telur',             'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('417532002', 'Allergy to fish',                       'Alergi ikan',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('300913006', 'Allergy to shrimp',                     'Alergi udang',             'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('735029006', 'Allergy to crab',                       'Alergi kepiting',          'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('414285001', 'Allergy to food',                       'Alergi makanan',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('425525006', 'Allergy to dairy product',              'Alergi susu/produk susu',  'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('89811004',  'Allergy to gluten',                     'Alergi gluten',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('91937001',  'Allergy to shellfish',                  'Alergi kerang',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('300916003', 'Allergy to chocolate',                  'Alergi cokelat',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418184004', 'Allergy to soy protein',                'Alergi protein kedelai',   'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('782555009', 'Allergy to cow milk protein',           'Alergi protein susu sapi', 'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('390952000', 'Allergy to dust',                       'Alergi debu',              'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('419474003', 'Allergy to mold',                       'Alergi jamur/kapang',      'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232350006', 'Allergy to cat dander',                 'Alergi bulu kucing',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('232349006', 'Allergy to dog dander',                 'Alergi bulu anjing',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('735030001', 'Cold urticaria',                        'Alergi udara dingin',      'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('402387002', 'Allergic contact dermatitis',           'Dermatitis kontak alergi', 'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294716003', 'Allergy to sulfonamide',                'Alergi sulfonamida',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293586001', 'Allergy to codeine',                    'Alergi kodein',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293584003', 'Allergy to morphine',                   'Alergi morfin',            'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('294921000', 'Allergy to tetracycline',               'Alergi tetrasiklin',       'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293963004', 'Allergy to gentamicin',                 'Alergi gentamisin',        'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('293747003', 'Allergy to insulin',                    'Alergi insulin',           'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418038007', 'Allergy to propylene glycol',           'Alergi propilen glikol',   'substance-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('418325008', 'Allergy to adhesive plaster',           'Alergi plester',           'substance-code', SYSDATE)
        SELECT 1 FROM DUAL;
        v_inserted := v_inserted + SQL%ROWCOUNT;

        -- Tindakan umum (procedure-code)
        INSERT ALL
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('182813001', 'Emergency treatment',                   'Penanganan darurat',       'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('225358003', 'Wound care',                            'Perawatan luka',           'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('274474001', 'Bone fracture treatment',               'Penanganan patah tulang',  'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('33195004',  'Removal of foreign body',               'Pengambilan benda asing',  'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('18949003',  'Change of dressing',                    'Ganti perban',             'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('387713003', 'Surgical procedure',                    'Prosedur bedah',           'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('71388002',  'Procedure',                             'Prosedur',                 'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('14768001',  'Suturing of wound',                     'Penjahitan luka',          'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('74770003',  'Splinting',                             'Pembidaian',               'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('430193006', 'Medication administration',             'Pemberian obat',           'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('45211000',  'Insertion of catheter',                 'Pemasangan kateter',       'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('397619005', 'Injection',                             'Injeksi',                  'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386637004', 'Infusion',                              'Infus',                    'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('82078001',  'Taking of blood specimen',              'Pengambilan darah',        'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('363680008', 'Radiographic imaging',                  'Rontgen',                  'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('16310003',  'Ultrasonography',                       'USG',                      'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('29303009',  'Electrocardiographic procedure',        'EKG',                      'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('241615005', 'Magnetic resonance imaging',            'MRI',                      'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('77477000',  'Computerized axial tomography',         'CT Scan',                  'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('40701008',  'Echocardiography',                      'Ekokardiografi',           'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('386746003', 'Endoscopy',                             'Endoskopi',                'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('73761001',  'Colonoscopy',                           'Kolonoskopi',              'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('44608003',  'Tonsillectomy',                         'Tonsilektomi',             'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('80146002',  'Appendectomy',                          'Apendektomi',              'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('11466000',  'Cesarean section',                      'Operasi sesar',            'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('176795006', 'Circumcision',                          'Sunat',                    'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('27114001',  'Tooth extraction',                      'Cabut gigi',               'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('274031008', 'Hemodialysis',                          'Hemodialisis',             'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('35025007',  'Manual reduction of fracture',          'Reposisi patah tulang',    'procedure-code', SYSDATE)
            INTO skmst_snomed_codes (snomed_code, display_en, display_id, value_set, created_at) VALUES ('5765007',   'Debridement of wound',                  'Debridemen luka',          'procedure-code', SYSDATE)
        SELECT 1 FROM DUAL;
        v_inserted := v_inserted + SQL%ROWCOUNT;

        COMMIT;
        DBMS_OUTPUT.PUT_LINE('  ✓ Seeded ' || v_inserted || ' SNOMED codes (condition+substance+procedure).');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ skmst_snomed_codes already has ' || v_count || ' rows — skip seed.');
    END IF;
END;
/


-- =============================================================================
-- SECTION 04A — SKMST_LOINC_CODES (table + indexes + comments)
-- =============================================================================
PROMPT
PROMPT ─── [2/6] SKMST_LOINC_CODES (struct) ─────────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_LOINC_CODES';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE q'[
            CREATE TABLE skmst_loinc_codes (
                loinc_code    VARCHAR2(20)   NOT NULL,
                display       VARCHAR2(500)  NOT NULL,
                display_id    VARCHAR2(500),
                component     VARCHAR2(200),
                loinc_class   VARCHAR2(100),
                created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_rsmst_loinc_codes PRIMARY KEY (loinc_code)
            )
        ]';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_loinc_display    ON skmst_loinc_codes (UPPER(display))';
        EXECUTE IMMEDIATE 'CREATE INDEX idx_loinc_display_id ON skmst_loinc_codes (UPPER(display_id))';
        EXECUTE IMMEDIATE q'[COMMENT ON TABLE  skmst_loinc_codes IS 'Cache data LOINC untuk LOV pemeriksaan lab (Satu Sehat)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_loinc_codes.loinc_code  IS 'Kode LOINC (contoh: 718-7)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_loinc_codes.display     IS 'Nama resmi LOINC dalam bahasa Inggris']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_loinc_codes.display_id  IS 'Nama Indonesia (diisi manual/admin)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_loinc_codes.component   IS 'Komponen LOINC (contoh: Hemoglobin)']';
        EXECUTE IMMEDIATE q'[COMMENT ON COLUMN skmst_loinc_codes.loinc_class IS 'Kelas LOINC (contoh: HEM/BC, CHEM, UA)']';
        DBMS_OUTPUT.PUT_LINE('  ✓ SKMST_LOINC_CODES created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ SKMST_LOINC_CODES already exists — skip create.');
    END IF;
END;
/


-- =============================================================================
-- SECTION 04B — SKMST_LOINC_CODES seed (laboratorium)
-- =============================================================================
PROMPT
PROMPT ─── [2/6] SKMST_LOINC_CODES (seed lab) ───────────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM skmst_loinc_codes WHERE loinc_class IN ('HEM/BC','CHEM','SERO','MICRO','UA');
    IF v_count = 0 THEN
        INSERT ALL
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('58410-2', 'CBC panel - Blood by Automated count',                             'Panel Darah Lengkap',       'CBC panel',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('57021-8', 'CBC W Ordered Manual Differential panel - Blood',                  'Panel DL 5 Diff',           'CBC W Diff panel',   'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('718-7',   'Hemoglobin [Mass/volume] in Blood',                                'Hemoglobin',                'Hemoglobin',         'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('789-8',   'Erythrocytes [#/volume] in Blood by Automated count',              'Eritrosit',                 'Erythrocytes',       'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('6690-2',  'Leukocytes [#/volume] in Blood by Automated count',               'Leukosit',                  'Leukocytes',         'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('777-3',   'Platelets [#/volume] in Blood by Automated count',                'Trombosit',                 'Platelets',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4544-3',  'Hematocrit [Volume Fraction] of Blood',                            'Hematokrit',                'Hematocrit',         'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4537-7',  'Erythrocyte sedimentation rate',                                   'LED',                       'ESR',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('713-8',   'Eosinophils/100 leukocytes in Blood',                              'Eosinofil %',               'Eosinophils',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('706-2',   'Basophils/100 leukocytes in Blood',                                'Basofil %',                 'Basophils',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('770-8',   'Neutrophils/100 leukocytes in Blood',                              'Neutrofil %',               'Neutrophils',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('736-9',   'Lymphocytes/100 leukocytes in Blood',                              'Limfosit %',                'Lymphocytes',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5905-5',  'Monocytes/100 leukocytes in Blood',                                'Monosit %',                 'Monocytes',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('731-0',   'Lymphocytes [#/volume] in Blood',                                  'Limfosit #',                'Lymphocytes',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('751-8',   'Neutrophils [#/volume] in Blood',                                  'Neutrofil #',               'Neutrophils',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('742-7',   'Monocytes [#/volume] in Blood',                                    'Monosit #',                 'Monocytes',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('711-2',   'Eosinophils [#/volume] in Blood',                                  'Eosinofil #',               'Eosinophils',        'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('704-7',   'Basophils [#/volume] in Blood',                                    'Basofil #',                 'Basophils',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('787-2',   'MCV [Entitic volume]',                                             'MCV',                       'MCV',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('785-6',   'MCH [Entitic mass]',                                               'MCH',                       'MCH',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('786-4',   'MCHC [Mass/volume]',                                               'MCHC',                      'MCHC',               'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('788-0',   'Erythrocyte distribution width [Ratio] by Automated count',        'RDW-CV',                    'RDW',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('21000-5', 'Erythrocyte distribution width [Entitic volume]',                  'RDW-SD',                    'RDW',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32207-3', 'Platelet distribution width [Entitic volume]',                     'PDW',                       'PDW',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32623-1', 'Platelet mean volume [Entitic volume]',                            'MPV',                       'MPV',                'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('37854-8', 'Plateletcrit [Volume Fraction] in Blood',                          'PCT (Plateletcrit)',        'Plateletcrit',       'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('49497-1', 'Platelets large [#/volume] in Blood',                              'P-LCC',                     'Platelets.large',    'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('71260-4', 'Platelets large/100 platelets in Blood',                           'P-LRC',                     'Platelets.large',    'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('17849-1', 'Reticulocytes/100 erythrocytes in Blood',                          'Retikulosit',               'Reticulocytes',      'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3184-9',  'Coagulation tissue factor induced.clot time',                     'CT (Clotting Time)',        'CT',                 'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11067-6', 'Bleeding time',                                                    'BT (Bleeding Time)',        'Bleeding time',      'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1558-6',  'Fasting glucose [Mass/volume] in Serum or Plasma',                'Gula Darah Puasa',          'Glucose',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1521-4',  'Glucose [Mass/volume] in Serum or Plasma --2 hours post meal',    'Gula Darah 2 Jam PP',       'Glucose',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2339-0',  'Glucose [Mass/volume] in Blood',                                   'Gula Darah Sewaktu',        'Glucose',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('4548-4',  'Hemoglobin A1c/Hemoglobin.total in Blood',                         'HbA1c',                     'Hemoglobin A1c',     'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2571-8',  'Triglyceride [Mass/volume] in Serum or Plasma',                    'Trigliserida',              'Triglyceride',       'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2093-3',  'Cholesterol [Mass/volume] in Serum or Plasma',                     'Kolesterol Total',          'Cholesterol',        'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2085-9',  'HDL Cholesterol [Mass/volume] in Serum or Plasma',                'HDL Kolesterol',            'HDL Cholesterol',    'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2089-1',  'LDL Cholesterol [Mass/volume] in Serum or Plasma',                'LDL Kolesterol',            'LDL Cholesterol',    'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1920-8',  'Aspartate aminotransferase [Enzymatic activity/volume] in Serum or Plasma', 'SGOT',             'AST',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1742-6',  'Alanine aminotransferase [Enzymatic activity/volume] in Serum or Plasma',   'SGPT',             'ALT',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1975-2',  'Bilirubin.total [Mass/volume] in Serum or Plasma',                'Bilirubin Total',           'Bilirubin.total',    'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1968-7',  'Bilirubin.direct [Mass/volume] in Serum or Plasma',               'Bilirubin Direk',           'Bilirubin.direct',   'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1971-1',  'Bilirubin.indirect [Mass/volume] in Serum or Plasma',             'Bilirubin Indirek',         'Bilirubin.indirect', 'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1751-7',  'Albumin [Mass/volume] in Serum or Plasma',                         'Albumin',                   'Albumin',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2885-2',  'Protein [Mass/volume] in Serum or Plasma',                         'Total Protein',             'Protein',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('10834-0', 'Globulin [Mass/volume] in Serum by calculation',                  'Globulin',                  'Globulin',           'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('6768-6',  'Alkaline phosphatase [Enzymatic activity/volume] in Serum or Plasma', 'Alkaline Fosfatase',    'ALP',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2324-2',  'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma', 'Gamma GT',         'GGT',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('24363-4', 'Renal function panel - Serum or Plasma',                          'Panel Fungsi Ginjal',       'Renal function',     'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3091-6',  'Urea nitrogen [Mass/volume] in Serum or Plasma',                  'Ureum',                     'Urea nitrogen',      'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3094-0',  'Urea nitrogen [Mass/volume] in Serum or Plasma',                  'BUN',                       'BUN',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2160-0',  'Creatinine [Mass/volume] in Serum or Plasma',                     'Kreatinin',                 'Creatinine',         'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3084-1',  'Urate [Mass/volume] in Serum or Plasma',                           'Asam Urat',                 'Urate',              'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2823-3',  'Potassium [Moles/volume] in Serum or Plasma',                     'Kalium',                    'Potassium',          'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2951-2',  'Sodium [Moles/volume] in Serum or Plasma',                        'Natrium',                   'Sodium',             'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2075-0',  'Chloride [Moles/volume] in Serum or Plasma',                      'Klorida',                   'Chloride',           'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('49563-0', 'Troponin I.cardiac [Mass/volume] in Serum or Plasma',             'Troponin I',                'Troponin I',         'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('32673-6', 'Creatine kinase.MB [Mass/volume] in Serum or Plasma',             'CK-MB',                     'CK-MB',              'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('48065-7', 'Fibrin D-dimer FEU [Mass/volume] in Platelet poor plasma',        'D-Dimer',                   'D-dimer',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('75241-0', 'Procalcitonin [Mass/volume] in Serum or Plasma',                  'Procalcitonin',             'Procalcitonin',      'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3016-3',  'Thyrotropin [Units/volume] in Serum or Plasma',                   'TSH',                       'TSH',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3053-6',  'Triiodothyronine (T3) [Moles/volume] in Serum or Plasma',         'T3',                        'T3',                 'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3026-2',  'Thyroxine (T4) [Moles/volume] in Serum or Plasma',                'T4',                        'T4',                 'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('3024-7',  'Thyroxine (T4) free [Moles/volume] in Serum or Plasma',           'FT4',                       'T4 free',            'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2039-6',  'Carcinoembryonic Ag [Mass/volume] in Serum or Plasma',            'CEA',                       'CEA',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2986-8',  'Testosterone [Mass/volume] in Serum or Plasma',                   'Testosteron',               'Testosterone',       'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5195-3',  'Hepatitis B virus surface Ag [Presence] in Serum',                'HBsAg',                     'HBsAg',              'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('68961-2', 'HIV 1+2 Ab [Presence] in Serum or Plasma',                        'Anti HIV',                  'HIV 1+2 Ab',         'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('20507-0', 'Treponema pallidum Ab [Presence] in Serum',                       'Anti Sifilis',              'Treponema pallidum', 'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('90423-5', 'HIV 1+2 Ab and HIV1 p24 Ag panel - Serum or Plasma',             'Panel HIV/Syphilis',        'HIV+Syphilis',       'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5765-5',  'Salmonella typhi O Ab [Titer] in Serum',                          'S. Typhi O',                'S.typhi O Ab',       'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5764-8',  'Salmonella typhi H Ab [Titer] in Serum',                          'S. Typhi H',                'S.typhi H Ab',       'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5758-0',  'Salmonella paratyphi A Ab [Titer] in Serum',                      'S. Paratyphi A',            'S.paratyphi A Ab',   'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5760-6',  'Salmonella paratyphi B Ab [Titer] in Serum',                      'S. Paratyphi B',            'S.paratyphi B Ab',   'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('69668-2', 'Salmonella sp Ab panel - Serum',                                  'Panel Anti Salmonella',     'Salmonella Ab',      'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('75377-2', 'Dengue virus NS1 Ag [Presence] in Serum by Immunoassay',         'Dengue NS1',                'Dengue NS1 Ag',      'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('29676-4', 'Dengue virus IgG Ab [Presence] in Serum',                        'Dengue IgG',                'Dengue IgG',         'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('29504-8', 'Dengue virus IgM Ab [Presence] in Serum',                        'Dengue IgM',                'Dengue IgM',         'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('56888-1', 'Toxoplasma gondii Ab panel - Serum',                              'Panel Toxoplasma',          'Toxoplasma Ab',      'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('8039-1',  'Toxoplasma gondii IgG Ab [Units/volume] in Serum',               'Toxoplasma IgG',            'Toxoplasma IgG',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('8040-9',  'Toxoplasma gondii IgM Ab [Units/volume] in Serum',               'Toxoplasma IgM',            'Toxoplasma IgM',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('40674-4', 'Leptospira sp IgG Ab [Presence] in Serum',                       'Leptospira IgG',            'Leptospira IgG',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('40675-1', 'Leptospira sp IgM Ab [Presence] in Serum',                       'Leptospira IgM',            'Leptospira IgM',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94500-6', 'SARS-CoV-2 RNA [Presence] in Respiratory specimen by NAA',       'PCR SARS-CoV-2',            'SARS-CoV-2 RNA',     'MICRO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('95209-3', 'SARS-CoV-2 Ag [Presence] in Respiratory specimen by Rapid immunoassay', 'Rapid Antigen',       'SARS-CoV-2 Ag',      'MICRO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94563-4', 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma',               'Anti SARS-CoV-2 IgG',       'SARS-CoV-2 IgG',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('94564-2', 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma',               'Anti SARS-CoV-2 IgM',       'SARS-CoV-2 IgM',     'SERO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11545-1', 'Mycobacterium sp identified in Specimen by Acid fast stain',     'BTA (Basil Tahan Asam)',    'Mycobacterium',      'MICRO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('51587-4', 'Plasmodium falciparum Ag [Presence] in Blood',                   'P. Falciparum',             'P.falciparum Ag',    'MICRO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('51588-2', 'Plasmodium vivax Ag [Presence] in Blood',                        'P. Vivax',                  'P.vivax Ag',         'MICRO')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('24362-6', 'Urinalysis complete panel - Urine',                               'Panel Urin Lengkap',        'Urinalysis panel',   'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5770-3',  'Albumin [Presence] in Urine by Test strip',                      'Albumin Urin',              'Albumin',            'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('1977-8',  'Bilirubin [Presence] in Urine by Test strip',                    'Bilirubin Urin',            'Bilirubin',          'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5792-7',  'Glucose [Presence] in Urine by Test strip',                      'Reduksi / Glukosa Urin',    'Glucose',            'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5818-0',  'Urobilinogen [Presence] in Urine by Test strip',                 'Urobilinogen',              'Urobilinogen',       'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5797-6',  'Ketones [Presence] in Urine by Test strip',                      'Keton Urin',                'Ketones',            'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5802-4',  'Nitrite [Presence] in Urine by Test strip',                      'Nitrit Urin',               'Nitrite',            'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5808-1',  'Erythrocytes [#/area] in Urine sediment by Microscopy',          'Eritrosit Urin',            'Erythrocytes',       'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('5821-4',  'Leukocytes [#/area] in Urine sediment by Microscopy',            'Leukosit Urin',             'Leukocytes',         'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('11277-1', 'Epithelial cells [#/area] in Urine sediment by Microscopy',      'Epitel Urin',               'Epithelial cells',   'UA')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('883-9',   'ABO group [Type] in Blood',                                       'Golongan Darah',            'ABO group',          'HEM/BC')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('2106-3',  'Choriogonadotropin (pregnancy test) [Presence] in Urine',        'Tes Kehamilan',             'hCG',                'CHEM')
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class) VALUES ('58408-6', 'Peripheral blood smear interpretation',                           'Hapusan Darah Tepi',        'Blood smear',        'HEM/BC')
        SELECT 1 FROM DUAL;
        COMMIT;
        DBMS_OUTPUT.PUT_LINE('  ✓ Seeded ' || SQL%ROWCOUNT || ' LOINC codes (lab).');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ skmst_loinc_codes already has ' || v_count || ' lab rows — skip seed.');
    END IF;
END;
/


-- =============================================================================
-- SECTION 05 — SKMST_CLABITEMS: tambah kolom LOINC + Kid range
-- =============================================================================
PROMPT
PROMPT ─── [3/6] SKMST_CLABITEMS columns ────────────────────────────

DECLARE
    v_count NUMBER;
    PROCEDURE add_col(p_col VARCHAR2, p_type VARCHAR2, p_comment VARCHAR2) IS
        v_n NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_n FROM user_tab_columns
         WHERE table_name='SKMST_CLABITEMS' AND column_name=p_col;
        IF v_n = 0 THEN
            EXECUTE IMMEDIATE 'ALTER TABLE skmst_clabitems ADD ' || p_col || ' ' || p_type;
            EXECUTE IMMEDIATE 'COMMENT ON COLUMN skmst_clabitems.' || p_col || ' IS ''' || p_comment || '''';
            DBMS_OUTPUT.PUT_LINE('  ✓ ' || p_col || ' added.');
        ELSE
            DBMS_OUTPUT.PUT_LINE('  ⚠ ' || p_col || ' already exists — skip.');
        END IF;
    END;
BEGIN
    -- Precondition
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_CLABITEMS';
    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Tabel SKMST_CLABITEMS belum ada di schema siklik.');
    END IF;

    add_col('LOINC_CODE',    'VARCHAR2(20)',   'Kode LOINC — paket header utk ServiceRequest/DiagnosticReport, item anak utk Observation');
    add_col('LOINC_DISPLAY', 'VARCHAR2(200)',  'Nama resmi LOINC (contoh: Hemoglobin [Mass/volume] in Blood)');
    add_col('LOW_LIMIT_K',   'NUMBER(15,2)',   'Batas bawah nilai normal untuk anak (Kid)');
    add_col('HIGH_LIMIT_K',  'NUMBER(15,2)',   'Batas atas nilai normal untuk anak (Kid)');

    -- Index lookup by LOINC
    SELECT COUNT(*) INTO v_count FROM user_indexes
     WHERE table_name='SKMST_CLABITEMS' AND index_name='IDX_CLABITEM_LOINC';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX idx_clabitem_loinc ON skmst_clabitems (loinc_code)';
        DBMS_OUTPUT.PUT_LINE('  ✓ idx_clabitem_loinc created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ idx_clabitem_loinc already exists — skip.');
    END IF;

    COMMIT;
END;
/


-- =============================================================================
-- SECTION 06A — SKMST_RADIOLOGIS columns + index
-- =============================================================================
PROMPT
PROMPT ─── [4/6] SKMST_RADIOLOGIS columns ───────────────────────────

DECLARE
    v_count NUMBER;
    PROCEDURE add_col(p_col VARCHAR2, p_type VARCHAR2, p_comment VARCHAR2) IS
        v_n NUMBER;
    BEGIN
        SELECT COUNT(*) INTO v_n FROM user_tab_columns
         WHERE table_name='SKMST_RADIOLOGIS' AND column_name=p_col;
        IF v_n = 0 THEN
            EXECUTE IMMEDIATE 'ALTER TABLE skmst_radiologis ADD ' || p_col || ' ' || p_type;
            EXECUTE IMMEDIATE 'COMMENT ON COLUMN skmst_radiologis.' || p_col || ' IS ''' || p_comment || '''';
            DBMS_OUTPUT.PUT_LINE('  ✓ ' || p_col || ' added.');
        ELSE
            DBMS_OUTPUT.PUT_LINE('  ⚠ ' || p_col || ' already exists — skip.');
        END IF;
    END;
BEGIN
    SELECT COUNT(*) INTO v_count FROM user_tables WHERE table_name = 'SKMST_RADIOLOGIS';
    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Tabel SKMST_RADIOLOGIS belum ada di schema siklik.');
    END IF;

    add_col('LOINC_CODE',    'VARCHAR2(20)',  'Kode LOINC — utk ServiceRequest/DiagnosticReport radiologi ke Satu Sehat');
    add_col('LOINC_DISPLAY', 'VARCHAR2(200)', 'Nama resmi LOINC (contoh: XR Chest 2 Views)');

    SELECT COUNT(*) INTO v_count FROM user_indexes
     WHERE table_name='SKMST_RADIOLOGIS' AND index_name='IDX_RAD_LOINC';
    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX idx_rad_loinc ON skmst_radiologis (loinc_code)';
        DBMS_OUTPUT.PUT_LINE('  ✓ idx_rad_loinc created.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ idx_rad_loinc already exists — skip.');
    END IF;

    COMMIT;
END;
/


-- =============================================================================
-- SECTION 06B — SKMST_RADIOLOGIS LOINC mapping (UPDATEs — idempotent)
-- =============================================================================
PROMPT
PROMPT ─── [4/6] SKMST_RADIOLOGIS mapping ───────────────────────────

-- Rontgen — Thorax
UPDATE skmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Chest 2 Views'                                  WHERE rad_id = 'R1';
UPDATE skmst_radiologis SET loinc_code = '36554-4', loinc_display = 'XR Chest Lateral'                                  WHERE rad_id = 'R2';
UPDATE skmst_radiologis SET loinc_code = '37439-8', loinc_display = 'XR Chest Oblique'                                  WHERE rad_id = 'R3';
UPDATE skmst_radiologis SET loinc_code = '37439-8', loinc_display = 'XR Chest Oblique'                                  WHERE rad_id = 'R22';
UPDATE skmst_radiologis SET loinc_code = '36687-2', loinc_display = 'XR Chest AP Lordotic'                              WHERE rad_id = 'R23';
UPDATE skmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Chest PA and Lateral'                           WHERE rad_id = 'R1036';
-- Rontgen — Abdomen
UPDATE skmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP'                                     WHERE rad_id = 'R4';
UPDATE skmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP and Lateral decubitus'               WHERE rad_id = 'R5';
UPDATE skmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP'                                     WHERE rad_id = 'R24';
UPDATE skmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen AP upright'                             WHERE rad_id = 'R55';
UPDATE skmst_radiologis SET loinc_code = '43462-6', loinc_display = 'XR Abdomen 3 Views'                                WHERE rad_id = 'R121';
-- Rontgen — Pelvis & Hip
UPDATE skmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis AP'                                      WHERE rad_id = 'R6';
UPDATE skmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis Frog leg'                                WHERE rad_id = 'R25';
UPDATE skmst_radiologis SET loinc_code = '37620-3', loinc_display = 'XR Pelvis Inlet and Outlet'                        WHERE rad_id = 'R125';
UPDATE skmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R57';
UPDATE skmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R58';
UPDATE skmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R115';
UPDATE skmst_radiologis SET loinc_code = '37181-6', loinc_display = 'XR Hip AP'                                         WHERE rad_id = 'R116';
UPDATE skmst_radiologis SET loinc_code = '37182-4', loinc_display = 'XR Hip Lateral'                                    WHERE rad_id = 'R117';
UPDATE skmst_radiologis SET loinc_code = '37182-4', loinc_display = 'XR Hip Lateral'                                    WHERE rad_id = 'R118';
-- Rontgen — Skull & Facial
UPDATE skmst_radiologis SET loinc_code = '36287-1', loinc_display = 'XR Skull AP'                                       WHERE rad_id = 'R30';
UPDATE skmst_radiologis SET loinc_code = '36288-9', loinc_display = 'XR Skull Lateral'                                  WHERE rad_id = 'R31';
UPDATE skmst_radiologis SET loinc_code = '37017-2', loinc_display = 'XR Sinuses Waters view'                            WHERE rad_id = 'R32';
UPDATE skmst_radiologis SET loinc_code = '36287-1', loinc_display = 'XR Skull submentovertex'                           WHERE rad_id = 'R33';
UPDATE skmst_radiologis SET loinc_code = '37339-0', loinc_display = 'XR Mandible AP'                                    WHERE rad_id = 'R7';
UPDATE skmst_radiologis SET loinc_code = '37339-0', loinc_display = 'XR Mandible'                                       WHERE rad_id = 'R46';
UPDATE skmst_radiologis SET loinc_code = '36957-0', loinc_display = 'XR Nasal bones Lateral'                            WHERE rad_id = 'R48';
UPDATE skmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid'                                        WHERE rad_id = 'R8';
UPDATE skmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid'                                        WHERE rad_id = 'R18';
UPDATE skmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid Stenvers'                               WHERE rad_id = 'R104';
UPDATE skmst_radiologis SET loinc_code = '36780-6', loinc_display = 'XR Mastoid Stenvers'                               WHERE rad_id = 'R105';
UPDATE skmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R9';
UPDATE skmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R14';
UPDATE skmst_radiologis SET loinc_code = '37901-7', loinc_display = 'XR Temporomandibular joint'                        WHERE rad_id = 'R209';
-- Rontgen — Spine
UPDATE skmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine AP'                              WHERE rad_id = 'R10';
UPDATE skmst_radiologis SET loinc_code = '36659-1', loinc_display = 'XR Cervical spine Lateral'                         WHERE rad_id = 'R108';
UPDATE skmst_radiologis SET loinc_code = '36660-9', loinc_display = 'XR Cervical spine Oblique'                         WHERE rad_id = 'R11';
UPDATE skmst_radiologis SET loinc_code = '36660-9', loinc_display = 'XR Cervical spine Oblique'                         WHERE rad_id = 'R50';
UPDATE skmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine AP and Lateral'                  WHERE rad_id = 'R1041';
UPDATE skmst_radiologis SET loinc_code = '36661-7', loinc_display = 'XR Cervical spine Odontoid AP'                     WHERE rad_id = 'R16';
UPDATE skmst_radiologis SET loinc_code = '36661-7', loinc_display = 'XR Cervical spine Odontoid AP'                     WHERE rad_id = 'R109';
UPDATE skmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine Flexion'                         WHERE rad_id = 'R13';
UPDATE skmst_radiologis SET loinc_code = '36657-5', loinc_display = 'XR Cervical spine Extension'                       WHERE rad_id = 'R112';
UPDATE skmst_radiologis SET loinc_code = '37944-7', loinc_display = 'XR Thoracic spine AP'                              WHERE rad_id = 'R101';
UPDATE skmst_radiologis SET loinc_code = '37945-4', loinc_display = 'XR Thoracic spine Lateral'                         WHERE rad_id = 'R103';
UPDATE skmst_radiologis SET loinc_code = '37944-7', loinc_display = 'XR Thoracic spine AP and Lateral'                  WHERE rad_id = 'R120';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine AP'                                WHERE rad_id = 'R100';
UPDATE skmst_radiologis SET loinc_code = '36591-6', loinc_display = 'XR Lumbar spine Lateral'                           WHERE rad_id = 'R102';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine AP and Lateral'                    WHERE rad_id = 'R1034';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Bending'                           WHERE rad_id = 'R12';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Bending'                           WHERE rad_id = 'R15';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Flexion'                           WHERE rad_id = 'R113';
UPDATE skmst_radiologis SET loinc_code = '36589-0', loinc_display = 'XR Lumbar spine Extension'                         WHERE rad_id = 'R114';
UPDATE skmst_radiologis SET loinc_code = '37682-3', loinc_display = 'XR Sacrum AP'                                      WHERE rad_id = 'R39';
UPDATE skmst_radiologis SET loinc_code = '37683-1', loinc_display = 'XR Sacrum Lateral'                                 WHERE rad_id = 'R40';
UPDATE skmst_radiologis SET loinc_code = '36697-1', loinc_display = 'XR Coccyx AP'                                      WHERE rad_id = 'R21';
UPDATE skmst_radiologis SET loinc_code = '36698-9', loinc_display = 'XR Coccyx Lateral'                                 WHERE rad_id = 'R54';
UPDATE skmst_radiologis SET loinc_code = '36697-1', loinc_display = 'XR Sacrococcyx AP'                                 WHERE rad_id = 'R110';
UPDATE skmst_radiologis SET loinc_code = '36698-9', loinc_display = 'XR Sacrococcyx Lateral'                            WHERE rad_id = 'R111';
-- Rontgen — Upper Extremity
UPDATE skmst_radiologis SET loinc_code = '36695-5', loinc_display = 'XR Clavicle'                                       WHERE rad_id = 'R34';
UPDATE skmst_radiologis SET loinc_code = '36695-5', loinc_display = 'XR Clavicle'                                       WHERE rad_id = 'R56';
UPDATE skmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder AP'                                    WHERE rad_id = 'R51';
UPDATE skmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder AP'                                    WHERE rad_id = 'R59';
UPDATE skmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder'                                       WHERE rad_id = 'R35';
UPDATE skmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder'                                       WHERE rad_id = 'R60';
UPDATE skmst_radiologis SET loinc_code = '37764-9', loinc_display = 'XR Shoulder Axial'                                 WHERE rad_id = 'R107';
UPDATE skmst_radiologis SET loinc_code = '37220-2', loinc_display = 'XR Humerus'                                        WHERE rad_id = 'R26';
UPDATE skmst_radiologis SET loinc_code = '37220-2', loinc_display = 'XR Humerus'                                        WHERE rad_id = 'R47';
UPDATE skmst_radiologis SET loinc_code = '36856-3', loinc_display = 'XR Elbow'                                          WHERE rad_id = 'R36';
UPDATE skmst_radiologis SET loinc_code = '36856-3', loinc_display = 'XR Elbow'                                          WHERE rad_id = 'R61';
UPDATE skmst_radiologis SET loinc_code = '37048-7', loinc_display = 'XR Forearm'                                        WHERE rad_id = 'R17';
UPDATE skmst_radiologis SET loinc_code = '37048-7', loinc_display = 'XR Forearm'                                        WHERE rad_id = 'R27';
UPDATE skmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R37';
UPDATE skmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R62';
UPDATE skmst_radiologis SET loinc_code = '37974-4', loinc_display = 'XR Wrist'                                          WHERE rad_id = 'R63';
UPDATE skmst_radiologis SET loinc_code = '37154-3', loinc_display = 'XR Hand'                                           WHERE rad_id = 'R38';
UPDATE skmst_radiologis SET loinc_code = '37154-3', loinc_display = 'XR Hand'                                           WHERE rad_id = 'R53';
-- Rontgen — Lower Extremity
UPDATE skmst_radiologis SET loinc_code = '37026-3', loinc_display = 'XR Femur'                                          WHERE rad_id = 'R28';
UPDATE skmst_radiologis SET loinc_code = '37026-3', loinc_display = 'XR Femur'                                          WHERE rad_id = 'R49';
UPDATE skmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee'                                           WHERE rad_id = 'R19';
UPDATE skmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee'                                           WHERE rad_id = 'R52';
UPDATE skmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee Skyline'                                   WHERE rad_id = 'R64';
UPDATE skmst_radiologis SET loinc_code = '37302-8', loinc_display = 'XR Knee Skyline'                                   WHERE rad_id = 'R65';
UPDATE skmst_radiologis SET loinc_code = '37917-3', loinc_display = 'XR Tibia and Fibula'                               WHERE rad_id = 'R20';
UPDATE skmst_radiologis SET loinc_code = '37917-3', loinc_display = 'XR Tibia and Fibula'                               WHERE rad_id = 'R29';
UPDATE skmst_radiologis SET loinc_code = '36619-5', loinc_display = 'XR Ankle'                                          WHERE rad_id = 'R41';
UPDATE skmst_radiologis SET loinc_code = '36619-5', loinc_display = 'XR Ankle'                                          WHERE rad_id = 'R67';
UPDATE skmst_radiologis SET loinc_code = '36639-3', loinc_display = 'XR Calcaneus'                                      WHERE rad_id = 'R42';
UPDATE skmst_radiologis SET loinc_code = '36639-3', loinc_display = 'XR Calcaneus'                                      WHERE rad_id = 'R70';
UPDATE skmst_radiologis SET loinc_code = '37042-0', loinc_display = 'XR Foot'                                           WHERE rad_id = 'R43';
UPDATE skmst_radiologis SET loinc_code = '37042-0', loinc_display = 'XR Foot'                                           WHERE rad_id = 'R68';
-- Rontgen — Kontras / Fluoroscopy
UPDATE skmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract IVP'                              WHERE rad_id = 'R44';
UPDATE skmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract IVP'                              WHERE rad_id = 'R45';
UPDATE skmst_radiologis SET loinc_code = '24850-8', loinc_display = 'XR Urinary tract Single shot IVP'                  WHERE rad_id = 'R80';
UPDATE skmst_radiologis SET loinc_code = '24745-0', loinc_display = 'RF Colon Barium enema'                             WHERE rad_id = 'R71';
UPDATE skmst_radiologis SET loinc_code = '24745-0', loinc_display = 'RF Colon Barium enema'                             WHERE rad_id = 'R81';
UPDATE skmst_radiologis SET loinc_code = '24852-4', loinc_display = 'RF Urethra Urethrography'                          WHERE rad_id = 'R72';
UPDATE skmst_radiologis SET loinc_code = '24695-7', loinc_display = 'RF Bladder Cystography'                            WHERE rad_id = 'R73';
UPDATE skmst_radiologis SET loinc_code = '24852-4', loinc_display = 'RF Urethra and Bladder Voiding cystourethrography' WHERE rad_id = 'R77';
UPDATE skmst_radiologis SET loinc_code = '24853-2', loinc_display = 'RF Uterus and Fallopian tubes HSG'                 WHERE rad_id = 'R74';
UPDATE skmst_radiologis SET loinc_code = '24853-2', loinc_display = 'RF Uterus and Fallopian tubes HSG'                 WHERE rad_id = 'H01';
UPDATE skmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Upper GI tract with barium'                     WHERE rad_id = 'R75';
UPDATE skmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Upper GI tract with barium'                     WHERE rad_id = 'R82';
UPDATE skmst_radiologis SET loinc_code = '24856-5', loinc_display = 'RF Esophagus with barium'                          WHERE rad_id = 'R76';
UPDATE skmst_radiologis SET loinc_code = '24626-2', loinc_display = 'RF Appendix with barium'                           WHERE rad_id = 'R78';
UPDATE skmst_radiologis SET loinc_code = '24626-2', loinc_display = 'RF Appendix with barium'                           WHERE rad_id = 'R79';
UPDATE skmst_radiologis SET loinc_code = '24855-7', loinc_display = 'RF Barium follow through'                          WHERE rad_id = 'R83';
-- Rontgen — Bayi & Lain-lain
UPDATE skmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Babygram'                                       WHERE rad_id = 'R119';
UPDATE skmst_radiologis SET loinc_code = '37629-4', loinc_display = 'XR Penis'                                          WHERE rad_id = 'R106';
-- USG
UPDATE skmst_radiologis SET loinc_code = '24590-2', loinc_display = 'US Head'                                           WHERE rad_id = 'U1';
UPDATE skmst_radiologis SET loinc_code = '24648-6', loinc_display = 'US Carotid artery Duplex'                          WHERE rad_id = 'U2';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen'                                        WHERE rad_id = 'U3';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen lower'                                  WHERE rad_id = 'U4';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen upper'                                  WHERE rad_id = 'U5';
UPDATE skmst_radiologis SET loinc_code = '24685-8', loinc_display = 'US Liver Doppler'                                  WHERE rad_id = 'U6';
UPDATE skmst_radiologis SET loinc_code = '24843-3', loinc_display = 'US Thyroid'                                        WHERE rad_id = 'U7';
UPDATE skmst_radiologis SET loinc_code = '24843-3', loinc_display = 'US Thyroid'                                        WHERE rad_id = 'U8';
UPDATE skmst_radiologis SET loinc_code = '24838-3', loinc_display = 'US Salivary gland'                                 WHERE rad_id = 'U9';
UPDATE skmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Urinary tract'                                  WHERE rad_id = 'U10';
UPDATE skmst_radiologis SET loinc_code = '24696-5', loinc_display = 'US Kidney Duplex'                                  WHERE rad_id = 'U11';
UPDATE skmst_radiologis SET loinc_code = '24696-5', loinc_display = 'US Kidney Duplex bilateral'                        WHERE rad_id = 'U12';
UPDATE skmst_radiologis SET loinc_code = '24604-9', loinc_display = 'US Breast unilateral'                              WHERE rad_id = 'U13';
UPDATE skmst_radiologis SET loinc_code = '24604-9', loinc_display = 'US Breast bilateral'                               WHERE rad_id = 'U14';
UPDATE skmst_radiologis SET loinc_code = '37432-3', loinc_display = 'US Axilla'                                         WHERE rad_id = 'U15';
UPDATE skmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis transvaginal'                            WHERE rad_id = 'U16';
UPDATE skmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis 4D'                                      WHERE rad_id = 'U17';
UPDATE skmst_radiologis SET loinc_code = '24841-7', loinc_display = 'US Scrotum'                                        WHERE rad_id = 'U18';
UPDATE skmst_radiologis SET loinc_code = '37432-3', loinc_display = 'US Inguinal'                                       WHERE rad_id = 'U19';
UPDATE skmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Lower extremity vein Duplex'                    WHERE rad_id = 'U20';
UPDATE skmst_radiologis SET loinc_code = '24754-2', loinc_display = 'US Musculoskeletal'                                WHERE rad_id = 'U21';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Doppler per organ'                              WHERE rad_id = 'U22';
UPDATE skmst_radiologis SET loinc_code = '24643-7', loinc_display = 'US Chest'                                          WHERE rad_id = 'U23';
UPDATE skmst_radiologis SET loinc_code = '24643-7', loinc_display = 'US Chest'                                          WHERE rad_id = 'U24';
UPDATE skmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Extremity vein Duplex'                          WHERE rad_id = 'U25';
UPDATE skmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Upper extremity Duplex'                         WHERE rad_id = 'U26';
UPDATE skmst_radiologis SET loinc_code = '24862-3', loinc_display = 'US Lower extremity Duplex'                         WHERE rad_id = 'U27';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Neck'                                           WHERE rad_id = 'U28';
-- USG — CITO
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen'                                        WHERE rad_id = 'R212';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen upper'                                  WHERE rad_id = 'R213';
UPDATE skmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen lower'                                  WHERE rad_id = 'R214';
UPDATE skmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Urinary tract'                                  WHERE rad_id = 'R215';
UPDATE skmst_radiologis SET loinc_code = '24841-7', loinc_display = 'US Scrotum'                                        WHERE rad_id = 'R216';
UPDATE skmst_radiologis SET loinc_code = '24854-0', loinc_display = 'US Pelvis'                                         WHERE rad_id = 'R217';
UPDATE skmst_radiologis SET loinc_code = '24685-8', loinc_display = 'US Abdomen Doppler'                                WHERE rad_id = 'R218';

COMMIT;


-- =============================================================================
-- SECTION 07 — SKMST_LOINC_CODES seed (radiologi)
-- =============================================================================
PROMPT
PROMPT ─── [5/6] SKMST_LOINC_CODES (seed radiologi) ─────────────────

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM skmst_loinc_codes WHERE loinc_class = 'RAD';
    IF v_count = 0 THEN
        INSERT ALL
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36643-5', 'XR Chest 2 Views',                        'Rontgen Dada PA/AP',        'Chest XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36554-4', 'XR Chest Lateral',                        'Rontgen Dada Lateral',      'Chest XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37439-8', 'XR Chest Oblique',                        'Rontgen Dada Oblique',      'Chest XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36687-2', 'XR Chest AP Lordotic',                    'Rontgen Dada Top Lordotic', 'Chest XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('43462-6', 'XR Abdomen AP',                           'Rontgen Perut (BOF)',       'Abdomen XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37620-3', 'XR Pelvis AP',                            'Rontgen Pelvis',            'Pelvis XR',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37181-6', 'XR Hip AP',                               'Rontgen Panggul AP',        'Hip XR',         'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37182-4', 'XR Hip Lateral',                          'Rontgen Panggul Lateral',   'Hip XR',         'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36287-1', 'XR Skull AP',                             'Rontgen Kepala AP',         'Skull XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36288-9', 'XR Skull Lateral',                        'Rontgen Kepala Lateral',    'Skull XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37017-2', 'XR Sinuses Waters view',                  'Rontgen Sinus Waters',      'Sinuses XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37339-0', 'XR Mandible AP',                          'Rontgen Mandibula',         'Mandible XR',    'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36957-0', 'XR Nasal bones Lateral',                  'Rontgen Hidung Lateral',    'Nasal XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36780-6', 'XR Mastoid',                              'Rontgen Mastoid',           'Mastoid XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37901-7', 'XR Temporomandibular joint',              'Rontgen TMJ',               'TMJ XR',         'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36657-5', 'XR Cervical spine AP',                    'Rontgen Cervikal',          'C-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36659-1', 'XR Cervical spine Lateral',               'Rontgen Cervikal Lateral',  'C-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36660-9', 'XR Cervical spine Oblique',               'Rontgen Cervikal Oblique',  'C-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36661-7', 'XR Cervical spine Odontoid AP',           'Rontgen Odontoid',          'C-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37944-7', 'XR Thoracic spine AP',                    'Rontgen Thorakal AP',       'T-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37945-4', 'XR Thoracic spine Lateral',               'Rontgen Thorakal Lateral',  'T-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36589-0', 'XR Lumbar spine AP',                      'Rontgen Lumbosakral AP',    'L-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36591-6', 'XR Lumbar spine Lateral',                 'Rontgen Lumbosakral Lat',   'L-Spine XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37682-3', 'XR Sacrum AP',                            'Rontgen Sakrum AP',         'Sacrum XR',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37683-1', 'XR Sacrum Lateral',                       'Rontgen Sakrum Lateral',    'Sacrum XR',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36697-1', 'XR Coccyx AP',                            'Rontgen Coccyx AP',         'Coccyx XR',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36698-9', 'XR Coccyx Lateral',                       'Rontgen Coccyx Lateral',    'Coccyx XR',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36695-5', 'XR Clavicle',                             'Rontgen Klavikula',         'Clavicle XR',    'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37764-9', 'XR Shoulder AP',                          'Rontgen Bahu',              'Shoulder XR',    'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37220-2', 'XR Humerus',                              'Rontgen Humerus',           'Humerus XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36856-3', 'XR Elbow',                                'Rontgen Siku',              'Elbow XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37048-7', 'XR Forearm',                              'Rontgen Lengan Bawah',      'Forearm XR',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37974-4', 'XR Wrist',                                'Rontgen Pergelangan',       'Wrist XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37154-3', 'XR Hand',                                 'Rontgen Tangan',            'Hand XR',        'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37026-3', 'XR Femur',                                'Rontgen Femur',             'Femur XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37302-8', 'XR Knee',                                 'Rontgen Lutut',             'Knee XR',        'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37917-3', 'XR Tibia and Fibula',                     'Rontgen Cruris',            'Tibia/Fibula',   'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36619-5', 'XR Ankle',                                'Rontgen Pergelangan Kaki',  'Ankle XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('36639-3', 'XR Calcaneus',                            'Rontgen Tumit',             'Calcaneus XR',   'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37042-0', 'XR Foot',                                 'Rontgen Kaki',              'Foot XR',        'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37629-4', 'XR Penis',                                'Rontgen Penis',             'Penis XR',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24850-8', 'XR Urinary tract IVP',                    'IVP',                       'IVP',            'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24745-0', 'RF Colon Barium enema',                   'Colon in Loop',             'Barium enema',   'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24852-4', 'RF Urethra Urethrography',                'Uretrografi',               'Urethrography',  'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24695-7', 'RF Bladder Cystography',                  'Sistografi',                'Cystography',    'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24853-2', 'RF Uterus and Fallopian tubes HSG',       'HSG',                       'HSG',            'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24855-7', 'RF Upper GI tract with barium',           'Upper GI',                  'Upper GI',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24856-5', 'RF Esophagus with barium',                'Oesofagografi',             'Esophagography', 'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24626-2', 'RF Appendix with barium',                 'Appendicografi',            'Appendicography','RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24590-2', 'US Head',                                 'USG Kepala',                'Head US',        'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24648-6', 'US Carotid artery Duplex',                'USG Doppler Karotis',       'Carotid US',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24558-9', 'US Abdomen',                              'USG Abdomen',               'Abdomen US',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24685-8', 'US Liver Doppler',                        'USG Hepar Doppler',         'Liver US',       'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24843-3', 'US Thyroid',                              'USG Tiroid',                'Thyroid US',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24838-3', 'US Salivary gland',                       'USG Kelenjar Liur',         'Salivary US',    'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24854-0', 'US Urinary tract',                        'USG Urologi',               'Urinary US',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24696-5', 'US Kidney Duplex',                        'USG Doppler Ginjal',        'Kidney US',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24604-9', 'US Breast',                               'USG Mammae',                'Breast US',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('37432-3', 'US Axilla',                               'USG Aksila',                'Axilla US',      'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24841-7', 'US Scrotum',                              'USG Testis/Skrotum',        'Scrotum US',     'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24862-3', 'US Lower extremity vein Duplex',          'USG Doppler Vena Ext',      'DVT US',         'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24754-2', 'US Musculoskeletal',                      'USG Muskuloskeletal',       'MSK US',         'RAD', SYSDATE)
            INTO skmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at) VALUES ('24643-7', 'US Chest',                                'USG Thorax',                'Chest US',       'RAD', SYSDATE)
        SELECT 1 FROM DUAL;
        COMMIT;
        DBMS_OUTPUT.PUT_LINE('  ✓ Seeded ' || SQL%ROWCOUNT || ' LOINC codes (radiologi).');
    ELSE
        DBMS_OUTPUT.PUT_LINE('  ⚠ skmst_loinc_codes already has ' || v_count || ' RAD rows — skip seed.');
    END IF;
END;
/


-- =============================================================================
-- SECTION 08 — SKMST_CLABITEMS LOINC mapping (UPDATEs — idempotent)
-- =============================================================================
PROMPT
PROMPT ─── [6/6] SKMST_CLABITEMS mapping ────────────────────────────

-- PAKET HEADER
UPDATE skmst_clabitems SET loinc_code = '58410-2', loinc_display = 'CBC panel - Blood by Automated count'                WHERE clabitem_id = 'HE00001';
UPDATE skmst_clabitems SET loinc_code = '57021-8', loinc_display = 'CBC W Ordered Manual Differential panel - Blood'     WHERE clabitem_id = 'HE00005';
UPDATE skmst_clabitems SET loinc_code = '24362-6', loinc_display = 'Urinalysis complete panel - Urine'                   WHERE clabitem_id = 'UR00030';
UPDATE skmst_clabitems SET loinc_code = '24331-1', loinc_display = 'Lipid 1996 panel - Serum or Plasma'                  WHERE clabitem_id = 'WI00021';
UPDATE skmst_clabitems SET loinc_code = '24362-6', loinc_display = 'Urinalysis complete panel - Urine'                   WHERE clabitem_id = 'MA00067';
UPDATE skmst_clabitems SET loinc_code = '24363-4', loinc_display = 'Renal function panel - Serum or Plasma'              WHERE clabitem_id = 'RF00026';
UPDATE skmst_clabitems SET loinc_code = '75377-2', loinc_display = 'Dengue virus NS1 Ag panel - Serum'                   WHERE clabitem_id = 'TE00080';
UPDATE skmst_clabitems SET loinc_code = '40675-1', loinc_display = 'Leptospira Ab panel - Serum'                         WHERE clabitem_id = 'LE00160';
UPDATE skmst_clabitems SET loinc_code = '56888-1', loinc_display = 'Toxoplasma gondii Ab panel - Serum'                  WHERE clabitem_id = 'TO00109';
UPDATE skmst_clabitems SET loinc_code = '69668-2', loinc_display = 'Salmonella sp Ab panel - Serum'                      WHERE clabitem_id = 'AN00150';
UPDATE skmst_clabitems SET loinc_code = '90423-5', loinc_display = 'HIV 1+2 Ab and HIV1 p24 Ag panel - Serum or Plasma'  WHERE clabitem_id = 'HI00156';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00104';

-- HEMATOLOGI 3 Diff (HE001)
UPDATE skmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HA00002';
UPDATE skmst_clabitems SET loinc_code = '789-8',   loinc_display = 'Erythrocytes [#/volume] in Blood by Automated count' WHERE clabitem_id = 'ER00003';
UPDATE skmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE00004';
UPDATE skmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE00005';
UPDATE skmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE00140';
UPDATE skmst_clabitems SET loinc_code = '713-8',   loinc_display = 'Eosinophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'EO00006';
UPDATE skmst_clabitems SET loinc_code = '706-2',   loinc_display = 'Basophils/100 leukocytes in Blood'                   WHERE clabitem_id = 'BA00007';
UPDATE skmst_clabitems SET loinc_code = '770-8',   loinc_display = 'Neutrophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'SE00009';
UPDATE skmst_clabitems SET loinc_code = '736-9',   loinc_display = 'Lymphocytes/100 leukocytes in Blood'                 WHERE clabitem_id = 'LI00010';
UPDATE skmst_clabitems SET loinc_code = '5905-5',  loinc_display = 'Monocytes/100 leukocytes in Blood'                   WHERE clabitem_id = 'MO00011';
UPDATE skmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR00012';
UPDATE skmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'HA00013';
UPDATE skmst_clabitems SET loinc_code = '731-0',   loinc_display = 'Lymphocytes [#/volume] in Blood'                     WHERE clabitem_id = 'LY00128';
UPDATE skmst_clabitems SET loinc_code = '751-8',   loinc_display = 'Neutrophils [#/volume] in Blood'                     WHERE clabitem_id = 'GR00130';
UPDATE skmst_clabitems SET loinc_code = '787-2',   loinc_display = 'MCV [Entitic volume]'                                WHERE clabitem_id = 'MC00131';
UPDATE skmst_clabitems SET loinc_code = '785-6',   loinc_display = 'MCH [Entitic mass]'                                  WHERE clabitem_id = 'MC001311';
UPDATE skmst_clabitems SET loinc_code = '786-4',   loinc_display = 'MCHC [Mass/volume]'                                  WHERE clabitem_id = 'MC00132';
UPDATE skmst_clabitems SET loinc_code = '788-0',   loinc_display = 'Erythrocyte distribution width [Ratio] by Automated count' WHERE clabitem_id = 'RD00133';
UPDATE skmst_clabitems SET loinc_code = '21000-5', loinc_display = 'Erythrocyte distribution width [Entitic volume]'     WHERE clabitem_id = 'RD00134';
UPDATE skmst_clabitems SET loinc_code = '32207-3', loinc_display = 'Platelet distribution width [Entitic volume]'        WHERE clabitem_id = 'PD00136';
UPDATE skmst_clabitems SET loinc_code = '32623-1', loinc_display = 'Platelet mean volume [Entitic volume]'               WHERE clabitem_id = 'MP00135';
UPDATE skmst_clabitems SET loinc_code = '37854-8', loinc_display = 'Plateletcrit [Volume Fraction] in Blood'             WHERE clabitem_id = 'PC00137';
UPDATE skmst_clabitems SET loinc_code = '49497-1', loinc_display = 'Platelets large [#/volume] in Blood'                 WHERE clabitem_id = 'P-00138';
UPDATE skmst_clabitems SET loinc_code = '71260-4', loinc_display = 'Platelets large/100 platelets in Blood'              WHERE clabitem_id = 'P-00139';
UPDATE skmst_clabitems SET loinc_code = '742-7',   loinc_display = 'Monocytes [#/volume] in Blood'                       WHERE clabitem_id = 'MI00129';

-- HEMATOLOGI 5 Diff (HE005)
UPDATE skmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HA500002';
UPDATE skmst_clabitems SET loinc_code = '789-8',   loinc_display = 'Erythrocytes [#/volume] in Blood by Automated count' WHERE clabitem_id = 'ER500003';
UPDATE skmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE500004';
UPDATE skmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE500005';
UPDATE skmst_clabitems SET loinc_code = '4537-7',  loinc_display = 'Erythrocyte sedimentation rate'                      WHERE clabitem_id = 'LE500140';
UPDATE skmst_clabitems SET loinc_code = '713-8',   loinc_display = 'Eosinophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'EO500006';
UPDATE skmst_clabitems SET loinc_code = '706-2',   loinc_display = 'Basophils/100 leukocytes in Blood'                   WHERE clabitem_id = 'BA500007';
UPDATE skmst_clabitems SET loinc_code = '770-8',   loinc_display = 'Neutrophils/100 leukocytes in Blood'                 WHERE clabitem_id = 'SE500009';
UPDATE skmst_clabitems SET loinc_code = '736-9',   loinc_display = 'Lymphocytes/100 leukocytes in Blood'                 WHERE clabitem_id = 'LI500010';
UPDATE skmst_clabitems SET loinc_code = '5905-5',  loinc_display = 'Monocytes/100 leukocytes in Blood'                   WHERE clabitem_id = 'MO500011';
UPDATE skmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR500012';
UPDATE skmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'HA500013';
UPDATE skmst_clabitems SET loinc_code = '731-0',   loinc_display = 'Lymphocytes [#/volume] in Blood'                     WHERE clabitem_id = 'LY500128';
UPDATE skmst_clabitems SET loinc_code = '751-8',   loinc_display = 'Neutrophils [#/volume] in Blood'                     WHERE clabitem_id = 'GR500130';
UPDATE skmst_clabitems SET loinc_code = '787-2',   loinc_display = 'MCV [Entitic volume]'                                WHERE clabitem_id = 'MC500131';
UPDATE skmst_clabitems SET loinc_code = '785-6',   loinc_display = 'MCH [Entitic mass]'                                  WHERE clabitem_id = 'MC5001311';
UPDATE skmst_clabitems SET loinc_code = '786-4',   loinc_display = 'MCHC [Mass/volume]'                                  WHERE clabitem_id = 'MC500132';
UPDATE skmst_clabitems SET loinc_code = '788-0',   loinc_display = 'Erythrocyte distribution width [Ratio]'              WHERE clabitem_id = 'RD500133';
UPDATE skmst_clabitems SET loinc_code = '21000-5', loinc_display = 'Erythrocyte distribution width [Entitic volume]'     WHERE clabitem_id = 'RD500134';
UPDATE skmst_clabitems SET loinc_code = '32207-3', loinc_display = 'Platelet distribution width [Entitic volume]'        WHERE clabitem_id = 'PD500136';
UPDATE skmst_clabitems SET loinc_code = '32623-1', loinc_display = 'Platelet mean volume [Entitic volume]'               WHERE clabitem_id = 'MP500135';
UPDATE skmst_clabitems SET loinc_code = '37854-8', loinc_display = 'Plateletcrit [Volume Fraction] in Blood'             WHERE clabitem_id = 'PC500137';
UPDATE skmst_clabitems SET loinc_code = '49497-1', loinc_display = 'Platelets large [#/volume] in Blood'                 WHERE clabitem_id = 'P5-00138';
UPDATE skmst_clabitems SET loinc_code = '71260-4', loinc_display = 'Platelets large/100 platelets in Blood'              WHERE clabitem_id = 'P5-00139';
UPDATE skmst_clabitems SET loinc_code = '742-7',   loinc_display = 'Monocytes [#/volume] in Blood'                       WHERE clabitem_id = 'MI500129';
UPDATE skmst_clabitems SET loinc_code = '711-2',   loinc_display = 'Eosinophils [#/volume] in Blood'                     WHERE clabitem_id = 'EO00205';
UPDATE skmst_clabitems SET loinc_code = '704-7',   loinc_display = 'Basophils [#/volume] in Blood'                       WHERE clabitem_id = 'BA00206';

-- KIMIA DARAH — Gula
UPDATE skmst_clabitems SET loinc_code = '1558-6',  loinc_display = 'Fasting glucose [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'GU00014';
UPDATE skmst_clabitems SET loinc_code = '1521-4',  loinc_display = 'Glucose [Mass/volume] in Serum or Plasma --2 hours post meal' WHERE clabitem_id = 'GU00015';
UPDATE skmst_clabitems SET loinc_code = '2339-0',  loinc_display = 'Glucose [Mass/volume] in Blood'                      WHERE clabitem_id = 'GU00016';
UPDATE skmst_clabitems SET loinc_code = '4548-4',  loinc_display = 'Hemoglobin A1c/Hemoglobin.total in Blood'            WHERE clabitem_id = 'HB00090';

-- KIMIA DARAH — Lipid
UPDATE skmst_clabitems SET loinc_code = '2571-8',  loinc_display = 'Triglyceride [Mass/volume] in Serum or Plasma'       WHERE clabitem_id = 'TR00017';
UPDATE skmst_clabitems SET loinc_code = '2093-3',  loinc_display = 'Cholesterol [Mass/volume] in Serum or Plasma'        WHERE clabitem_id = 'CH00018';
UPDATE skmst_clabitems SET loinc_code = '2085-9',  loinc_display = 'HDL Cholesterol [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'HD00019';
UPDATE skmst_clabitems SET loinc_code = '2089-1',  loinc_display = 'LDL Cholesterol [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'LD00020';

-- KIMIA DARAH — Fungsi Hati
UPDATE skmst_clabitems SET loinc_code = '1920-8',  loinc_display = 'Aspartate aminotransferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'SG00044';
UPDATE skmst_clabitems SET loinc_code = '1742-6',  loinc_display = 'Alanine aminotransferase [Enzymatic activity/volume] in Serum or Plasma'   WHERE clabitem_id = 'SG00045';
UPDATE skmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00075';
UPDATE skmst_clabitems SET loinc_code = '1968-7',  loinc_display = 'Bilirubin.direct [Mass/volume] in Serum or Plasma'   WHERE clabitem_id = 'BI00042';
UPDATE skmst_clabitems SET loinc_code = '1971-1',  loinc_display = 'Bilirubin.indirect [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'BI00043';
UPDATE skmst_clabitems SET loinc_code = '1751-7',  loinc_display = 'Albumin [Mass/volume] in Serum or Plasma'            WHERE clabitem_id = 'AL00046';
UPDATE skmst_clabitems SET loinc_code = '2885-2',  loinc_display = 'Protein [Mass/volume] in Serum or Plasma'            WHERE clabitem_id = 'TO00073';
UPDATE skmst_clabitems SET loinc_code = '10834-0', loinc_display = 'Globulin [Mass/volume] in Serum by calculation'      WHERE clabitem_id = 'GL00074';
UPDATE skmst_clabitems SET loinc_code = '6768-6',  loinc_display = 'Alkaline phosphatase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'AL00146';
UPDATE skmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00141';
UPDATE skmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00142';
UPDATE skmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00143';
UPDATE skmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00144';
UPDATE skmst_clabitems SET loinc_code = '2324-2',  loinc_display = 'Gamma glutamyl transferase [Enzymatic activity/volume] in Serum or Plasma' WHERE clabitem_id = 'GA00145';

-- KIMIA DARAH — Fungsi Ginjal
UPDATE skmst_clabitems SET loinc_code = '3091-6',  loinc_display = 'Urea nitrogen [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'UR00027';
UPDATE skmst_clabitems SET loinc_code = '3094-0',  loinc_display = 'Urea nitrogen [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'BU00028';
UPDATE skmst_clabitems SET loinc_code = '2160-0',  loinc_display = 'Creatinine [Mass/volume] in Serum or Plasma'         WHERE clabitem_id = 'CR00029';
UPDATE skmst_clabitems SET loinc_code = '3084-1',  loinc_display = 'Urate [Mass/volume] in Serum or Plasma'              WHERE clabitem_id = 'UR00055';

-- ELEKTROLIT
UPDATE skmst_clabitems SET loinc_code = '2823-3',  loinc_display = 'Potassium [Moles/volume] in Serum or Plasma'         WHERE clabitem_id = 'KA00164';
UPDATE skmst_clabitems SET loinc_code = '2951-2',  loinc_display = 'Sodium [Moles/volume] in Serum or Plasma'            WHERE clabitem_id = 'NA00165';
UPDATE skmst_clabitems SET loinc_code = '2075-0',  loinc_display = 'Chloride [Moles/volume] in Serum or Plasma'          WHERE clabitem_id = 'CH00166';

-- CARDIAC MARKER
UPDATE skmst_clabitems SET loinc_code = '49563-0', loinc_display = 'Troponin I.cardiac [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CT00092';
UPDATE skmst_clabitems SET loinc_code = '32673-6', loinc_display = 'Creatine kinase.MB [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CK00154';
UPDATE skmst_clabitems SET loinc_code = '48065-7', loinc_display = 'Fibrin D-dimer FEU [Mass/volume] in Platelet poor plasma' WHERE clabitem_id = 'D-00159';
UPDATE skmst_clabitems SET loinc_code = '75241-0', loinc_display = 'Procalcitonin [Mass/volume] in Serum or Plasma'      WHERE clabitem_id = 'PR00163';

-- TUMOR MARKER & HORMON
UPDATE skmst_clabitems SET loinc_code = '2039-6',  loinc_display = 'Carcinoembryonic Ag [Mass/volume] in Serum or Plasma' WHERE clabitem_id = 'CE00091';
UPDATE skmst_clabitems SET loinc_code = '2986-8',  loinc_display = 'Testosterone [Mass/volume] in Serum or Plasma'       WHERE clabitem_id = 'TE00093';
UPDATE skmst_clabitems SET loinc_code = '3026-2',  loinc_display = 'Thyroxine (T4) [Moles/volume] in Serum or Plasma'    WHERE clabitem_id = 'T400094';
UPDATE skmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00095';
UPDATE skmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00096';
UPDATE skmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00097';
UPDATE skmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00098';
UPDATE skmst_clabitems SET loinc_code = '3016-3',  loinc_display = 'Thyrotropin [Units/volume] in Serum or Plasma'       WHERE clabitem_id = 'TS00099';
UPDATE skmst_clabitems SET loinc_code = '3024-7',  loinc_display = 'Thyroxine (T4) free [Moles/volume] in Serum or Plasma' WHERE clabitem_id = 'FT00171';
UPDATE skmst_clabitems SET loinc_code = '3053-6',  loinc_display = 'Triiodothyronine (T3) [Moles/volume] in Serum or Plasma' WHERE clabitem_id = 'B-00127';

-- HEMOSTASIS
UPDATE skmst_clabitems SET loinc_code = '3184-9',  loinc_display = 'Coagulation tissue factor induced.clot time'         WHERE clabitem_id = 'CT00071';
UPDATE skmst_clabitems SET loinc_code = '11067-6', loinc_display = 'Bleeding time'                                       WHERE clabitem_id = 'BT00072';

-- SEROLOGI — Widal
UPDATE skmst_clabitems SET loinc_code = '5765-5',  loinc_display = 'Salmonella typhi O Ab [Titer] in Serum'              WHERE clabitem_id = 'S.00022';
UPDATE skmst_clabitems SET loinc_code = '5764-8',  loinc_display = 'Salmonella typhi H Ab [Titer] in Serum'              WHERE clabitem_id = 'S.00023';
UPDATE skmst_clabitems SET loinc_code = '5758-0',  loinc_display = 'Salmonella paratyphi A Ab [Titer] in Serum'          WHERE clabitem_id = 'S.00024';
UPDATE skmst_clabitems SET loinc_code = '5760-6',  loinc_display = 'Salmonella paratyphi B Ab [Titer] in Serum'          WHERE clabitem_id = 'S.00025';

-- HEPATITIS
UPDATE skmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'HB00047';
UPDATE skmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'HB00209';
UPDATE skmst_clabitems SET loinc_code = '5195-3',  loinc_display = 'Hepatitis B virus surface Ag [Presence] in Serum'    WHERE clabitem_id = 'GB00210';

-- DENGUE
UPDATE skmst_clabitems SET loinc_code = '75377-2', loinc_display = 'Dengue virus NS1 Ag [Presence] in Serum by Immunoassay' WHERE clabitem_id = 'PE00113';
UPDATE skmst_clabitems SET loinc_code = '29676-4', loinc_display = 'Dengue virus IgG Ab [Presence] in Serum'             WHERE clabitem_id = 'DE00083';
UPDATE skmst_clabitems SET loinc_code = '29504-8', loinc_display = 'Dengue virus IgM Ab [Presence] in Serum'             WHERE clabitem_id = 'DE00084';

-- TOXOPLASMA
UPDATE skmst_clabitems SET loinc_code = '8039-1',  loinc_display = 'Toxoplasma gondii IgG Ab [Units/volume] in Serum'    WHERE clabitem_id = 'TO00110';
UPDATE skmst_clabitems SET loinc_code = '8040-9',  loinc_display = 'Toxoplasma gondii IgM Ab [Units/volume] in Serum'    WHERE clabitem_id = 'TO00112';

-- LEPTOSPIRA
UPDATE skmst_clabitems SET loinc_code = '40674-4', loinc_display = 'Leptospira sp IgG Ab [Presence] in Serum'            WHERE clabitem_id = 'IG00161';
UPDATE skmst_clabitems SET loinc_code = '40675-1', loinc_display = 'Leptospira sp IgM Ab [Presence] in Serum'            WHERE clabitem_id = 'IG00162';

-- HIV & SYPHILIS
UPDATE skmst_clabitems SET loinc_code = '68961-2', loinc_display = 'HIV 1+2 Ab [Presence] in Serum or Plasma'            WHERE clabitem_id = 'HI00157';
UPDATE skmst_clabitems SET loinc_code = '20507-0', loinc_display = 'Treponema pallidum Ab [Presence] in Serum'           WHERE clabitem_id = 'SY00158';
UPDATE skmst_clabitems SET loinc_code = '68961-2', loinc_display = 'HIV 1+2 Ab [Presence] in Serum or Plasma'            WHERE clabitem_id = 'HI00212';
UPDATE skmst_clabitems SET loinc_code = '20507-0', loinc_display = 'Treponema pallidum Ab [Presence] in Serum'           WHERE clabitem_id = 'PR00148';

-- ANTI SARS-CoV-2
UPDATE skmst_clabitems SET loinc_code = '94500-6', loinc_display = 'SARS-CoV-2 RNA [Presence] in Respiratory specimen by NAA' WHERE clabitem_id = 'SW00147';
UPDATE skmst_clabitems SET loinc_code = '94563-4', loinc_display = 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00159';
UPDATE skmst_clabitems SET loinc_code = '94564-2', loinc_display = 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IGM00159';
UPDATE skmst_clabitems SET loinc_code = '95209-3', loinc_display = 'SARS-CoV-2 Ag [Presence] in Respiratory specimen by Rapid immunoassay' WHERE clabitem_id = 'SW00146';
UPDATE skmst_clabitems SET loinc_code = '94563-4', loinc_display = 'SARS-CoV-2 IgG Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00144';
UPDATE skmst_clabitems SET loinc_code = '94564-2', loinc_display = 'SARS-CoV-2 IgM Ab [Presence] in Serum or Plasma'    WHERE clabitem_id = 'IG00145';

-- BTA
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00064';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00065';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00066';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00105';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00106';
UPDATE skmst_clabitems SET loinc_code = '11545-1', loinc_display = 'Mycobacterium sp identified in Specimen by Acid fast stain' WHERE clabitem_id = 'BT00107';

-- MALARIA
UPDATE skmst_clabitems SET loinc_code = '51587-4', loinc_display = 'Plasmodium falciparum Ag [Presence] in Blood'        WHERE clabitem_id = 'PL00088';
UPDATE skmst_clabitems SET loinc_code = '51588-2', loinc_display = 'Plasmodium vivax Ag [Presence] in Blood'             WHERE clabitem_id = 'PL00089';

-- URINALISA
UPDATE skmst_clabitems SET loinc_code = '5770-3',  loinc_display = 'Albumin [Presence] in Urine by Test strip'           WHERE clabitem_id = 'AL00031';
UPDATE skmst_clabitems SET loinc_code = '1977-8',  loinc_display = 'Bilirubin [Presence] in Urine by Test strip'         WHERE clabitem_id = 'BI00032';
UPDATE skmst_clabitems SET loinc_code = '5792-7',  loinc_display = 'Glucose [Presence] in Urine by Test strip'           WHERE clabitem_id = 'RE00033';
UPDATE skmst_clabitems SET loinc_code = '5818-0',  loinc_display = 'Urobilinogen [Presence] in Urine by Test strip'      WHERE clabitem_id = 'UR00034';
UPDATE skmst_clabitems SET loinc_code = '5797-6',  loinc_display = 'Ketones [Presence] in Urine by Test strip'           WHERE clabitem_id = 'KE00035';
UPDATE skmst_clabitems SET loinc_code = '5802-4',  loinc_display = 'Nitrite [Presence] in Urine by Test strip'           WHERE clabitem_id = 'NI00172';
UPDATE skmst_clabitems SET loinc_code = '5808-1',  loinc_display = 'Erythrocytes [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'ER00036';
UPDATE skmst_clabitems SET loinc_code = '5821-4',  loinc_display = 'Leukocytes [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'LE00037';
UPDATE skmst_clabitems SET loinc_code = '11277-1', loinc_display = 'Epithelial cells [#/area] in Urine sediment by Microscopy' WHERE clabitem_id = 'EP00038';

-- LAIN-LAIN
UPDATE skmst_clabitems SET loinc_code = '883-9',   loinc_display = 'ABO group [Type] in Blood'                           WHERE clabitem_id = 'GO00056';
UPDATE skmst_clabitems SET loinc_code = '2106-3',  loinc_display = 'Choriogonadotropin (pregnancy test) [Presence] in Urine' WHERE clabitem_id = 'PL00049';
UPDATE skmst_clabitems SET loinc_code = '58408-6', loinc_display = 'Peripheral blood smear interpretation'               WHERE clabitem_id = 'HA00050';
UPDATE skmst_clabitems SET loinc_code = '58408-6', loinc_display = 'Peripheral blood smear interpretation'               WHERE clabitem_id = 'HA00152';
UPDATE skmst_clabitems SET loinc_code = '17849-1', loinc_display = 'Reticulocytes/100 erythrocytes in Blood'             WHERE clabitem_id = 'RE00153';
UPDATE skmst_clabitems SET loinc_code = '718-7',   loinc_display = 'Hemoglobin [Mass/volume] in Blood'                   WHERE clabitem_id = 'HE00114';
UPDATE skmst_clabitems SET loinc_code = '6690-2',  loinc_display = 'Leukocytes [#/volume] in Blood by Automated count'  WHERE clabitem_id = 'LE00115';
UPDATE skmst_clabitems SET loinc_code = '777-3',   loinc_display = 'Platelets [#/volume] in Blood by Automated count'    WHERE clabitem_id = 'TR00116';
UPDATE skmst_clabitems SET loinc_code = '4544-3',  loinc_display = 'Hematocrit [Volume Fraction] of Blood'               WHERE clabitem_id = 'PC00117';
UPDATE skmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00207';
UPDATE skmst_clabitems SET loinc_code = '1975-2',  loinc_display = 'Bilirubin.total [Mass/volume] in Serum or Plasma'    WHERE clabitem_id = 'BI00208';

COMMIT;


-- =============================================================================
-- VERIFY — final sanity check
-- =============================================================================
PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  VERIFY                                                    ║
PROMPT ╚════════════════════════════════════════════════════════════╝

PROMPT
PROMPT === Tabel cache (harus ada 2 baris: SNOMED + LOINC) ===
SELECT table_name FROM user_tables
 WHERE table_name IN ('SKMST_SNOMED_CODES','SKMST_LOINC_CODES')
 ORDER BY table_name;

PROMPT
PROMPT === SNOMED code count per value_set ===
SELECT value_set, COUNT(*) AS n FROM skmst_snomed_codes GROUP BY value_set ORDER BY value_set;

PROMPT
PROMPT === LOINC code count per loinc_class ===
SELECT loinc_class, COUNT(*) AS n FROM skmst_loinc_codes GROUP BY loinc_class ORDER BY loinc_class;

PROMPT
PROMPT === SKMST_CLABITEMS LOINC columns (harus ada 4 baris) ===
SELECT column_name, data_type || '(' || NVL(data_length,0) || ')' AS data_type
FROM user_tab_columns
WHERE table_name = 'SKMST_CLABITEMS'
  AND column_name IN ('LOINC_CODE','LOINC_DISPLAY','LOW_LIMIT_K','HIGH_LIMIT_K')
ORDER BY column_name;

PROMPT
PROMPT === SKMST_CLABITEMS rows mapped to LOINC ===
SELECT COUNT(*) AS mapped_rows FROM skmst_clabitems WHERE loinc_code IS NOT NULL;

PROMPT
PROMPT === SKMST_RADIOLOGIS LOINC columns (harus ada 2 baris) ===
SELECT column_name, data_type || '(' || NVL(data_length,0) || ')' AS data_type
FROM user_tab_columns
WHERE table_name = 'SKMST_RADIOLOGIS'
  AND column_name IN ('LOINC_CODE','LOINC_DISPLAY')
ORDER BY column_name;

PROMPT
PROMPT === SKMST_RADIOLOGIS rows mapped to LOINC ===
SELECT COUNT(*) AS mapped_rows FROM skmst_radiologis WHERE loinc_code IS NOT NULL;

PROMPT
PROMPT ╔════════════════════════════════════════════════════════════╗
PROMPT ║  ✓ SATUSEHAT BUNDLE SELESAI                                ║
PROMPT ╚════════════════════════════════════════════════════════════╝

EXIT
