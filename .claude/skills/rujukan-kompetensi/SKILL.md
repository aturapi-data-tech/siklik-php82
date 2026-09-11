---
name: rujukan-kompetensi
description: Aturan payload & jebakan Rujukan Berbasis Kompetensi Layanan (SRBK) FKTP — gateway pcare-sisrute-rest BPJS, jalur Rawat Jalan. WAJIB dibaca sebelum menyentuh PcareSisruteTrait, panel rujukan di EMR RJ, cetak Surat Rujukan, atau layar /rujukan/keluar. Juga saat menyalin kode rujukan dari sirus-php82 (FKRTL) — banyak yang TIDAK berlaku di FKTP.
---

# Rujukan Berbasis Kompetensi (SRBK) FKTP

Rincian lengkap (katalog endpoint, contoh body/response, katalog error, bukti dari grup
piloting): **`docs/rujukan-kompetensi.md`**. Berkas ini hanya aturan yang paling mudah dilanggar.

## Kapan dibaca
- Menambah/mengubah apa pun di `app/Http/Traits/BPJS/PcareSisruteTrait.php`.
- Menyusun payload rujukan, membaca response-nya, atau menampilkan kandidat faskes.
- Menyalin kode dari sirus-php82 branch `feat/rujukan-antar-rs` (itu FKRTL, beda gateway).
- Mendiagnosis pesan error BPJS/SATUSEHAT saat merujuk.

## Aturan payload (yang sering salah)

1. **`encounter.reference` beda bentuk per langkah.**
   `GetKriteriaRujukan` & `GetFaskesRujukan` → `"Encounter/<uuid>"`.
   `postKunjungan` (di dalam `satuSehatRujukan`) → **UUID polos**, tanpa prefix.
2. **`kodeFaskesSatuSehat` dikirim TANPA `"Organization/"`.** Prefix itu hanya ada di
   *response* `GetFaskesRujukan` dan wajib dibuang sebelum dipakai lagi.
3. **`kriteriaRujukan` adalah OBJEK `{ "item": [ … ] }`**, bukan array polos — dan isinya
   **TEPAT SATU** item. Dua/tiga item terisi ditolak:
   *"hanya boleh mengisi salah satu dari Terapi, Tindakan Medis, atau Upaya Diagnosis"*.
   Pakai `RujukanKompetensiOptions::bangunKriteriaRujukan()`.
4. **`linkId` DINAMIS per diagnosa.** Selalu dari response `GetKriteriaRujukan` yang sama;
   jangan di-cache lintas diagnosa, jangan dikarang.
5. **Tindakan Medis → `answer.valueString` = ICD-9-CM valid.** Dua kriteria lain memakai
   `valueBoolean: true`.
6. **Tanggal ke BPJS `DD-MM-YYYY`** (`estimasiRujuk`, `tglEstRujuk`) — bukan `Y-m-d`.
   Node JSON internal & tampilan memakai `d/m/Y`.
7. **`kodePropinsi` 2 digit angka** (`"35"`), diambil dari `JejaringWilayah`.
8. **`kdppk` (BPJS) dan `kdppkSatuSehatTujuanRujukan` (Org ID SATUSEHAT) WAJIB dari SATU
   baris kandidat yang sama.** Beda baris → *"Satu Sehat Tujuan Rujukan tidak sesuai dengan
   PPK Dirujuk"*.
9. **`kdDokter` = kode dokter BPJS**, bukan kode SATUSEHAT. Kosong/keliru → *"dokter tidak valid"*.
10. **`kdStatusPulang` "4"** menandai kunjungan-rujuk; kunjungan itu dikirim lewat
    `Sisrute/postKunjungan` **dan tidak boleh juga dikirim** lewat endpoint `kunjungan` lama.

## Jebakan

- **`Gagal mendapatkan nomor Rujukan Satu Sehat` bukan berarti gagal.** Rujukan bisa saja sudah
  terbentuk; nomornya menempel di `identifier[]` pesan error. Periksa dulu dengan
  `sisruteBacaNomorRujukan()` sebelum mengirim ulang — kirim ulang bisa menghasilkan rujukan
  ganda atau malah `Pendaftaran tidak valid` (pendaftaran PCare terhapus).
- **`deleteKunjungan` menghapus sampai pendaftaran PCare**, bukan cuma rujukannya. Tidak ada
  work-around. UI wajib memperingatkan keras; sesudahnya harus daftar ulang dari PCare.
- **Server DEV (dvlp) menolak permintaan yang membawa `Content-Type`.** Biarkan
  `SISRUTE_CONTENT_TYPE` kosong di dev, isi `application/json` di produksi.
- **Cons ID `pcare-sisrute-rest` terpisah dari cons ID PCare.** Memakai cons ID PCare →
  *"Unauthorized! You are not registered for this service!"* — itu urusan pengajuan ke
  KC BPJS/IT Wilayah, bukan bug kode. IP juga harus di-whitelist lewat ITSM.
- **Diagnosa terlalu umum tidak punya kriteria** (`A02` kosong, `A02.9` jalan). Tampilkan
  saran itu ke petugas, jangan diam-diam menggantinya.
- **`WEB_LOG_STATUS` siklik TIDAK punya kolom `http_payload`** (sirus punya). Payload
  disimpan di dalam kolom `response` pada key `payload` — jangan menyalin `insert()` sirus apa adanya.
- **Jarak/waktu bisa `1.7976931348623E+308`** = "tak terhitung". Selalu lewat
  `RujukanKompetensiTampil::jarak()` / `::waktu()`.
- **`jadwal: null`** = tidak diinformasikan, bukan "tutup". `jmlRujuk`/`kapasitas` pernah
  dilaporkan tidak ter-update — jangan dipakai sebagai angka keras.
- **Nama method trait semuanya berawalan `sisrute`** karena komponen memakainya berdampingan
  dengan `PcareTrait` (yang punya `signature()`, `stringDecrypt()`, `sendResponse()`).
  Jangan menambah method tanpa awalan itu.

## Berkas terkait

| Berkas | Isi |
|---|---|
| `app/Http/Traits/BPJS/PcareSisruteTrait.php` | 4 endpoint + katalog hint + pembaca nomor rujukan |
| `app/Support/Rujukan/RujukanKompetensiOptions.php` | kriteria, petunjuk, `STATUS_PULANG_RUJUK`, pembangun `kriteriaRujukan` |
| `app/Support/Rujukan/RujukanKompetensiTampil.php` | `kandidatBaris()`, `jarak()`, `waktu()`, `infoTujuan()` |
| `config/bpjs.php` → `sisrute` | kredensial, `simulasi`, `content_type`, timeout |
| `database/fixtures/sisrute/*.json` | jawaban mode simulasi (`SISRUTE_SIMULASI=true`) |
| `docs/rujukan-kompetensi.md` | dokumentasi lengkap + katalog error |
