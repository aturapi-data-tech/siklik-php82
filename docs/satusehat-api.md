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

  UI RJ (Livewire/Volt SFC, satu tombol per resource):
   ⚡satu-sehat-rj-actions ──buka modal──▶ ⚡kirim-encounter │ ⚡kirim-condition │
                                          ⚡kirim-observation │ ⚡kirim-procedure │
                                          ⚡kirim-medication-request
```

Hasil kiriman disimpan di node JSON `satusehat` pada `sktxn_rjhdrs.datadaftarpolirj_json`:
`encounterId`, `conditionIds[]`, `observationIds[]`, `procedureIds[]`,
`medicationRequestIds[]`, `medicationRequestItems[]`, dan flag `encounterInProgress` /
`encounterFinished`. Ditulis lewat `DB::transaction` + `lockRJRow()` + `updateJsonRJ()`.

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

### 5.1 Aktif (5 kartu, modal Kirim Satu Sehat di Daftar RJ)

| # | Kartu | Resource FHIR | Sistem kode | Sumber JSON EMR |
|---|---|---|---|---|
| 1 | Encounter (+ Selesaikan Encounter) | `Encounter` class **AMB** | v3-ActCode | `rjDate`, `regNo`→`patient_uuid`, `drId`→`dr_uuid`, `poliId`→`poli_uuid`, `taskIdPelayanan` |
| 2 | Condition | `Condition` / `encounter-diagnosis` | **ICD-10** | `diagnosis[]` { `icdX` ?? `diagId`, `diagDesc` } |
| 3 | Observation | `Observation` / `vital-signs` | **LOINC** + UCUM | `pemeriksaan.tandaVital` { `sistolik`, `distolik`, `frekuensiNadi`, `suhu`, `frekuensiNafas`, `spo2` } |
| 4 | Procedure | `Procedure` / completed | **ICD-9-CM** | `procedure[]` { `procedureId`, `procedureDesc` } |
| 5 | MedicationRequest | `MedicationRequest` + contained `Medication` | **KFA** | `eresep[]` { `productId`, `productName`, `qty`, … } + master obat |

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
`medication-request`, `encounter-selesai`.

Kartu Encounter juga sudah menerima parameter `bagian` (`'kirim'` | `'selesai'` | `'semua'`,
default `'semua'`) supaya kartu "Selesaikan Encounter" bisa dipindah ke urutan paling bawah
tanpa memecah logikanya ke berkas lain.

### 5.3 Rencana potongan C — kartu baru

| Kartu | Resource | Prasyarat yang BELUM terpenuhi |
|---|---|---|
| Chief Complaint | `Condition` / `problem-list-item`, SNOMED | LOV SNOMED keluhan utama + kolom penyimpan kodenya di JSON EMR |
| Allergy | `AllergyIntolerance`, SNOMED | kode SNOMED alergi + padanan `category` (food/environment/medication); trait sudah siap |
| MedicationDispense | `MedicationDispense`, KFA | sama seperti MedicationRequest: kolom KFA di master obat (C4) |
| Lab | `ServiceRequest` → `Observation(laboratory)` → `DiagnosticReport` | pemetaan LOINC per pemeriksaan lab + IHS petugas lab |

**C4 — kolom KFA di master obat (prasyarat paling menghambat).**
`skmst_products` **belum punya kolom `product_id_satusehat`** (diperiksa ke
`user_tab_columns`: PRODUCT_ID, PRODUCT_NAME, PRODUCT_TYPE, CAT_ID, UOM_ID, SUPP_ID,
COST_PRICE, SALES_PRICE, MARGIN_PERSEN, LIMIT_STOCK, QTY_BOX, PRODUCT_RAK, ACTIVE_STATUS).
Item e-resep siklik pun tidak punya key `kfaCode` — jadi selama kolom itu belum dibuat &
diisi, **tidak ada satu obat pun yang bisa dikirim**. Kartu MedicationRequest melaporkannya
apa adanya ("0 item punya KFA" + sebab), bukan pura-pura siap; begitu kolomnya ada, kartu
langsung jalan tanpa perubahan kode.

**Racikan** (`eresepRacikan[]`) belum didukung: campurannya tak punya KFA tunggal, yang
ber-KFA adalah tiap bahannya. Trait `MedicationRequestTrait`/`MedicationDispenseTrait`
sudah menerima `ingredient[]` dan `medicationType` `SD`/Compound, tapi pemetaan bahan →
KFA-nya belum ada di siklik. Jumlah racikan dilaporkan di kartu supaya tidak hilang senyap.

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
3. **Kolom KFA di `skmst_products`** (§5.3 C4) — penghalang tunggal MedicationRequest &
   MedicationDispense.
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
