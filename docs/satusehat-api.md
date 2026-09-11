# Dokumentasi API SATUSEHAT — Model Pengiriman & Standarisasi Data (siklik / FKTP Rawat Jalan)

Dokumen ini menjelaskan **cara siklik mengirim data ke SATUSEHAT** (platform interoperabilitas
Kemenkes, FHIR R4) dan **standarisasi data** tiap resource. Berbasis implementasi nyata di repo,
bukan teori.

- Lapisan trait: `app/Http/Traits/SATUSEHAT/*.php`
- Lapisan UI (aktif): `resources/views/pages/transaksi/rj/satu-sehat/⚡kirim-*.blade.php`
  dibuka dari `resources/views/pages/transaksi/rj/daftar-rj/⚡satu-sehat-rj-actions.blade.php`
- Konfigurasi: `config/satusehat.php` (SATUSEHAT) & `config/txfhir.php` (terminology server)

> **Ruang lingkup siklik = klinik pratama, RAWAT JALAN saja.** Tidak ada modul Rawat Inap,
> UGD, radiologi PACS, gizi, maupun imunisasi — bagian-bagian dokumen sirus yang membahas
> EpisodeOfCare, NutritionOrder, ImagingStudy/Orthanc, Immunization, dan modul RI/UGD
> **sengaja tidak diport**. Sisanya (aturan validator, transport, pola sender) berlaku sama.

---

## 1. Arsitektur singkat

```
        ┌──────────────── SatuSehatTrait (core/transport) ─────────────────┐
        │ initializeSatuSehat() · getAccessToken() · makeRequest()          │
        │ logSatuSehat() → web_log_status · ringkasErrorSatuSehat()         │
        └───────────────────────────────────────────────────────────────────┘
                       ▲ di-"use" oleh semua resource trait

  Resource traits (bangun payload FHIR + POST/PUT):
   Encounter · Condition · Observation · Procedure · AllergyIntolerance ·
   MedicationRequest · MedicationDispense · ServiceRequest · Specimen ·
   DiagnosticReport · Patient · Practitioner · Organization · Location ·
   (Loinc/Snomed = lookup terminologi ke tx.fhir.org)

  Helper terminologi & pembaca JSON (app/Support):
   EresepJson (normalisasi node e-resep) · Terminologi\ObatKfa (non-racikan → KFA) ·
   Terminologi\RacikanKfa (compound → ingredient[] ber-KFA) ·
   Terminologi\MedicationRequestItem (peta resep → penyerahan) ·
   KolomSatuSehat (penjaga kolom yang datang dari SQL manual) ·
   PenanggungJawabPenunjang (performer ServiceRequest lab/radiologi) ·
   PenunjangKirimTrait (indeks kirim per-order)

  UI RJ (Livewire/Volt SFC, satu tombol per resource):
   ⚡satu-sehat-rj-actions ──buka modal──▶ ⚡kirim-encounter │ ⚡kirim-condition │
                                          ⚡kirim-observation │ ⚡kirim-procedure │
                                          ⚡kirim-medication-request │
                                          ⚡kirim-medication-dispense │ ⚡kirim-lab │
                                          ⚡kirim-radiologi
```

Hasil kiriman disimpan di node JSON `satusehat` pada `sktxn_rjhdrs.datadaftarpolirj_json`:
`encounterId`, `conditionIds[]`, `observationIds[]`, `procedureIds[]`,
`medicationRequestIds[]`, `medicationRequestItems[]`, `medicationDispenseIds[]`,
`labServiceRequestIds[]`, `labSpecimenIds[]`, `labObservationIds[]`,
`labDiagnosticReportIds[]`, `labKirim{}`, `radServiceRequestIds[]`,
`radObservationIds[]`, `radDiagnosticReportIds[]`, `radKirim{}`, dan flag
`encounterInProgress` / `encounterFinished`. Ditulis lewat `DB::transaction` +
`lockRJRow()` + `updateJsonRJ()`.

> Orkestrator batch `KirimRawatJalanTrait` (580 baris) **sudah DIHAPUS** — kode mati yang
> tak pernah di-`use` siapa pun dan masih memuat bug key JSON yang sudah dibetulkan di
> kartu (`diagnpinaList`, `tindakanList`, `kfaCode`). Jangan dihidupkan lagi dari git
> history tanpa membetulkan key-nya lebih dulu.

---

## 2. Autentikasi & environment

OAuth2 **client_credentials** — `SatuSehatTrait::getAccessToken()`.

| Hal | Nilai / Cara |
|---|---|
| Token endpoint | `config('satusehat.auth_url') . "accesstoken?grant_type=client_credentials"` (POST `asForm`) |
| Kredensial | `config('satusehat.client_id')`, `config('satusehat.secret_id')` — env `SATUSEHAT_SECRET_ID` (catat: `_SECRET_ID`, bukan `_CLIENT_SECRET`) |
| Cache token | `Cache::remember('satusehat_access_token', 3500, …)` — TTL hardcoded ~58 mnt, `expires_in` diabaikan |
| Header API | `Authorization: Bearer {token}` + `Organization-Id: {organization_id}` |
| Base URL FHIR | `config('satusehat.base_url')` |
| Timeout | `config('satusehat.timeout')`, default 10 detik |
| Versi | FHIR **R4**; profil resource `https://fhir.kemkes.go.id/r4/StructureDefinition/*` |

**`config/satusehat.php` SUDAH ADA di siklik** dan semua trait membacanya lewat `config()`,
bukan `env()` langsung. Ini beda dari sirus (yang masih `env()` di `SatuSehatTrait`) dan
**wajib dipertahankan**: `env()` mengembalikan `null` setelah `php artisan config:cache`,
dan integrasi akan mati senyap. Jangan menyalin baris `env()` dari sirus saat memport kode.

⚠️ **`.env` siklik menunjuk PRODUKSI** (`api-satusehat.kemkes.go.id`, tanpa `-stg`).
Tidak ada toggle sandbox di kode — pindah lingkungan = ganti nilai env. Selama kredensial
produksi terpasang, **jangan pernah** menjalankan kiriman uji; pakai uji payload di §6.

---

## 3. Transport & logging

`makeRequest($method, $endpoint, $data = [])` — Laravel `Http`.

- **Bukan FHIR Bundle.** Tiap resource = satu HTTP call terpisah (`POST Encounter`,
  `POST Condition`, …).
- GET: query string dirangkai ke `$endpoint` (argumen `$data` diabaikan untuk GET).
  Method ini hanya menerima **3 argumen** — pencarian yang mengirim `$params` sebagai
  argumen keempat dibuang diam-diam oleh PHP (bug lama `searchServiceRequest`).
- Sukses (`2xx`) → `$response->json()`. Gagal → `throw \Exception('API request failed: '.body)`.
  Pemanggil (kartu Livewire) menangkap `\Throwable` → toast lewat `ringkasErrorSatuSehat()`
  yang memungut `issue[0].details.text` dari OperationOutcome, bukan menempel JSON mentah.
- **Logging:** setiap call — sukses maupun 4xx/5xx maupun gagal koneksi — di-insert ke tabel
  **`web_log_status`** lewat `logSatuSehat()`, pola kolom yang sama dengan
  `AntrianTrait::logWebStatus()` (BPJS): `code`, `date_ref`, `response`, `http_req`,
  `requestTransferTime`. Kegagalan menulis log tidak pernah menggagalkan kiriman.

⚠️ **`web_log_status` siklik TIDAK punya kolom `http_payload`** (sirus punya). Artinya
balasan server tercatat, tapi **isi payload yang kita kirim belum tersimpan**. Saat SATUSEHAT
membantah isi kiriman, jejaknya belum cukup untuk membuktikan apa yang dikirim → lihat
backlog §7.

---

## 4. Resolusi IHS Code

IHS = identitas resource di SATUSEHAT. Sumbernya kolom master (di-set sekali), bukan
di-lookup tiap kirim:

| Entitas | IHS disimpan di | Cara isi |
|---|---|---|
| **Pasien** | `skmst_pasiens.patient_uuid` | Master Pasien — tombol "Update patientUuid" (`searchPatient` by NIK → `createPatient` bila kosong) |
| **Dokter** | `skmst_doctors.dr_uuid` | manual (trait `searchPractitioner` tersedia, tak dipakai runtime) |
| **Poli / Location** | `skmst_polis.poli_uuid` | manual (trait `searchLocation`/`createLocation` tersedia) |
| **Organization** | `config('satusehat.organization_id')` | tetap |

Kalau salah satu dari tiga uuid kosong, kartu Encounter berhenti dengan toast yang menyebut
mana yang kosong — tidak mengirim apa pun.

---

## 5. Kartu yang ada sekarang, dan rencananya

### 5.1 Aktif — kartu di modal Kirim Satu Sehat (Daftar RJ)

| # | Kartu | Resource FHIR | Sistem kode | Sumber JSON EMR |
|---|---|---|---|---|
| 1 | Encounter (+ Selesaikan Encounter) | `Encounter` class **AMB** | v3-ActCode | `rjDate`, `regNo`→`patient_uuid`, `drId`→`dr_uuid`, `poliId`→`poli_uuid`, `taskIdPelayanan` |
| 2 | Condition | `Condition` / `encounter-diagnosis` | **ICD-10** | `diagnosis[]` { `icdX` ?? `diagId`, `diagDesc` } |
| 3 | Observation | `Observation` / `vital-signs` | **LOINC** + UCUM | `pemeriksaan.tandaVital` { `sistolik`, `distolik`, `frekuensiNadi`, `suhu`, `frekuensiNafas`, `spo2` } |
| 4 | Procedure | `Procedure` / completed | **ICD-9-CM** | `procedure[]` { `procedureId`, `procedureDesc` } |
| 5 | MedicationRequest | `MedicationRequest` + contained `Medication` | **KFA** | `eresep[]` { `productId`, `productName`, `qty`, … } + master obat |
| 6 | Chief Complaint | `Condition` / `problem-list-item` | **SNOMED CT** | `anamnesa.keluhanUtama` { `keluhanUtama`, `snomedCode`, `snomedDisplayEn`, `snomedDisplayId` } |
| 7 | Allergy Intolerance | `AllergyIntolerance` | **SNOMED CT** | `anamnesa.alergi` { `alergi`, `snomedCode`, `snomedDisplayEn`, `snomedDisplayId` } |
| 8 | Nyeri & Kesadaran | `Observation` / `survey` + `exam` | SNOMED + **LOINC** | `penilaian.nyeri[]` { `nyeri.nyeriMetode.{nyeriMetode,nyeriMetodeScore}` } dan `pemeriksaan.tandaVital.tingkatKesadaran` |
| 9 | Telaah Resep | `QuestionnaireResponse` **Q0007** | clinical-term Kemkes | `telaahResep` (10 butir + `penanggungJawab`) |
| 10 | MedicationDispense | `MedicationDispense` + contained `Medication` | **KFA** | `satusehat.medicationRequestItems[]` (peta resep→penyerahan) |
| 11 | Penunjang Lab | `ServiceRequest` → `Specimen` → `Observation`(laboratory) → `DiagnosticReport` | **LOINC** | DB: `sktxn_rjlabs` + `sktxn_checkuphdrs/dtls` + `skmst_clabitems` |
| 12 | Penunjang Radiologi | `ServiceRequest` → `Observation`(imaging) → `DiagnosticReport` | **LOINC** | DB: `sktxn_rjrads` + `skmst_radiologis` |

Rincian kartu 6–9 (kunci node, jebakan, apa yang sengaja TIDAK dikirim): **§5.4**.
Rincian kartu 5 (racikan), 10, 11 & 12: **§5.5**.

Kartu 11 & 12 membaca **DB langsung, bukan JSON EMR** — hasil lab & radiologi memang tidak
pernah ditulis ke `datadaftarpolirj_json`.

**Encounter adalah akar.** Semua kartu lain di-gate `:disabled="!$hasEncounter"` dan
mereferensikan `Encounter/{id}`, `Patient/{id}`, `Practitioner/{id}`.

Siklus status Encounter: `arrived` (POST) → `in-progress` (PUT `startRoomEncounter`) →
`finished` (PUT, tombol **Finish** — mensyaratkan `conditionIds` sudah terisi).

**Kode LOINC vital di-hardcode di kartu**: panel TD `85354-9` (komponen sistolik `8480-6`,
distolik `8462-4`), Nadi `8867-4`, Suhu `8310-5`, Pernapasan `9279-1`, SpO2 `59408-5`.
`LoincTrait`/`SnomedTrait` (lookup live ke tx.fhir.org) tidak dipakai di alur ini.

### 5.2 Rencana potongan B — orkestrasi "Kirim Semua"

Setiap kartu sudah disiapkan: method publik dipecah jadi `kirim()` (pembungkus) dan
`kirimInti()` (kerja sesungguhnya), dan `kirim()` **selalu** membalas
`dispatch('rj-satu-sehat.langkah-selesai', langkah: '<nama>')` — berhasil, ditolak server,
atau berhenti di guard. Orkestrator tinggal mendengarkan event itu dan memanggil langkah
berikutnya; tanpa balasan wajib itu rantai menggantung diam-diam pada langkah pertama yang
gagal dan petugas cuma melihat modal membeku.

Nama langkah yang dibalas: `encounter`, `condition`, `observation`, `procedure`,
`medication-request`, `chief-complaint`, `allergy`, `nyeri-kesadaran`, `telaah-resep`,
`medication-dispense`, `lab`, `radiologi`, `encounter-selesai`.

`medication-dispense` **wajib sesudah** `medication-request` (ia merujuk
`MedicationRequest` yang sudah terbit). `lab` & `radiologi` bebas urutannya asal sesudah
`encounter`.

Event pemicunya `ss-<nama langkah>-rj.kirim` (payload `rjNo`), dan tag komponennya
`<livewire:pages::transaksi.rj.satu-sehat.kirim-<nama langkah> :rjNo="$rjNo" />`
(berkasnya `⚡kirim-<nama langkah>.blade.php`), kecuali `encounter-selesai` yang
memakai kartu Encounter dengan parameter `bagian="selesai"`.

Kartu Encounter juga sudah menerima parameter `bagian` (`'kirim'` | `'selesai'` | `'semua'`,
default `'semua'`) supaya kartu "Selesaikan Encounter" bisa dipindah ke urutan paling bawah
tanpa memecah logikanya ke berkas lain.

### 5.3 Potongan C — status prasyarat

| Kartu | Prasyarat | Status |
|---|---|---|
| MedicationRequest (racikan) | pemetaan bahan racikan → KFA | ✅ `App\Support\Terminologi\RacikanKfa` (§5.5) |
| MedicationDispense | kolom KFA di master obat | ⏳ kode & UI siap, **SQL belum dijalankan** (§5.5) |
| Lab | LOINC per pemeriksaan + performer penunjang | ✅ `skmst_clabitems.loinc_code` terisi 108/152; performer lihat §5.6 |
| Radiologi | LOINC per pemeriksaan | ✅ `skmst_radiologis.loinc_code` terisi 127/136 (dari `install_bundle_satusehat.sql`) |

**Kolom KFA di master obat — satu-satunya penghalang yang tersisa.**
`skmst_products` **belum punya `product_id_satusehat` / `product_name_satusehat`**
(diperiksa ke `user_tab_columns` 11/09/2026). Item e-resep siklik pun tidak punya key
`kfaCode`, jadi selama kolom itu belum dibuat & diisi **tidak ada satu obat pun yang bisa
dikirim** — non-racikan maupun racikan. Kartu melaporkannya apa adanya ("0 obat ber-KFA" +
sebabnya), bukan pura-pura siap; begitu kolomnya ada, kartu langsung jalan tanpa perubahan
kode. SQL-nya: `database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql`.

### 5.4 Kartu potongan C-a — kunci node, sumber data, jebakan

Empat kartu diport dari sirus-php82 lalu **diadaptasi ke key siklik yang sudah
diverifikasi ke data nyata** (24.001 baris `sktxn_rjhdrs`, diperiksa 11/09/2026).
Menyalin kartu sirus apa adanya akan gagal senyap — beda key-nya bukan kosmetik.

| Kartu | Node hasil di `satusehat` | Langkah | Event |
|---|---|---|---|
| Chief Complaint | `chiefComplaintId` (skalar) | `chief-complaint` | `ss-chief-complaint-rj.kirim` |
| Allergy | `allergyId` (skalar) | `allergy` | `ss-allergy-rj.kirim` |
| Nyeri & Kesadaran | `nyeriKesadaranObservationIds[]` | `nyeri-kesadaran` | `ss-nyeri-kesadaran-rj.kirim` |
| Telaah Resep | `telaahResepQuestionnaireId` (skalar) | `telaah-resep` | `ss-telaah-resep-rj.kirim` |

**Chief Complaint.** `anamnesa.keluhanUtama.snomedCode` diisi LOV SNOMED
(`lov.selected.keluhanUtamaSnomed`). Per 11/09/2026 **belum ada satu pun record
ber-`snomedCode`** di basis data — LOV-nya baru. Kartu karena itu menyatakan sebabnya
di muka, bukan menunggu tombol ditekan. Kode TIDAK pernah diturunkan dari teks bebas.

**Allergy.** `category` **WAJIB** juga untuk "tidak ada alergi" (RuleNumber 10075);
`type` & `criticality` justru harus DIHILANGKAN di situ. Pemetaannya di
`App\Support\Terminologi\AlergiSnomed`. Beda dari sirus: siklik **tidak punya key
`adaAlergi`**, jadi keadaan "tidak ada alergi" hanya dikenali dari kodenya —
`AlergiSnomed::normalisasi()` milik sirus sengaja tidak diport.

**Nyeri & Kesadaran.** Dua jebakan yang masing-masing membuat kartu salah kirim:

1. Kesadaran ada di `pemeriksaan.tandaVital.tingkatKesadaran`, **bukan**
   `screening.kesadaran` (node itu tidak ada di siklik), dan isinya **kode BPJS PCare**
   `kdSadar` — 200/200 kunjungan terbaru memakai `'01'` (Compos mentis), bukan teks AVPU
   yang masih terdaftar di `tingkatKesadaranOptions` form perawat. `NyeriKesadaranObservationMap`
   menerjemahkan kodenya ke teks dan mengirimnya sebagai `valueCodeableConcept` **tanpa
   `coding`** — sah di FHIR, dan jauh lebih jujur daripada mengarang kode.
2. `penilaian.nyeri` di data nyata **selalu bentuk LAMA** (satu entri assoc, `nyeriMetode`
   berupa STRING, skor di `skalaNyeri` atau `vas.vas`); 0 record memakai `nyeriMetodeScore`
   yang ditulis form baru. Semua pembacaan wajib lewat `NyeriOptions::daftarEntri()`.

Skala yang **punya** kode resmi hanya **NRS** (SNOMED `1172399009`, `valueInteger`) dan
**NIPS** (LOINC `98012-8`, `valueQuantity {score}`). **VAS, FLACC, BPS dilewati** — dan
jumlahnya disebut di kartu & toast. Catatan: contoh resmi "Observation - BPS" display-nya
justru Wong-Baker FACES (skala anak), instrumen yang berbeda sama sekali dari Behavioral
Pain Scale di dropdown siklik, jadi BPS siklik sengaja **tidak** dipetakan ke sana.

**Telaah Resep (Q0007).** Telaah siklik hanya **10 butir**, sirus 15. Lima pertanyaan
Q0007 tidak punya sumber data di siklik (`1.2` identitas & paraf dokter, `1.3` tanggal
resep, `1.4` ruangan asal resep, `2.3` stabilitas obat, `3.1` ketepatan indikasi) dan
**sengaja tidak dikirim — bukan dijawab "Sesuai"**: mengarang jawaban atas pertanyaan yang
tak pernah diajukan ke apoteker lalu menuliskannya ke rekam medis nasional jauh lebih buruk
daripada kuesioner yang tidak lengkap. Sebaliknya butir siklik `kejelasanTulisanResep`
tidak punya linkId Q0007 dan ikut tidak terkirim; kartu menyebutkan keduanya.

Yang dikirim: `1.1`←`bbPasienAnak`, `2.1`←`tepatObat`, `2.2`←`tepatDosis`,
`2.4`←`tepatRute`+`tepatWaktu` (keduanya harus 'Ya'), `3.2`←`duplikasi`,
`3.3`←`alergi`, `3.4`←`kontraIndikasiLain`, `3.5`←`interaksiObat`, dan `4` (reference
MedicationRequest) hanya bila resepnya sudah terbit di SATUSEHAT. Bentuk bersarangnya
mengikuti contoh resmi: grup `2`, grup `3`, dan butir `4` berada **di DALAM** grup `1`.

Dua penghalang kirim yang disengaja:
- **Kode "Tidak Sesuai" belum ada.** Koleksi Postman resmi cuma memuat `OV000052`
  ("Sesuai"); `Coding` tak punya field `text` sehingga tak bisa diakali. Selama
  `TelaahResepQ0007::TIDAK_SESUAI` masih `null`, telaah yang memuat jawaban "Tidak" pada
  butir ber-`valueCoding` **ditolak kirim** beserta sebutan butirnya. Melewatinya berarti
  telaah bermasalah terkirim tanpa masalahnya.
- **Butir kosong ditolak** (`butirBelumDijawab()`) — kalau lolos, ia terkirim sebagai
  "Sesuai"/"tidak ada masalah" yang tak pernah dinyatakan siapa pun.

`penanggungJawab.userLogCode` di siklik adalah `users.myuser_code`, sedangkan satu-satunya
pemetaan ke `Practitioner` IHS adalah `skmst_doctors.dr_uuid` — apoteker praktis tak pernah
ada di sana, jadi `author` hampir selalu kosong. Kuesioner tetap dikirim tanpa `author`
(elemen objek kosong justru ditolak validator) dan kekurangan itu disebut di toast.

### 5.5 Kartu potongan C-b — obat (KFA, racikan, penyerahan)

| Kartu | Node hasil di `satusehat` | Langkah | Event | Tag Livewire |
|---|---|---|---|---|
| MedicationRequest | `medicationRequestIds[]` + `medicationRequestItems[]` | `medication-request` | `ss-medication-request-rj.kirim` | `pages::transaksi.rj.satu-sehat.kirim-medication-request` |
| MedicationDispense | `medicationDispenseIds[]` | `medication-dispense` | `ss-medication-dispense-rj.kirim` | `pages::transaksi.rj.satu-sehat.kirim-medication-dispense` |

**KFA datang dari master obat, bukan dari JSON.** `skmst_products.product_id_satusehat`
(kode) + `product_name_satusehat` (display). Diisi manual dari Kamus Farmasi & Alkes
Kemenkes lewat **Master Produk Apotek → SATUSEHAT — Kode KFA**; belum ada pencarian KFA
otomatis (sirus pun tidak punya — inputnya manual juga di sana). Kolom yang kosong bukan
kegagalan senyap: daftar master menandai baris "KFA belum diisi", kartu menghitungnya,
toast menyebutkannya.

Kolom itu datang dari **SQL manual**, bukan migration, jadi ada jendela waktu ketika kode
sudah terpasang tapi kolomnya belum ada. `App\Support\KolomSatuSehat` menjawab
"kolomnya ada?" sekali per request (`user_tab_columns`, di-cache) dan **semua** pembaca
lewat situ — tanpa itu halaman Master Obat dan kartu kirim mati ORA-00904 sebelum sempat
melapor. Pola yang sama dipakai untuk `skmst_radiologis.loinc_code`.

**Racikan = compound, KFA ada di BAHANNYA.** Baris `eresepRacikan[]` siklik **tidak punya
`productId` sama sekali** (probe 11/09/2026, RJ 23977 dkk — hanya `productName`, `dosis`,
`qty`, `noRacikan`). `App\Support\Terminologi\RacikanKfa` karena itu memetakan lewat dua
jalur: `productId` bila ada, kalau tidak **nama** yang cocok **tepat satu** produk ber-KFA.
Nama kembar DITOLAK, bukan diambil yang pertama — menebak berarti salah obat. Grup yang
tak lolos dilaporkan beserta nama bahan yang gagal.

Untuk compound, `medicationCode` dikirim **kosong** sehingga `contained.Medication.code`
berisi `text` saja (tanpa `coding`), dan `ingredient[]` (`RacikanKfa::fhirIngredient()`)
yang membawa kode KFA per bahan; `medicationType` = `SD`/Compound. `strength` sengaja
tidak diisi: dosis racikan siklik teks bebas ("1/2", "3", "sesuai bb") — menebak angkanya
berisiko salah takar.

**`medicationRequestItems[]` adalah peta resep→penyerahan**, ditulis saat
MedicationRequest dikirim: `{id, jenis(nonRacikan|racikan), kunci(productId|noRacikan),
kode, display, qty}`. Tanpa peta ini MedicationDispense harus menebak pasangannya lewat
urutan daftar — geser satu item, obat tertaut ke resep yang salah.
`App\Support\Terminologi\MedicationRequestItem::ambil()` memulihkan kunjungan lama dari
urutan pengiriman (non-racikan dulu, lalu racikan yang siap) dan **menolak** bila
jumlahnya tak cocok; dispense lalu membatalkan diri, bukan memasangkan sembarangan.

`whenPrepared`/`whenHandedOver` = `taskIdPelayanan.taskId7` (obat diserahkan), jatuh ke
`now()` bila kosong — sama seperti waktu selesai Encounter (§8). `performer` memakai IHS
dokter karena apoteker belum punya pemetaan ke `Practitioner`. Satuan quantity memakai
`v3-orderableDrugForm`; CodeSystem `kfa-satuan` DITOLAK (RuleNumber 10050).

### 5.6 Kartu potongan C-b — penunjang per-order & indeks kirim ulang

| Kartu | Node hasil di `satusehat` | Langkah | Event | Tag Livewire |
|---|---|---|---|---|
| Lab | `labServiceRequestIds[]`, `labSpecimenIds[]`, `labObservationIds[]`, `labDiagnosticReportIds[]`, `labKirim{}` | `lab` | `ss-lab-rj.kirim` | `pages::transaksi.rj.satu-sehat.kirim-lab` |
| Radiologi | `radServiceRequestIds[]`, `radObservationIds[]`, `radDiagnosticReportIds[]`, `radKirim{}` | `radiologi` | `ss-radiologi-rj.kirim` | `pages::transaksi.rj.satu-sehat.kirim-radiologi` |

**Indeks per-order (`labKirim` / `radKirim`) di samping array datar.**
Array datar saja tidak cukup: begitu satu order gagal di tengah (SR terbentuk, DR belum),
tak ada cara tahu order mana yang bolong — kiriman ulang lalu mem-POST SR dengan
identifier yang sama dan **macet permanen** di penolakan duplikat (RuleNumber 20002).
`App\Http\Traits\SATUSEHAT\PenunjangKirimTrait` menyimpan `{kunciOrder: {sr, sp, obs, dr}}`
memakai identifier stabil tiap order (lab `{rjNo}-{checkupNo}`, radiologi
`rad-{rjNo}-{radDtl}`). Array datar TETAP ditulis apa adanya — hitungan kartu dan pembaca
lain (termasuk yang mencocokkan string mentah ke CLOB) bergantung padanya.

Record lama yang belum punya indeks dipulihkan sekali lewat pencarian identifier ke
SATUSEHAT (`cariIdLewatIdentifier`). Identifier DiagnosticReport **dicoba dua bentuk**:
`…/diagnostic/{org}/lab` (atau `/rad`) dan bentuk lama `…/diagnostic/{org}` tanpa akhiran —
sebelum RuleNumber 10432 identifier-nya tanpa akhiran, dan tanpa percobaan kedua DR lama
tak ketemu lalu dibuatkan DR KEDUA.

**`DiagnosticReport.result` wajib** (RuleNumber 10385), tapi `Observation` **tidak punya
identifier** sehingga tak bisa dipulihkan maupun ditolak duplikat oleh server. Karena itu
Observation dibuat **DI DALAM** cabang pembuatan DR di kedua kartu: kalau dibuat di luar,
order yang laporannya sudah ada akan ditinggali observasi yatim tiap tombol Kirim ditekan.
Lab yang tak punya satu pun Observation berhasil **tidak** mengirim DR sama sekali.

**Sumber lab.** Relasi rj→checkup ada di DUA tempat dan keduanya dipakai:
`sktxn_rjlabs(rj_no → checkup_no)` dan `sktxn_checkuphdrs.ref_no`; record lama kadang hanya
punya salah satunya, dan paket yang terlewat berarti hasil lab tak pernah terkirim. Paket
diambil bila `status_rjri = 'RJ'` dan `checkup_status <> 'P'` (P = masih proses). Item dari
`sktxn_checkupdtls` × `skmst_clabitems`, melewati baris judul grup (`is_group = 'Y'`) dan
item tersembunyi (`hidden_status = 'Y'`). Hasil numerik → `valueQuantity` (+ UCUM dari
`unit_desc`), selain itu `valueString`. Item **tanpa `loinc_code` dilewati dan dihitung** —
isinya di Master Lab. Panel SR/DR memakai LOINC generik `26436-6`; Specimen = darah
(SNOMED `119297000`), metode venipuncture (`129300006`).

**Sumber radiologi.** `sktxn_rjrads` (`rad_dtl`, `rad_id`, `rad_result`, `dr_radiologi`,
`waktu_entry`) ← `skmst_radiologis` (`loinc_code`, `loinc_display`). **TANPA
ImagingStudy/Orthanc** — siklik klinik pratama tidak punya PACS, dan bagian itu sengaja
tidak diport dari sirus. Hasil bacaan diwakili satu Observation ringkas (`category` =
`imaging`, `valueString` = `rad_result` atau "Lihat hasil pada lampiran radiologi") supaya
`DiagnosticReport.result` terisi. Master yang belum dipetakan jatuh ke LOINC generik
**`18748-4`** — sah di mata validator tapi semua pemeriksaan jadi tak bisa dibedakan, jadi
jumlahnya disebut di toast dan ditandai di daftar Master Radiologi.

**`ServiceRequest.performer` wajib** (RuleNumber 10377) dan menurut koleksi Postman resmi
isinya praktisi yang MENGERJAKAN pemeriksaan. siklik **tidak punya poli Laboratorium /
Radiologi** (`skmst_polis` cuma POLI UMUM & POLI GIGI), jadi konvensi sirus "dokter aktif
pada poli unit itu" tidak bisa diport. `App\Support\PenanggungJawabPenunjang` mencoba
berurutan: `dr_id` yang tercatat pada order (lab: `sktxn_checkuphdrs.dr_id`) →
`config('satusehat.pj_lab_dr_id')` / `pj_radiologi_dr_id` (env `SATUSEHAT_PJ_LAB_DR_ID`,
`SATUSEHAT_PJ_RADIOLOGI_DR_ID`) → **nama** teks bebas (`sktxn_rjrads.dr_radiologi`) yang
cocok **tepat satu** dokter aktif ber-IHS. Gagal semua → array kosong, dan
`ServiceRequestTrait` memakai dokter pengirim sebagai pengganti: kiriman jalan, nilainya
saja yang belum akurat.

---

## 6. Aturan payload universal — 11 butir

Semuanya pernah **ditolak sungguhan** oleh validator Kemkes (OperationOutcome +
`RuleNumber`), bukan dugaan.

1. **Elemen objek (kardinalitas 0..1) jangan dikirim `[]`.**
   `invalid value (expected a DispenseRequest object): []` — field opsional
   (`dispenseRequest`, `dosageInstruction`, `reasonReference`, `quantity`, `daysSupply`)
   hanya disertakan bila ada isinya. Ditangani di `MedicationRequestTrait` &
   `MedicationDispenseTrait`.
2. **`Encounter.statusHistory`: tiap entri wajib `start` DAN `end`** (Rule 10122).
   Entri bawaan `createNewEncounter()`/`startRoomEncounter()` hanya punya `start` →
   `EncounterTrait::siapkanFinishEncounter()` mengisi `end` tiap entri dari `start` entri
   BERIKUTNYA (entri terakhir memakai waktu selesai).
3. **`Encounter.diagnosis` wajib saat finish** (Rule 10457), merujuk Condition yang sudah
   dikirim, `use` = `DD` (Discharge diagnosis), `rank` berurutan. Tombol Finish **menolak
   lebih dulu** bila `conditionIds` kosong — jangan kirim lalu pasti ditolak.
4. **`period.end` tidak boleh mendahului `period.start`.** Dibalas menyesatkan:
   `unparseable_resource` + `fhirpath-constraint-violation-Encounter.period`, seolah
   resource kita rusak. `period.start` **DIBEKUKAN** di SATUSEHAT sejak Encounter dibuat,
   sedangkan waktu selesai datang dari `taskIdPelayanan` yang bisa lebih awal.
   Perbandingannya harus **sebagai waktu**, bukan teks: `08:00+07:00` vs `05:30+00:00` itu
   sah (end = 12:30 WIB) tapi sebagai teks urutannya terbalik.
5. **Tanggal kunjungan kosong = tolak di hulu.** `parseDate('')` diam-diam jatuh ke `now()`,
   sehingga `period.start` terisi jam petugas mengklik dan ikut dibekukan. Kartu Encounter
   menolak bila `rjDate` kosong dan menyuruh membetulkannya di pendaftaran.
6. **`AllergyIntolerance.category` WAJIB** (Rule 10075), termasuk untuk pernyataan
   "tidak ada alergi". `type` dan `criticality` boleh dihilangkan di kasus itu (ketiganya
   atribut alergi yang ADA), `category` tidak boleh.
7. **`prescriptionItemId` unik per item.** Satu resep berisi banyak obat; tanpa nomor item
   sendiri semua `MedicationRequest`-nya memakai identifier yang sama persis dan server tak
   bisa membedakan obat kedua dari obat pertama. Bentuk: `{rjNo}-{n}`.
8. **`MedicationDispense.quantity.system`**: `…/CodeSystem/kfa-satuan` DITOLAK
   (Rule 10050). Pakai `http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm`.
9. **Racikan (compound):** campurannya tak punya KFA → `Medication.code` cukup `code.text`,
   `medicationType` = `SD`/Compound, dan kode KFA ada di `ingredient[]` per bahan.
10. **Kode SNOMED harus ada di edisi yang dipakai Kemkes.**
    `Code not found: '1306548008' … (RuleNumber 10003)` — edisi SATUSEHAT lebih tua dari
    tx.fhir.org. Edisi di-pin lewat `config/txfhir.php` `snomed_version`; `SnomedTrait`
    mengirim `system-version` di `$expand` dan `version` di `$lookup`. Cache lama
    dibersihkan: `php artisan snomed:bersihkan-cache --dry-run` dulu.
11. **`ServiceRequest.performer` wajib** (Rule 10377) — bahkan ketika petugas penunjangnya
    belum punya IHS. Bila pemanggil tak menyebut performer, dokter pengirim dipakai sebagai
    pengganti (kiriman jalan, nilainya belum akurat).

### Aturan yang bukan soal validator, tapi sama pentingnya

- **Simpan id parsial sebelum melapor gagal.** Kalau satu POST di tengah loop ditolak,
  resource yang sudah terlanjur terbentuk di SATUSEHAT tidak pernah tercatat id-nya di
  sisi kita; percobaan berikutnya membuat ulang dari nol, menumpuk resource yatim, dan untuk
  resource yang punya aturan duplikat berujung **macet permanen**. Semua kartu siklik kini
  menyimpan apa yang sudah didapat di blok `catch` sebelum menoast error.
- **Pulihkan id saat ditolak duplikat.** Penolakan `"duplicate"` / `Found duplicate` berarti
  resource-nya memang sudah ada di sana. `ConditionTrait::findExistingConditionId()`
  memungut id-nya (`Condition?encounter=…`, lalu `Condition?subject=Patient/…` disaring
  per-encounter), bukan menyerah.
- **Yang tak terkirim WAJIB dilaporkan.** Item tanpa kode (KFA/ICD/LOINC) boleh dilewati,
  tidak boleh hilang diam-diam: kartu menampilkan hitungannya **sebelum** kirim, toast
  menyebutkannya **sesudah** kirim.
- **Verifikasi key JSON ke data nyata, jangan percaya kode lama.** Lihat §8.

---

## 7. Uji payload TANPA mengirim

Wajib, karena `.env` menunjuk produksi. Pakai kelas anonim yang me-`use` trait-nya lalu
**menimpa `makeRequest()`** supaya payload ditangkap, bukan dikirim:

```php
$dryRun = new class {
    use \App\Http\Traits\SATUSEHAT\MedicationRequestTrait;
    public array $payloadList = [];
    protected function makeRequest($method, $endpoint, $data = [])
    {
        $this->payloadList[] = [strtoupper($method), $endpoint, $data];
        return ['id' => 'dry'];
    }
};
$dryRun->initializeSatuSehat();
$dryRun->createMedicationRequest([...]);   // periksa $dryRun->payloadList[0][2]
```

Untuk Encounter **finish**, balas `makeRequest('get', …)` dengan Encounter palsu yang
`statusHistory`-nya hanya punya `start` — itu bentuk yang benar-benar dikembalikan server,
dan di situlah Rule 10122 kena.

Yang wajib diperiksa pada tiap payload:

- tidak ada elemen bernilai `[]` (telusuri rekursif) — aturan §6.1;
- `statusHistory` tiap entri punya `start` **dan** `end`, dan `end >= start`;
- `diagnosis` ada dan tidak kosong saat finish;
- `identifier` prescription-item **unik** antar item;
- `period.end >= period.start`.

Render kartunya sendiri diuji dengan `Livewire\Livewire::test('pages::transaksi.rj.satu-sehat.kirim-…', ['rjNo' => $rjNo])`
— aman karena `mount()` hanya membaca JSON, tidak memanggil SATUSEHAT.

**Jangan panggil `saveResult()` atau `kirim()` nyata dalam uji** — keduanya menulis CLOB
dan (untuk `kirim()`) menembak produksi.

---

## 8. Sumber data JSON EMR — key yang benar

Empat kelompok sender di sirus pernah membaca key yang **tak pernah ada** di JSON EMR dan
gagal SENYAP ("berhasil dikirim (0 item)" / "tidak ada data", tanpa error). Kartu siklik
mewarisi bug yang sama dan sudah dibetulkan:

| Kartu | Dibaca (salah, versi lama) | Yang benar di siklik |
|---|---|---|
| Condition | `diagnpinaList[]` / `diagnosaPinaUtama`, key `kodeIcdx`/`descIcdx` | `diagnosis[]`, key `icdX ?? diagId` + `diagDesc` |
| Observation | `pemeriksaanFisik` / `tandaVital` di akar; `sistole`/`diastole`/`nadi`/`rr` | `pemeriksaan.tandaVital`; `sistolik`/`distolik`/`frekuensiNadi`/`suhu`/`frekuensiNafas`/`spo2` |
| Procedure | `tindakanList`/`tindakan`, key `kodeIcd9`/`descIcd9` | `procedure[]`, key `procedureId` (ICD-9-CM) + `procedureDesc` |
| MedicationRequest | `kfaCode` / `product_id_satusehat` di item e-resep | lookup master obat lewat `productId` (kolom KFA belum ada — §5.3) |
| Nyeri & Kesadaran | `screening.kesadaran` (node sirus, tak ada di siklik) | `pemeriksaan.tandaVital.tingkatKesadaran` — isinya **kode BPJS** `kdSadar` |
| Nyeri (skor) | `nyeri.nyeriMetode.nyeriMetodeScore` langsung | selalu lewat `NyeriOptions::daftarEntri()`; data nyata masih bentuk lama (`nyeriMetode` string + `skalaNyeri`/`vas.vas`) |
| Telaah Resep | 15 butir telaah sirus | `telaahResep` hanya **10 butir**; 5 pertanyaan Q0007 tidak dikirim (§5.4) |

Acuan peta key yang benar: `app/Http/Traits/Txn/Rj/EmrCompletenessRJTrait.php` (dipakai
untuk menghitung kelengkapan EMR, jadi key-nya pasti yang benar-benar ditulis EMR).

Sebelum menulis sender baru: `findDataRJ('<rjNo>')` lalu `array_keys()` node yang dipakai.
Sekali saja, tapi wajib.

**Waktu "selesai" Encounter di RJ**: `taskIdPelayanan.taskId7` (obat diserahkan) →
`taskId5` (keluar poli) → `now()`. Keduanya ditulis
`⚡task-id-apotek-actions` / `⚡task-id-poli-actions`.

---

## 9. Backlog & prasyarat

1. **`APP_TIMEZONE` belum di-set di `.env` siklik** → `config('app.timezone')` = `UTC`,
   sehingga `toIso8601String()` menempelkan offset `+00:00` pada jam yang sebenarnya WIB.
   Contoh nyata: `rjDate` `10/09/2026 10:55:42` terkirim sebagai
   `2026-09-10T10:55:42+00:00` — **7 jam meleset**. sirus memasang
   `APP_TIMEZONE='Asia/Jakarta'`. **Betulkan ini sebelum kiriman produksi pertama**; ini
   perubahan env, bukan kode.
2. **`web_log_status` tanpa kolom `http_payload`** (§3) — tambahkan kolom itu agar payload
   yang dikirim ikut tercatat, lalu lengkapi `logSatuSehat()`.
3. **Kolom KFA di `skmst_products` belum dibuat** (§5.3, §5.5) — penghalang tunggal
   MedicationRequest & MedicationDispense, non-racikan maupun racikan. Jalankan
   `database/sql/2026_09_11_alter_skmst_products_add_satusehat.sql`, lalu isi kodenya
   lewat Master Produk Apotek. Sesudah kolomnya ada, pemetaan bahan racikan lewat NAMA
   (§5.5) perlu **diverifikasi ulang ke data nyata**: probe 11/09/2026 menemukan keempat
   nama bahan RJ 23977 cocok tepat satu produk, tapi itu baru satu kunjungan.
4. **Timeout 10 detik tanpa `connectTimeout()`/`retry()`** — samakan dengan pola BPJS
   (`timeout(8)->connectTimeout(3)`) supaya server SATUSEHAT yang lambat tidak membekukan
   layar.
5. **Token TTL hardcoded 3500 detik** mengabaikan `expires_in`, dan tidak ada invalidasi
   cache saat balasan 401.
6. **Idempotensi hanya guard lokal.** Hanya Encounter yang punya `identifier` bisnis
   (natural key) di sisi server; resource lain mengandalkan node JSON `satusehat`. Kalau
   node itu hilang, kiriman ulang membuat resource ganda — kecuali Condition, yang kini
   bisa memungut id lamanya saat ditolak duplikat.
7. **`registrationId == medicationCode == kode KFA`** untuk obat non-racikan — perlu
   ditinjau apakah field registrasi obat memang harus sama dengan KFA.
8. **`procedure[]` selalu kosong di data nyata** (probe 1.500 kunjungan terakhir: node-nya
   ada di 1.401 record, isinya 0). Kartu Procedure benar, tapi belum ada data untuk
   dikirim — periksa apakah tindakan ICD-9-CM memang belum diisi di EMR.
9. **`taskId5`/`taskId7` belum pernah terisi** pada 1.500 kunjungan terakhir (hanya
   `taskId3` dan `taskId99`) — kartu Finish akan jatuh ke `now()` sampai alur task antrean
   poli/apotek benar-benar dipakai.
10. **`snomedCode` masih kosong di SELURUH basis data** (0 dari 24.001 kunjungan, per
    11/09/2026) — LOV SNOMED keluhan utama & alergi baru dipasang. Kartu Chief Complaint
    dan Allergy akan selalu menolak sampai petugas mulai memilih kodenya.
11. **Kode "Tidak Sesuai" Q0007 belum diketahui** (§5.4) — telaah yang memuat jawaban
    "Tidak" pada butir ber-`valueCoding` belum bisa dikirim. Begitu kodenya didapat dari
    Lampiran Terminologi SATUSEHAT, cukup isi konstanta `TelaahResepQ0007::TIDAK_SESUAI`.
12. **Lima pertanyaan Q0007 tak punya sumber data** (§5.4). Kalau validator Kemkes ternyata
    mewajibkan seluruh linkId, pilihannya adalah **menambah butirnya ke form Telaah Resep**
    — bukan mengisinya dengan jawaban karangan.
13. **Padanan SNOMED tingkat kesadaran belum ada** untuk keempat kode BPJS (Compos mentis /
    Somnolence / Sopor / Coma); sementara ini dikirim sebagai teks. Isi `'code'` di
    `NyeriKesadaranObservationMap::KESADARAN` begitu padanan resminya terbit.
14. **VAS, FLACC, BPS belum punya kode Observation resmi** — entri nyeri yang memakainya
    tidak ikut terkirim (dilaporkan di kartu, bukan disembunyikan).
15. **IHS apoteker belum ada pemetaannya** — `telaahResep.penanggungJawab.userLogCode` =
    `users.myuser_code`, sedangkan Practitioner hanya bisa diresolusi dari
    `skmst_doctors.dr_uuid`. QuestionnaireResponse karena itu terkirim tanpa `author`,
    dan `MedicationDispense.performer` memakai IHS DOKTER, bukan apoteker.
16. **Kartu 10–12 belum disambung ke "Kirim Semua"** — `medication-dispense`, `lab`,
    `radiologi` (§5.5, §5.6) belum ada di `URUTAN_KIRIM` maupun grid
    `⚡satu-sehat-rj-actions.blade.php`. Tombol per kartu sudah berfungsi.
17. **Petugas penunjang belum punya IHS** (§5.6) — `ServiceRequest.performer` lab &
    radiologi praktis selalu jatuh ke dokter pengirim. Isi `SATUSEHAT_PJ_LAB_DR_ID` /
    `SATUSEHAT_PJ_RADIOLOGI_DR_ID` bila ada dokter penanggung jawab ber-`dr_uuid`, atau
    buat master penunjukan PJ sungguhan (lalu ubah `PenanggungJawabPenunjang` saja).
18. **LOINC lab belum lengkap**: `skmst_clabitems.loinc_code` terisi 108/152 baris,
    `skmst_radiologis.loinc_code` 127/136 (11/09/2026). Item lab tanpa LOINC **dilewati**
    (hasilnya tak sampai ke SATUSEHAT); pemeriksaan radiologi tanpa LOINC tetap terkirim
    tapi dengan kode generik `18748-4`. Keduanya dilaporkan di kartu, bukan disembunyikan.
19. **Specimen lab selalu diasumsikan darah** (SNOMED `119297000`, venipuncture) — siklik
    tak menyimpan jenis spesimen per paket. Urine/swab karena itu terkirim salah jenis;
    butuh kolom jenis spesimen di master/transaksi lab.
20. **Bentuk sediaan obat di-hardcode `BS066`/Tablet** pada MedicationRequest &
    MedicationDispense (termasuk `quantity.code` `TAB`) — `skmst_uoms` belum dipetakan ke
    CodeSystem `medication-form`. Sirup & injeksi karena itu terkirim sebagai tablet.
21. **`serialize_precision = 100` di PHP server ini** → setiap `valueQuantity.value` **→ SELESAI 11 Sep 2026: `SatuSehatTrait::encodeJsonFhir()` (serialize_precision=-1) dipakai `makeRequest()` untuk semua body POST/PUT.**
    bertipe float terkirim sebagai ekspansi biner 50 digit:
    `10.4` menjadi `10.4000000000000003552713678800500929355621337890625`. Terlihat pada
    hasil lab numerik (kartu Lab) **dan pada vital sign** (suhu `36.5`, dst. — kartu
    Observation yang sudah jalan), jadi ini cacat transport lama, bukan bawaan kartu baru.
    FHIR `decimal` membatasi 18 digit signifikan, jadi ada risiko nyata ditolak.
    Perbaikannya di satu tempat: `SatuSehatTrait::makeRequest()` mengirim body yang
    di-`json_encode` sendiri dengan `serialize_precision = -1` (atau set ini-nya di
    `php.ini`/bootstrap). **Uji ke sandbox dulu** — ini menyentuh semua kartu sekaligus.

---

## 10. Cara menambah kartu baru

1. **Trait sudah ada** → buat SFC `⚡kirim-<resource>.blade.php` meniru
   `⚡kirim-procedure.blade.php`: state (`rjNo`, `hasEncounter`, `count`, `tersedia`),
   `kirim()`/`kirimInti()`, `saveResult()` ke node `satusehat`, gate
   `:disabled="!$hasEncounter"`. Lalu render di `⚡satu-sehat-rj-actions.blade.php`.
2. **Trait belum ada** → buat `App\Http\Traits\SATUSEHAT\<Resource>Trait` meniru
   `ProcedureTrait` (bangun payload FHIR R4, `makeRequest('post', '/<Resource>', $payload)`),
   pastikan `encounter` + `subject` merujuk IHS yang benar.
3. Uji payload tanpa mengirim (§7) **sebelum** menyentuh tombol Kirim.
4. Verifikasi lewat tabel `web_log_status`.

> Lihat juga: skill `satusehat-kirim`, `docs/trait-template-api-eksternal.md`,
> skill `diagnosa-flow` (kode ICD-10), `docs/struktur-tabel.md`.
