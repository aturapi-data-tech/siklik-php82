# Rujukan Berbasis Kompetensi Layanan (SRBK) — jalur FKTP

Modul rujukan rawat jalan klinik pratama ke gateway **`pcare-sisrute-rest`** BPJS Kesehatan,
yang meneruskannya ke **SATUSEHAT** (CarePlan + ServiceRequest dibuat oleh BPJS, bukan oleh kita).

Klinik ini = FKTP wilayah **Tulungagung**, salah satu wilayah piloting SRBK.
Sumber aturan di bawah: grup WhatsApp piloting "SATUSEHAT Rujukan X PCare X VClaim"
(April–September 2026), sampel payload/response yang beredar di grup itu, dan
*Skenario UAT Uji Coba SRBK (FKTP) ver 1.0*.

---

## 1. Arsitektur

```
EMR Rawat Jalan (panel Tindak Lanjut, kdStatusPulang = 4)
        │  PcareSisruteTrait
        ▼
BPJS  pcare-sisrute-rest  ──►  SATUSEHAT  (Task → CarePlan → ServiceRequest)
        │
        └─► balasan: No. Kunjungan/Rujukan PCare + No. Rujukan SATUSEHAT + serviceRequestId
```

Empat langkah, semuanya **POST kecuali pembatalan**:

| # | Endpoint | Kapan |
|---|---|---|
| 1 | `Sisrute/GetKriteriaRujukan` | setelah diagnosa utama ditetapkan |
| 2 | `Sisrute/GetFaskesRujukan` | setelah dokter memilih kriteria + wilayah + subspesialis + tanggal |
| 3 | `Sisrute/postKunjungan` | saat rujukan dikirim (menggantikan endpoint `kunjungan` biasa) |
| 4 | `Sisrute/deleteKunjungan` | pembatalan (menghapus **sampai pendaftaran PCare**) |

**Prasyarat mutlak:** Encounter SATUSEHAT kunjungan itu sudah terkirim
(`satusehat.encounterId` pada CLOB). Tanpa Encounter, langkah 1 pun tidak bisa jalan.

**Kunjungan non-rujuk tetap lewat `kunjungan` lama** (`PcareTrait::addKunjungan`).
Untuk satu kunjungan, kirim salah satu saja — jangan dua-duanya.

Tidak ada tahap setuju/tolak untuk rawat jalan (itu hanya ada di jalur FKRTL rawat inap/gawat darurat).

---

## 2. Konfigurasi & prasyarat administratif

`config/bpjs.php` grup `sisrute`, diisi dari `.env` (`SISRUTE_*`).
Bila `SISRUTE_*` kosong, nilainya **jatuh ke `bpjs.pcare.*`** — itu kemudahan lingkungan,
bukan izin memakai kredensial PCare untuk SRBK.

| config | env | catatan |
|---|---|---|
| `bpjs.sisrute.url` | `SISRUTE_URL` | dev: `https://dvlp.bpjs-kesehatan.go.id/pcare-sisrute-rest/api/v1.0` (tanpa `/` penutup) |
| `bpjs.sisrute.cons_id` / `secret_key` / `user_key` | `SISRUTE_*` | cons ID **khusus service** — lihat di bawah |
| `bpjs.sisrute.username` / `password` | `SISRUTE_*` | untuk header `X-authorization` |
| `bpjs.sisrute.simulasi` | `SISRUTE_SIMULASI` | `true` = jawab dari fixture, tanpa jaringan |
| `bpjs.sisrute.content_type` | `SISRUTE_CONTENT_TYPE` | **kosong di dev**, `application/json` di produksi |
| `bpjs.sisrute.kd_aplikasi` | `SISRUTE_KD_APLIKASI` | `095` (PCare FKTP) |
| `bpjs.sisrute.timeout` | `SISRUTE_HTTP_TIMEOUT` | 20 detik (SATUSEHAT lebih lambat dari PCare) |
| `satusehat.organization_id` | `SATUSEHAT_ORGANIZATION_ID` | dipakai sebagai `kodeFaskesSatuSehat` |

Yang **tidak bisa diselesaikan dari kode**:

1. **Cons ID khusus.** Service `pcare-sisrute-rest` didaftarkan terpisah dari PCare biasa.
   Cons ID PCare produksi yang dipakai di sini akan dibalas
   `Unauthorized! You are not registered for this service!` (berulang kali terjadi di grup,
   13–14 Apr 2026). Pengajuan dilakukan **faskes → KC BPJS setempat**; untuk klinik ini
   KC Tulungagung, karena uji coba difokuskan ke "kota Bandung dan kab. Tulungagung"
   (BPJS, 14/04/26 10.54). Kendala teknis pada cons ID yang sudah terbit → IT Wilayah
   (BPJS, 08/06/26 16.27).
2. **Whitelist IP** lewat ITSM BPJS (Formulir Pengajuan Akses Bridging SIM). Belum di-whitelist
   tampak sebagai *Connection timed out / refused*, bukan pesan yang menjelaskan.
   Bila egress lewat VPS, nyalakan `BPJS_PROXY_AKTIF` (lihat `docs/bpjs-whitelist-ip-proxy.md`).
3. **Masa berlaku cons ID dev** — `Unauthorized! Consumer ID is expired!` → perpanjangan ke
   IT Wilayah (BPJS, 24/08/26 11.08).

---

## 3. Jawaban atas tujuh pertanyaan spesifikasi

| Pertanyaan | Jawaban | Bukti |
|---|---|---|
| Header `X-Authorization` (Basic user:pass) dipakai? | **Ya.** `pcare-sisrute-rest` mewarisi kontrak header PCare: `X-cons-id`, `X-timestamp`, `X-signature`, `user_key`, `X-authorization: Basic base64(username:password:095)`. | Daftar header resmi yang dipakai peserta piloting menyebut `X-Authorization` (14/04/26 09.04); gateway rujukan pihak ketiga mengirim `username`+`password` di `dataheader` untuk path `…/pcare-sisrute-rest/Sisrute/GetKriteriaRujukan` (13/04/26 17.52). Nilai `:095` = kode aplikasi PCare FKTP yang sudah dipakai `PcareTrait::signature()`. |
| `kodeFaskesSatuSehat` pakai prefix `"Organization/"`? | **Tidak saat dikirim** — angkanya saja (`"100006775"`). Prefix `Organization/` hanya muncul di **response** `GetFaskesRujukan` (`"Organization/100026379"`) dan harus dibuang sebelum dipakai sebagai `kdppkSatuSehatTujuanRujukan`. | `get-kriteria-payload.json`, `payload GetFaskesRujukan I10.json`, `payloadRujukan.txt` vs `GetFaskes.json` (02/07/26 & 04/09/26). |
| Format `encounter.reference` | **Berbeda per langkah.** Langkah 1 & 2 (`GetKriteriaRujukan`, `GetFaskesRujukan`): `"Encounter/<uuid>"`. Langkah 3 (`postKunjungan`, di dalam `satuSehatRujukan`): **UUID polos tanpa prefix**. | `get-kriteria-payload.json` & `payload GetFaskesRujukan I10.json` (berprefix) vs `payloadRujukan.txt` dan `response simulasi 03092026.json` (`"reference":"9aec5072-…"`, 03/09/26). |
| Bentuk `kriteriaRujukan` di `GetFaskesRujukan` | **Objek berisi `item[]`**: `{"kriteriaRujukan":{"item":[{linkId,text,answer:[…]}]}}` — bukan array langsung. `linkId` dinamis per diagnosa, ambil persis dari langkah 1. **Tepat satu** item terisi. | `payload GetFaskesRujukan I10.json`, `GetFaskes.json`, `gate_faskes.json`; aturan "tepat satu" dikonfirmasi BPJS/SATUSEHAT 03/07/26 15.16–15.32 dan pesan penolakan 08/09/26 10.13. |
| Bentuk `satuSehatRujukan` di `postKunjungan` | Objek di level atas payload kunjungan: `kodeFaskesSatuSehat`, `idPasienSatuSehat`, `kdppkSatuSehatTujuanRujukan` (**Org ID SATUSEHAT faskes tujuan**, bukan kode PPK BPJS), `kdDokterSatuSehat`, `encounter.reference` (UUID polos), `patientInstruction`, `kriteriaRujukan.item[]` (satu item), `keteranganRujukan`, `codeJejaringWilayah{kodePropinsi,namaPropinsi,kodeKabupaten,namaKabupaten}`. | `payloadRujukan.txt`, `response simulasi 03092026.json`. Kekeliruan mengisi `kdppkSatuSehatTujuanRujukan` dengan kode PPK BPJS menghasilkan *"Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk"* (`insert_rujukan.json`). |
| Body `deleteKunjungan` | **Tidak ada sampel resmi** di folder chat. Yang pasti: verb **DELETE** (BPJS, 14/04/26 10.16 "methodnya ganti Delete") dan penghapusannya menjalar sampai pendaftaran PCare. Implementasi di sini: `DELETE {url}/Sisrute/deleteKunjungan` dengan body `{"noKunjungan":"…"}`, mengikuti pola `kunjungan` PCare (identifikasi memakai `noKunjungan`) dan pola `Rujukan/Delete` FKRTL yang juga berbadan. **Sesuaikan begitu katalog resminya didapat.** | 20/05/26 14.40 (menghapus sampai pendaftaran PCare, tidak ada work-around); 14/04/26 10.16. |
| Dev menolak `Content-Type`? | **Ya.** "Environment DEV BPJS (dvlp) gagal jika Content-Type dikirim. Production tetap menggunakan Content-Type" — karena perpindahan URL sementara. Karena itu `SISRUTE_CONTENT_TYPE` bawaan **kosong**; isi `application/json` saat pindah ke produksi. | 11/06/26 10.06–10.09. |

**Bonus — di mana nomor rujukan berada pada response `postKunjungan`:**

* Sukses gateway FKTP (21/08/26 15.28):
  `{"noKunjungan":"180105060826Y000004","serviceRequestId":"42cf…","noRujukanSatuSehat":"73711802608211001"}`.
  Nomor Rujukan BPJS **sama dengan** `noKunjungan`.
* Saat BPJS melapor gagal `Gagal mendapatkan nomor Rujukan Satu Sehat`, ServiceRequest FHIR
  ikut dilampirkan di pesan dan **rujukan mungkin sudah terbentuk**. Nomornya ada di
  `identifier[]`:
  * `http://sys-ids.kemkes.go.id/referral-number-pcare` → No. Rujukan PCare
    (`response saat create ulang setelah delete.json`, 30/07/26)
  * `http://sys-ids.kemkes.go.id/referral-number-satusehat` → No. Rujukan SATUSEHAT
    (`sample response no rujukan.json`)
  * `http://sys-ids.kemkes.go.id/servicerequest/<orgId>` → `serviceRequestId`
  * `meta.tag[].system = …/trace-id` → `traceId` (bahan lapor Issue Tracker)

  `PcareSisruteTrait::sisruteBacaNomorRujukan()` mencari kelima nilai itu **baik di
  `response` maupun di `raw`**, justru supaya kasus "gagal tapi sudah terbentuk" tidak hilang.

---

## 4. Katalog endpoint: body & response

### 4.1 `Sisrute/GetKriteriaRujukan` (POST)

```json
{
  "kodeFaskesSatuSehat": "100028369",
  "kodeDiagnosa": "Z37.0",
  "encounter": { "reference": "Encounter/73d4d339-4b71-4b51-b83d-8569523b839b" }
}
```

Response (`response.kriteriaRujukan` + `response.JejaringWilayah`):

```json
{
  "kriteriaRujukan": [
    { "linkId": "51947,69587", "text": "Terapi",         "type": "boolean", "item": null },
    { "linkId": "27038,44678", "text": "Tindakan Medis", "type": "text",    "item": null },
    { "linkId": "2129,19769",  "text": "Upaya Diagnosis","type": "boolean", "item": null }
  ],
  "JejaringWilayah": [
    { "linkId": "1", "text": "Jejaring wilayah rujukan", "type": "group", "item": [
        { "linkId": "1.1", "text": "Provinsi",       "type": "choice", "answerOption": [ { "valueCoding": { "code": "35", "display": "JAWA TIMUR" } } ] },
        { "linkId": "1.2", "text": "Kabupaten/Kota", "type": "choice", "answerOption": [ { "valueCoding": { "code": "3504", "display": "TULUNGAGUNG" } } ] }
    ] }
  ]
}
```

* `linkId` **dinamis per diagnosa** (aslinya berupa deretan angka berkoma) — jangan di-cache
  lintas diagnosa, jangan dikarang.
* `JejaringWilayah` aslinya Questionnaire FHIR: 34 provinsi + ±508 kabupaten/kota.
  Ratakan dengan `PcareSisruteTrait::sisruteWilayahDariKriteria()` →
  `['propinsiList' => [{kode,nama}], 'kabupatenList' => [{kode,nama,kodePropinsi}]]`
  (kode kabupaten berawalan 2 digit kode propinsinya).
* Diagnosa terlalu umum sering tidak punya kriteria: `A02` kosong, `A02.9` jalan (13–14/04/26).

### 4.2 `Sisrute/GetFaskesRujukan` (POST)

```json
{
  "kodeFaskesSatuSehat": "100006766",
  "kodeSubSpesialis": "94",
  "kodeSarana": "",
  "kodeDiagnosa": "J15.9",
  "estimasiRujuk": "04-09-2026",
  "kriteriaRujukan": { "item": [
    { "linkId": "25129,26260,28256", "text": "Tindakan Medis", "answer": [ { "valueString": "33.22" } ] }
  ] },
  "codeJejaringWilayah": { "kodePropinsi": "35", "namaPropinsi": "JAWA TIMUR", "kodeKabupaten": "3504", "namaKabupaten": "TULUNGAGUNG" },
  "encounter": { "reference": "Encounter/99895e29-a642-43fb-b5f1-26389800594b" }
}
```

* `estimasiRujuk` **`DD-MM-YYYY`**, bukan `Y-m-d`. Boleh hari ini.
* `kodePropinsi` **2 digit angka** (`"kodePropinsi pada codeJejaringWilayah tidak valid (harus 2 digit angka)"`).
* `kodeSubSpesialis` diambil dari referensi PCare (`getSpesialis` → `getReferensiSubSpesialis`),
  `kodeSarana` dari `getSarana`; boleh `""`.
* Tindakan Medis → `answer.valueString` = **ICD-9-CM yang valid** (03/07/26 15.24).

Response:

```json
{ "count": 17, "list": [
  { "kodeFaskesSatuSehat": "Organization/100026379", "kdppk": "1801R017", "nmppk": "RSUD KOTA MAKASSAR",
    "strataSatuSehat": "Dasar", "alamatPpk": "…", "telpPpk": "…", "kelas": "B", "nmkc": "MAKASSAR",
    "distance": 4.57, "jadwal": null, "jmlRujuk": 0, "kapasitas": 0, "persentase": 0 }
] }
```

* Pakai `RujukanKompetensiTampil::kandidatBaris()` untuk menampilkan — ia membuang prefix
  `Organization/`, menormalkan strata, dan menyaring jarak/waktu mustahil
  (`1.7976931348623E+308` = "tak terhitung", bukan jarak).
* `jadwal` null = tidak diinformasikan, bukan tutup. `jmlRujuk/kapasitas` pernah dilaporkan
  tidak berubah walau sudah dirujuk (30/06/26) — jangan dipakai sebagai angka keras.
* **Pilih satu**; `kdppk` dan `kodeFaskesSatuSehat` WAJIB dari baris yang sama.

### 4.3 `Sisrute/postKunjungan` (POST)

Payload = payload kunjungan PCare biasa + tiga tambahan:

```json
{
  "noKunjungan": null, "noKartu": "0002084343849", "tglDaftar": "03-09-2026", "kdPoli": "001",
  "…": "… field kunjungan PCare seperti biasa …",
  "kdStatusPulang": "4",
  "kdDokter": "207209",
  "kdDiag1": "N40",
  "rujukLanjut": {
    "tglEstRujuk": "04-09-2026",
    "kdppk": "1801R001",
    "subSpesialis": { "kdSubSpesialis1": "16", "kdSarana": "" },
    "khusus": null
  },
  "satuSehatRujukan": {
    "kodeFaskesSatuSehat": "100006775",
    "idPasienSatuSehat": "P20396196444",
    "kdppkSatuSehatTujuanRujukan": "100025592",
    "kdDokterSatuSehat": "10010886691",
    "encounter": { "reference": "9aec5072-38bf-4ab1-b411-33d2c213784e" },
    "patientInstruction": "Rujukan",
    "kriteriaRujukan": { "item": [ { "linkId": "27244", "text": "Tindakan Medis", "answer": [ { "valueString": "60.29" } ] } ] },
    "keteranganRujukan": "Rujukan",
    "codeJejaringWilayah": { "kodePropinsi": "73", "namaPropinsi": "SULAWESI SELATAN", "kodeKabupaten": "7371", "namaKabupaten": "KOTA MAKASSAR" }
  }
}
```

Response sukses: `{"noKunjungan":"…","serviceRequestId":"…","noRujukanSatuSehat":"…"}`.

### 4.4 `Sisrute/deleteKunjungan` (DELETE)

Body yang dikirim aplikasi ini: `{"noKunjungan":"…"}`.
**Menghapus sampai pendaftaran PCare** — tidak ada cara membatalkan rujukan saja
(BPJS, 20/05/26). Peringatkan petugas dengan keras di UI, dan setelah dihapus, pembuatan
ulang dimulai dari pendaftaran PCare lagi (`Pendaftaran tidak valid` bila dipaksa kirim ulang).

---

## 5. Node JSON di CLOB

Key root `rujukanKompetensi` pada `sktxn_rjhdrs.datadaftarpolirj_json`
(diisi panel EMR, dibaca modul cetak & layar pemantauan):

```
rujukanKompetensi: {
  kodeDiagnosa, diagnosaDesc, encounterId,
  kriteriaList: [{linkId,text,type}], kriteriaPilih: "<linkId>", kriteriaIcd9, kriteriaIcd9Desc,
  wilayahList, kodePropinsi, namaPropinsi, kodeKabupaten, namaKabupaten,
  kodeSpesialis, namaSpesialis, kodeSubSpesialis, namaSubSpesialis, kodeSarana, namaSarana,
  estimasiRujuk: "d/m/Y", catatan,
  kandidatList: [ <baris mentah response GetFaskesRujukan> ], kandidatIdx: null|int,
  hasil: { noRujukanPcare, noRujukanSatuSehat, serviceRequestId, traceId, tglRujukan: "d/m/Y H:i:s",
           tujuanNama, tujuanPpk, tujuanSatuSehat, dikirimOleh, dikirimPada, noKunjunganPcare },
  dibatalkan: { oleh, pada, alasan } | null
}
```

Aturan: ganti diagnosa/kriteria/wilayah/subspesialis/tanggal → **kosongkan** `kandidatList`
dan `kandidatIdx` (kandidat lama sudah tidak sah). Setelah `hasil.noRujukanSatuSehat` terisi,
isian terkunci. Persist memakai pola `EmrRJTrait`: `DB::transaction` + `lockRJRow` +
`findDataRJ` (baca ulang segar) + `updateJsonRJ` + `appendAdminLogRJ(..., 'MR')`.

---

## 6. Katalog error → penanganan

`PcareSisruteTrait::sisruteHintKatalog($pesanMentah)` mengembalikan kalimat tindakan
(atau string kosong bila tidak dikenali — tetap tampilkan pesan asli BPJS).

| Pesan mentah (potongan) | Artinya & tindakan |
|---|---|
| `Unauthorized! You are not registered for this service!` | Cons ID belum terdaftar untuk `pcare-sisrute-rest`. Ajukan akses service ke IT Wilayah / KC BPJS. |
| `Unauthorized! Consumer ID is expired!` | Cons ID dev kedaluwarsa → perpanjangan ke IT Wilayah. Tidak ada yang perlu diubah di aplikasi. |
| `Signature Service Tidak Sesuai` | `SISRUTE_CONS_ID` & `SISRUTE_SECRET_KEY` tidak sepasang, atau jam server melenceng (timestamp UTC). |
| `Connection timed out` / `Connection refused` / `Timeout was reached` | IP belum di-whitelist BPJS (ITSM), URL dev pindah, atau proxy salah. |
| `timeout akses ke API Sisrute/Satu Sehat` | BPJS tersambung, SATUSEHAT-nya yang timeout. Bukan salah isian; ulangi, lalu laporkan dengan trace-id. |
| `hanya boleh mengisi salah satu dari Terapi, Tindakan Medis, atau Upaya Diagnosis` | Kirim **tepat satu** item kriteria. Validasi ini diketatkan sejak 03/07/26. |
| `tidak mengandung Kriteria Rujukan dan Jejaring Wilayah` | SATUSEHAT tak punya kriteria untuk diagnosa itu → pakai ICD-10 lebih spesifik (`A02.9`, bukan `A02`); bila tetap kosong, laporkan. |
| `tidak mengandung Faskes Rujukan` / `Data Faskes Rujukan di Sisrute Tidak ditemukan` | Tidak ada kandidat untuk kombinasi diagnosa+subspesialis+wilayah+tanggal. Ubah wilayah/subspesialis/tanggal. |
| `Gagal mendapatkan nomor Rujukan Satu Sehat` | **Bug backend — rujukan mungkin sudah terbentuk.** JANGAN kirim ulang; periksa `identifier` di response lebih dulu (lihat §3 Bonus). Kirim ulang berisiko rujukan ganda / pendaftaran PCare terhapus. |
| `Pendaftaran tidak valid` | Pendaftaran PCare-nya sudah terhapus (umumnya efek `deleteKunjungan` atau percobaan kirim ulang). Daftarkan ulang pasien di PCare. |
| `Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk` | `kdppk` dan `kdppkSatuSehatTujuanRujukan` beda faskes. Ambil keduanya dari **satu baris kandidat**. |
| `PPK Rujuk tidak ditemukan di pemetaan Satu Sehat` | Faskes tujuan belum dipetakan BPJS↔SATUSEHAT di pusat. Pilih kandidat lain atau laporkan pemetaannya. |
| `dokter tidak valid` | Yang divalidasi **kdDokter BPJS**, bukan kode SATUSEHAT. Lengkapi kode dokter BPJS di master (13/08/26). |
| `kodeSubSpesialis tidak valid` | Ambil dari referensi PCare, jangan ketik manual. |
| `kodePropinsi … harus 2 digit angka` | Isi `"35"`, bukan `"3504"` atau nama propinsi. |
| `Format json tidak valid` | Cek `Content-Type` (dev menolaknya) dan bentuk `kriteriaRujukan` (objek `{item:[…]}`). |
| `No Mapping Rule matched` | Path/verb salah di gateway — `deleteKunjungan` memakai verb DELETE. |
| HTTP `429` / `Rate limit quota violation` | Kuota SATUSEHAT habis. Berhenti mengirim ulang; tunggu kuota diperpanjang. |

Setiap panggilan (payload + response mentah) tercatat di `WEB_LOG_STATUS` — itu bukti wajib
saat melapor ke Issue Tracker BPJS.

---

## 7. Mode simulasi

`SISRUTE_SIMULASI=true` → keempat method menjawab dari
`database/fixtures/sisrute/{get-kriteria-rujukan,get-faskes-rujukan,post-kunjungan,delete-kunjungan}.json`
**tanpa menyentuh jaringan**. Berguna untuk latihan petugas dan uji tampilan panel/cetak
selagi cons ID belum terbit.

* Fixture menyimpan amplop gateway `{metaData:{code,message}, response:{…}}`; key `_catatan`
  di dalamnya hanya penjelasan, diabaikan trait.
* Data pasien di fixture **dummy** (nama/NIK/no kartu/nomor rujukan karangan); nama faskes
  sengaja bernama "CONTOH".
* `get-faskes-rujukan.json` memuat 3 kandidat dengan strata berbeda (Dasar/Madya/Utama),
  salah satunya tanpa jadwal & kapasitas — untuk menguji tampilan nilai kosong.
* Panggilan simulasi **tetap dicatat** ke `WEB_LOG_STATUS`, dengan `http_req` berawalan
  `[SIMULASI] ` dan pesan berawalan `[SIMULASI] `, supaya latihan tidak tersamar sebagai
  panggilan sungguhan.

---

## 8. Perbedaan dengan sirus (FKRTL)

| | siklik (FKTP) | sirus (FKRTL, branch `feat/rujukan-antar-rs`) |
|---|---|---|
| Gateway | `pcare-sisrute-rest` | `vclaim-sisrute-rest` |
| Path | `Sisrute/GetKriteriaRujukan`, `Sisrute/GetFaskesRujukan`, `Sisrute/postKunjungan`, `Sisrute/deleteKunjungan` | `Rujukan/GetKriteriaRujukan`, `Rujukan/GetFaskesRujukan`, `Rujukan/Insert`, `Rujukan/Delete` |
| Pembawa rujukan | payload **kunjungan PCare** + `rujukLanjut` + `satuSehatRujukan` | objek `request.t_rujukan` (butuh `noSep`) |
| Pembatalan | `deleteKunjungan` — menghapus **sampai pendaftaran** | `Rujukan/Delete` dengan `noRujukan` |
| Jalur IGD/ranap & bundle FHIR sendiri | **tidak ada** (klinik pratama) | ada (`SatuSehatRujukanTrait`, Task/CarePlan/ServiceRequest dikirim sendiri) |
| Kuesioner gawat darurat Q100, Kelompok Layanan `TK000562`, `performerType` SNOMED | **tidak dipakai** | dipakai di jalur FHIR |
| Trait | `PcareSisruteTrait` — method **publik non-static**, mengembalikan **array** | `SisruteTrait` — method **static**, mengembalikan `response()->json` |
| Log | `WEB_LOG_STATUS` **tanpa** kolom `http_payload`; payload disimpan di dalam kolom `response` (key `payload`) | ada kolom `http_payload` |
| Header | menyertakan `X-authorization` (PCare) | hanya `user_key` + trio signature (VClaim) |

Header `X-authorization` dan absennya `http_payload` adalah dua beda yang paling mudah
menjebak saat menyalin kode dari sirus ke sini.
