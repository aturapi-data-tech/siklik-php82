# DDL CREATE lengkap schema siklik

Dibuat otomatis oleh `php artisan siklik:ddl-create` dari data dictionary DB yang terhubung.
Jangan edit berkas SQL di folder ini secara manual; ubah generator lalu jalankan ulang.

## Isi

| Berkas                     | Isi                                                                  |
|----------------------------|----------------------------------------------------------------------|
| `01_tabel.sql`             | `CREATE TABLE` (122 tabel, urut modul) + PK / UNIQUE / CHECK (115) + `COMMENT ON` |
| `02_fk.sql`                | `ALTER TABLE … ADD CONSTRAINT … FOREIGN KEY` (147, termasuk `ON DELETE CASCADE`) |
| `03_index.sql`             | Index lepas (161) yang bukan pendukung PK/UK, termasuk index fungsi `UPPER(...)` |
| `04_sequence_trigger.sql`  | Sequence (21) + trigger (8)                                    |
| `05_plsql.sql`             | Type / function / procedure / package (18) dari `user_source`; view `SKVIEW_ACCOUNTS*` & `SKVIEW_HPPES` butuh `STRING_AGG` |
| `06_view.sql`              | View (41) urut ketergantungan, `CREATE OR REPLACE FORCE VIEW` dgn daftar kolom |
| `siklik_ddl_lengkap.sql`   | Gabungan 01–06 dalam satu berkas                                      |
| `99_drop_semua.sql`        | Kebalikan 01–06. **Menghapus tabel + data** — hanya untuk schema uji  |

## Tabel per modul

| Modul                        | Tabel  |
|------------------------------|--------|
| Rawat Jalan                  |     13 |
| Laboratorium                 |      6 |
| Pasien & Klinik              |     16 |
| Tarif & Jasa                 |      9 |
| Wilayah                      |      4 |
| Terminologi SATUSEHAT        |      2 |
| Akuntansi                    |      8 |
| Kas & Hutang-Piutang         |     11 |
| Apotek & Gudang (master)     |     14 |
| Apotek & Gudang (transaksi)  |     14 |
| Aplikasi & Identitas         |      5 |
| BPJS & Log                   |      4 |
| Sistem Laravel               |     15 |
| Lain-lain                    |      1 |

## Cara pakai

1. Siapkan user/schema Oracle kosong dengan hak `CREATE TABLE, CREATE VIEW, CREATE SEQUENCE, CREATE TRIGGER,
   CREATE PROCEDURE, CREATE TYPE` dan kuota tablespace.
2. Jalankan lewat SQL*Plus (bukan DBeaver — trigger & view memakai terminator `/`):

   ```
   echo exit | sqlplus -S siklik/rahasia@host/orcl @siklik_ddl_lengkap.sql | tee instal.log
   grep -c ORA- instal.log      # harus 0
   ```

   `echo exit` perlu karena berkas tidak diakhiri EXIT (supaya aman dijalankan dari sesi SQL*Plus
   yang sudah terbuka). Bisa juga berurutan `@01_tabel.sql` … `@06_view.sql` bila ingin berhenti per tahap.
3. Muat data (`imp`/`impdp` dengan `IGNORE=Y`/`TABLE_EXISTS_ACTION=APPEND`, atau INSERT dari CSV),
   lalu setel ulang sequence bila nilai `START WITH` sudah terlampaui data.
4. Lanjutkan `install_bundle*.sql` di folder induk hanya bila schema sumber belum memuat objek
   fitur lanjutan (generator sudah menyalin apa pun yang ada di DB sumber).

## Catatan

- Nama objek sudah berprefix `SKMST_ / SKTXN_ / SKACC_ / SKVIEW_` (rename 11 Sep 2026).
  Synonym nama lama (`rename-siklik/02_synonym_kompat_legacy.sql`) TIDAK dibuat di sini —
  jalankan itu hanya jika siklik-lite legacy masih dipakai.
- `NOT NULL` ditulis di kolom; constraint CHECK bawaan `SYS_C…` untuk NOT NULL tidak ditulis ulang.
- PK yang di DB sumber ditopang index bernama lain (mis. `RJDTLS_PK` ↔ `RJDTLS_RJDTL_DTL_IX`)
  di sini dibuat dengan index bernama sama dengan constraint-nya; hanya nama index yang berbeda.
- View yang berstatus INVALID di DB sumber tetap ditulis; `FORCE` membuatnya tercipta lalu
  bisa diperbaiki/dihapus belakangan tanpa menghentikan instalasi.
- Storage clause (tablespace, PCTFREE, dsb) sengaja tidak disertakan — memakai default schema.
- Unit PL/SQL disalin apa adanya dari `user_source`; procedure utilitas warisan (`TK_BACKUP_DB`, dsb) bisa
  "created with compilation errors" bila bergantung objek/privilege di luar schema (`TK_BACKUP_DB`: ORA-00942
  pada view sistem) — tidak menghentikan skrip, dan view tidak memakainya. Yang wajib valid: `T_STRING_AGG` + `STRING_AGG` (dipakai `SKVIEW_ACCOUNTS*`, `SKVIEW_HPPES`).
- Kolom PK yang NOT NULL-nya di DB sumber hanya tersirat dari PK di sini ditulis `NOT NULL` eksplisit
  (menambah satu constraint CHECK sistem; tidak mengubah perilaku).
- Diuji 12 Sep 2026 di schema kosong Oracle 10g lokal: 122 tabel, 785 kolom, 147 FK, 276 index,
  21 sequence, 8 trigger, 16 unit PL/SQL, 41 view tercipta tanpa ORA-; definisi kolom & FK identik dengan sumber.