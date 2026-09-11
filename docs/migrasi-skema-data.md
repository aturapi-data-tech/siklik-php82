# Riwayat migrasi skema & data siklik

Catatan untuk developer: **apa yang sudah berubah di Oracle (DDL) dan di isi data (JSON CLOB), di lingkungan mana
sudah dijalankan, dan skrip mana yang harus dijalankan di server lain.** Halaman web: menu Sistem → Struktur Tabel →
*Riwayat Migrasi* (membaca berkas ini). Urutan eksekusi di server baru = urutan tabel di bawah.

Legenda status: ✅ sudah dijalankan · ⏳ belum · — tidak perlu.

## 1. DDL (struktur)

| Tgl | Perubahan | Skrip / perintah | Dev (127.0.0.1) | Produksi | Catatan |
|---|---|---|---|---|---|
| 2026-09-11 | Rename prefix tabel/view `DI/IM/LB/RS/SC/TK` → `SKMST_/SKTXN_/SKACC_/SKVIEW_` (93 tabel, 40 view), synonym nama lama untuk siklik-lite, constraint/index `AKAR_KOLOM_PK/FK/UK/IX` | `php artisan siklik:ddl-rename --prefix=SK` → `database/sql/rename-siklik/01…04` (SQL*Plus) | ✅ | ⏳ | Generator dijalankan terhadap DB target dulu. Kode sejak commit `4f4a4be` hanya kenal nama SK. `05_drop_synonym_legacy.sql` hanya setelah siklik-lite pensiun |
| 2026-09-11 | 39 FK relasi implisit + 42 index kolom FK; drop 27 PL/SQL INVALID (BRPROC_*, APEX); drop 43 sequence tak terpakai | `php artisan siklik:audit-skema` → `database/sql/skema-tahap3/01…03` | ✅ | ⏳ | Laporan `docs/audit-skema.md`. 3 FK NOVALIDATE (ada baris yatim). Rollback `99_rollback.sql` |
| 2026-06 | Bundle fitur lanjutan: `SKMST_SIGNA_CATATANS`, 6 tabel non-medis + view, `USERS.LAST_SEEN_*` | `database/sql/install_bundle_fitur_lanjutan.sql` §01–04 | ✅ (11 Sep) | ⏳ cek | Idempoten; nama index `SIGNA_CATATANS_ACTIVE_IX` (yang lama 31 karakter gagal) |
| 2026-09-11 | `SKMST_PRODUCTS.PRODUCT_ID_SATUSEHAT`, `PRODUCT_NAME_SATUSEHAT` (KFA) | `database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql` (= bundle §05) | ✅ | ⏳ | Tanpa ini MedicationRequest/Dispense ke SATUSEHAT 0 item. Isi lewat Master Obat |
| 2026-09-11 | `SKMST_RADIOLOGIS.LOINC_CODE/LOINC_DISPLAY` | `2026_09_11_alter_skmst_radiologis_add_loinc.sql` (= bundle §06) | — sudah ada dari `install_bundle_satusehat.sql` | ⏳ cek | 127/136 terisi di dev |
| 2026-09-11 | `SKTXN_CHECKUPHDRS.KLINIS_DESC` (keterangan klinis order lab); `SKTXN_RJRADS.KLINIS_DESC` sudah ada (4000) | `2026_09_11_alter_penunjang_add_klinis_desc.sql` (= bundle §07) | ✅ | ⏳ | Form order lab/radiologi mewajibkannya; sebelum kolom ada, form tetap bisa simpan (guard `KolomOpsional`) |
| 2026-04/05 | Tabel sistem Laravel/Spatie, `REF_BPJS_TABLE`, `USERS.KASIR_ID`, `TKTXN_SOWHS` | `database/sql/install_bundle.sql` | ✅ | ✅ | Mandatory |
| 2026-05/06 | LOINC/SNOMED master + kolom LOINC lab/radiologi | `database/sql/install_bundle_satusehat.sql` | ✅ | ✅ | Opsional (SATUSEHAT) |

Cara memeriksa server lain: `php artisan siklik:audit-skema` (laporan `docs/audit-skema.md`) dan halaman Struktur Tabel
(membaca data dictionary langsung).

## 2. Data (isi JSON CLOB `SKTXN_RJHDRS.DATADAFTARPOLIRJ_JSON`)

| Tgl | Node | Bentuk lama → baru | Cara | Dev | Produksi | Rollback |
|---|---|---|---|---|---|---|
| 2026-09-11 | `anamnesa.alergi` | teks bebas ("-", "tidak ada", kosong) → `adaAlergi` Ya/Tidak + SNOMED 716186003 | **saat dibuka** di anamnesa (`AlergiSnomed::normalisasi`), bukan migrasi massal; record yang belum dibuka tetap lama dan tetap terbaca (`untukCetak`) | — | — | tidak perlu |
| 2026-09-11 | `pemeriksaan.tandaVital.tingkatKesadaran` | tiga bentuk nilai (kode BPJS `01`, teks lama `Sadar Baik / Alert`, kosong) | dibaca apa adanya (`NyeriKesadaranObservationMap::labelKesadaran`), belum dimigrasi | — | — | — |
| 2026-09-11 | `penilaian.resikoJatuh` | objek legacy siklik-lite `{skalaMorse{…Score}, skalaHumptyDumpty{…Score}}` → list entri `[{tglPenilaian, petugasPenilai, resikoJatuh{resikoJatuhMetode{resikoJatuhMetode, resikoJatuhMetodeScore, dataResikoJatuh}, kategoriResiko}}]`; kategori dihitung ulang ambang form baru (Morse ≥45 Tinggi, ≥25 Sedang); yang kosong → `[]` | `php artisan siklik:migrasi-json-emr` (uji) lalu `--jalankan`; aturan di `App\Support\PenilaianLegacy` (dipakai juga saat form dibuka) | ✅ (2 konversi, 13.759 → []) | ⏳ | salinan asli `penilaian.resikoJatuhLegacy` + penanda `migrasiPenilaian`; `--rollback --jalankan` |
| 2026-09-11 | `penilaian.nyeri` | objek legacy `{vas{vas}, pencetus, durasi, lokasi}` (hanya VAS yang pernah aktif) → list entri VAS `[{tglPenilaian, nyeri{nyeriMetode{VAS, skor, dataNyeri}, nyeriKet, pencetus, durasi, lokasi}}]` | sama (`siklik:migrasi-json-emr`) | ✅ (11 konversi, sisanya → []) | ⏳ | `penilaian.nyeriLegacy`; `--rollback --jalankan` |
| 2026-09-11 | `penilaian.{dekubitus,statusPediatrik,fisik,diagnosis}` | objek boilerplate legacy → `[]` bila kosong; bila berisi dibiarkan & dilaporkan (Norton ≠ Braden) | sama | ✅ (semua kosong) | ⏳ | tidak perlu (tak ada isi) |
| 2026-09-11 | `eresep`, `diagnosis` (top-level) | list berlubang tersimpan sebagai objek `{0,3,4}` (hapus tanpa reindex di siklik-lite) → `array_values` | sama | ✅ (186 + 44 baris) | ⏳ | tidak perlu (urutan saja) |
| 2026-09-11 | `satusehat` | — (node baru: `encounterId`, `conditionIds[]`, …, `medicationRequestItems[]`, `labKirim{}`, `radKirim{}`) | ditulis kartu Kirim SATUSEHAT | — | — | — |
| 2026-09-11 | `rujukanKompetensi` | — (node baru panel Rujukan Berbasis Kompetensi) | ditulis panel EMR tab Tindak Lanjut | — | — | — |

Kunci EMR yang benar (sumber tunggal: `app/Http/Traits/Txn/Rj/EmrCompletenessRJTrait.php` + skill `satusehat-kirim`):
`diagnosis[]{icdX|diagId,diagDesc}`, `procedure[]{procedureId,procedureDesc}`, `pemeriksaan.tandaVital.{sistolik,distolik,frekuensiNadi,suhu,frekuensiNafas,spo2,tingkatKesadaran(kode BPJS kdSadar)}`,
`anamnesa.keluhanUtama.snomedCode`, `anamnesa.alergi.{adaAlergi,alergi,snomedCode}`, `eresep[]{productId,qty}`, `eresepRacikan[]{noRacikan,…}`, `telaahResep` (10 butir), `taskIdPelayanan.taskId1..7,99`.

## 3. Log eksekusi migrasi data

Diisi otomatis oleh `siklik:migrasi-json-emr` (mode nyata) — jangan diedit manual.

<!-- LOG-MIGRASI-JSON:awal -->
| Tgl | Host DB | Mode | Diproses | Diubah | RJ konversi | Nyeri konversi | eresep reindex | diagnosis reindex | Berisi dibiarkan | Rusak |
|---|---|---|---|---|---|---|---|---|---|---|
| 2026-09-11 20:45 | 127.0.0.1 | migrasi v1 | 13990 | 13990 | 2 | 11 | 186 | 44 | 0 | 0 |
<!-- LOG-MIGRASI-JSON:akhir -->

## 4. Konfigurasi `.env` yang wajib ada di server baru

| Kunci | Nilai | Sejak | Alasan |
|---|---|---|---|
| `APP_TIMEZONE` | `Asia/Jakarta` | 2026-09-11 | waktu ISO8601 ke SATUSEHAT `+07:00` (dulu UTC, meleset 7 jam) |
| `SISRUTE_URL`, `SISRUTE_CONS_ID`, `SISRUTE_SECRET_KEY`, `SISRUTE_USER_KEY`, `SISRUTE_USERNAME`, `SISRUTE_PASSWORD`, `SISRUTE_SIMULASI` | lihat `.env.example` | 2026-09-11 | Rujukan Berbasis Kompetensi (pcare-sisrute-rest); simulasi = fixture tanpa jaringan |
| `BPJS_PROXY_AKTIF`, `BPJS_PROXY_URL`, `BPJS_IP_WHITELIST` | lihat `.env.example` | 2026-09-11 | whitelist IP BPJS lewat VPS proxy |
| `SATUSEHAT_PJ_LAB_DR_ID`, `SATUSEHAT_PJ_RADIOLOGI_DR_ID` | dr_id ber-IHS | 2026-09-11 | performer ServiceRequest penunjang |

## 5. Yang sengaja BELUM dimigrasi (butuh keputusan)

- Key BPJS PCare legacy `alergiMakanan` vs kode `alergiMakan`: nilai alergi makanan record lama tidak terbaca ke PCare.
- Tabel tanpa PK (12) — `docs/audit-skema.md` §3.
- Synonym nama lama (`RSMST_*` dst.) masih hidup untuk siklik-lite; drop setelah legacy pensiun.
