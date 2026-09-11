---
name: satusehat-kirim
description: Aturan mengirim resource ke SATUSEHAT (FHIR R4) di repo siklik — sumber key JSON EMR yang benar, jebakan validator Kemkes, dan cara menguji payload tanpa mengirim. WAJIB dibaca sebelum menambah/mengubah sender di app/Http/Traits/SATUSEHAT atau resources/views/pages/transaksi/rj/satu-sehat, atau saat kiriman ditolak OperationOutcome.
---

# Kirim SATUSEHAT (siklik — klinik pratama, RAWAT JALAN)

Rujukan lengkap: **`docs/satusehat-api.md`** (arsitektur, aturan payload 11 butir, backlog).
Skill ini isinya yang paling sering bikin salah.

Ruang lingkup siklik = **rawat jalan saja**. Tidak ada RI/UGD, EpisodeOfCare, NutritionOrder,
ImagingStudy/Orthanc, atau Immunization — jangan memport bagian itu dari sirus.

## 0. `.env` menunjuk PRODUKSI — jangan pernah kirim untuk menguji

`SATUSEHAT_BASE_URL` = `api-satusehat.kemkes.go.id` (**tanpa `-stg`**). Setiap tombol Kirim
menembak data nyata Kemenkes. Menguji payload = §4, bukan menekan tombol.

## 1. Verifikasi sumber data ke DATA NYATA, jangan percaya kode lama

Empat kartu pernah membaca key yang **tak pernah ada** di JSON EMR dan gagal SENYAP —
"berhasil dikirim (0 item)" atau "tidak ada data", tanpa error:

| Kartu | Dibaca (salah) | Yang benar |
|---|---|---|
| Observation | `pemeriksaanFisik`/`tandaVital` di akar, key `sistole`/`diastole`/`nadi`/`rr` | `pemeriksaan.tandaVital`, key `sistolik`/`distolik`/`frekuensiNadi`/`suhu`/`frekuensiNafas`/`spo2` |
| Condition | `diagnpinaList[]`/`diagnosaPinaUtama`, key `kodeIcdx`/`descIcdx` | `diagnosis[]`, key `icdX ?? diagId` + `diagDesc` |
| Procedure | `tindakanList`/`tindakan`, key `kodeIcd9`/`descIcd9` | `procedure[]`, key `procedureId` (= ICD-9-CM) + `procedureDesc` |
| MedicationRequest | `kfaCode`/`product_id_satusehat` di item e-resep | lookup master obat lewat `productId` — **kolomnya belum ada**, lihat §5 |
| Nyeri & Kesadaran | `screening.kesadaran` (node sirus — TIDAK ADA di siklik) | `pemeriksaan.tandaVital.tingkatKesadaran`, isinya **kode BPJS `kdSadar`** ('01' Compos mentis … '04' Coma), bukan teks |
| Nyeri (skor) | `nyeri.nyeriMetode.nyeriMetodeScore` dibaca langsung | selalu lewat `NyeriOptions::daftarEntri()` — data nyata masih bentuk LAMA (`nyeriMetode` string + `skalaNyeri`/`vas.vas`); 0 record memakai `nyeriMetodeScore` |
| Telaah Resep | 15 butir telaah sirus | `telaahResep` siklik hanya **10 butir** — 5 pertanyaan Q0007 TIDAK dikirim, jangan dijawab "Sesuai" |

Peta key yang benar ada di `app/Http/Traits/Txn/Rj/EmrCompletenessRJTrait.php` (dipakai
menghitung kelengkapan EMR, jadi key-nya pasti yang benar-benar ditulis).

Sebelum menulis sender: `findDataRJ('<rjNo>')` lalu `array_keys()` node yang dipakai.
Sekali saja, tapi wajib.

## 2. Jebakan validator Kemkes (semuanya pernah menolak sungguhan)

- **Elemen objek (0..1) jangan dikirim `[]`** → `invalid value (expected a DispenseRequest object): []`.
  Field opsional hanya disertakan bila ada isinya.
- **`Encounter.statusHistory`**: tiap entri wajib `start` DAN `end` (Rule 10122). Entri bawaan
  `createNewEncounter()`/`startRoomEncounter()` hanya punya `start` →
  `EncounterTrait::siapkanFinishEncounter()` yang merapikan.
- **`Encounter.diagnosis` wajib saat finish** (Rule 10457) → dari `conditionIds`, `use` = `DD`.
  Tolak lebih dulu bila diagnosa belum dikirim, jangan kirim lalu pasti gagal.
- **`period.end` tak boleh mendahului `period.start`** — dibalas menyesatkan
  (`unparseable_resource` + fhirpath-constraint-violation). `period.start` DIBEKUKAN sejak
  Encounter dibuat; bandingkan sebagai **waktu**, bukan teks (offset zona bisa beda).
- **Tanggal kunjungan kosong** → `parseDate('')` diam-diam jadi `now()` dan ikut dibekukan.
  Tolak di hulu.
- **`AllergyIntolerance.category` WAJIB** (Rule 10075), termasuk untuk "tidak ada alergi".
  `type`/`criticality` boleh dihilangkan di situ, `category` tidak.
- **`prescriptionItemId` unik per item** — tanpa itu semua obat memakai identifier sama.
- **`ServiceRequest.performer` wajib** (Rule 10377) — fallback ke requester bila petugas
  penunjang belum punya IHS.
- **SNOMED**: pin edisi lewat `config/txfhir.php` `snomed_version`, kalau tidak konsep baru
  ditolak `RuleNumber 10003`. Cache lama: `php artisan snomed:bersihkan-cache --dry-run`.

## 3. Jangan biarkan id hangus

Kalau satu POST di tengah loop ditolak, resource yang **sudah terbentuk** di SATUSEHAT tak
pernah tercatat id-nya → kiriman ulang menumpuk resource yatim, dan untuk resource
ber-aturan duplikat jadi **macet permanen**. Aturannya:

1. `catch` per item, bukan hanya di puncak `kirim()`;
2. simpan apa yang sudah didapat **sebelum** menoast error;
3. penolakan duplikat = pungut id lamanya (`ConditionTrait::isDuplicateError()` +
   `findExistingConditionId()`), bukan menyerah.

## 4. Uji payload TANPA mengirim

Timpa `makeRequest()` di kelas anonim yang me-`use` trait-nya:

```php
$dryRun = new class {
    use \App\Http\Traits\SATUSEHAT\MedicationRequestTrait;
    public array $payloadList = [];
    protected function makeRequest($method, $endpoint, $data = [])
    { $this->payloadList[] = $data; return ['id' => 'dry']; }
};
$dryRun->initializeSatuSehat();
$dryRun->createMedicationRequest([...]);
```

Untuk Encounter finish, balas `makeRequest('get', …)` dengan Encounter palsu yang
`statusHistory`-nya hanya punya `start` — itu bentuk asli dari server.

Periksa: tidak ada elemen `[]`, `statusHistory` lengkap start+end & urut, `diagnosis` ada
saat finish, identifier prescription-item unik.

Render kartunya: `Livewire\Livewire::test('pages::transaksi.rj.satu-sehat.kirim-condition', ['rjNo' => $rjNo])`
— aman, `mount()` cuma membaca JSON. **Jangan panggil `kirim()` atau `saveResult()`.**

## 5. Yang tak terkirim WAJIB dilaporkan

Item tanpa kode (KFA/ICD/LOINC) boleh dilewati, **tidak boleh hilang diam-diam**: kartu
menampilkan hitungannya sebelum kirim, toast menyebutkannya sesudah kirim.

Kasus nyata sekarang: `skmst_products` **belum punya kolom `product_id_satusehat`**, dan
item e-resep tidak punya `kfaCode`. Jadi MedicationRequest jujur melaporkan "0 item punya
KFA" beserta sebabnya. Jangan mengarang kode KFA atau membaca key yang tak ada supaya kartu
"terlihat jalan". Racikan (`eresepRacikan[]`) juga belum didukung dan dihitung terpisah.

Kasus nyata lain (kartu potongan C-a, rinciannya di `docs/satusehat-api.md` §5.4):

- **Skala nyeri VAS/FLACC/BPS belum punya kode Observation resmi** → dilewati, jumlahnya
  disebut di kartu & toast. Hanya NRS (`valueInteger`) dan NIPS (`valueQuantity {score}`)
  yang berangkat.
- **Tingkat kesadaran belum punya padanan SNOMED** → dikirim sebagai `valueCodeableConcept`
  ber-`text` TANPA `coding`. Itu sah di FHIR; mengarang kode tidak.
- **Q0007: lima pertanyaan tanpa sumber data di siklik tidak dikirim** — jangan
  menggenapinya dengan "Sesuai". Dan selama kode "Tidak Sesuai" belum diketahui, telaah
  yang memuat jawaban "Tidak" **ditolak kirim**, bukan dikirim tanpa temuannya.
- **`snomedCode` masih 0 dari 24.001 record** → kartu Chief Complaint & Allergy menyatakan
  sebab penolakannya di muka, bukan setelah tombol ditekan.

## 6. Konfigurasi lewat `config()`, bukan `env()`

`config/satusehat.php` dan `config/txfhir.php` sudah ada. sirus masih memakai `env()`
langsung di `SatuSehatTrait` — **jangan ikut memportnya**: `env()` jadi `null` setelah
`php artisan config:cache` dan integrasi mati senyap.

## 7. Waktu "selesai" di RJ

`Encounter.period.end` = jam layanan berakhir, bukan `now()` (jam petugas mengklik):
`taskIdPelayanan.taskId7` (obat diserahkan) → `taskId5` (keluar poli) → `now()`.

## 8. Pola `kirim()` / `kirimInti()`

Tiap kartu memisah `kirim()` (pembungkus, dipanggil lewat event) dari `kirimInti()` (kerja).
`kirim()` **selalu** membalas `dispatch('rj-satu-sehat.langkah-selesai', langkah: '<nama>')`
apa pun hasilnya, supaya orkestrator "Kirim Semua" bisa melanjutkan dan modal tidak membeku
di langkah pertama yang gagal.
