---
name: diagnosa-flow
description: Arsitektur & jebakan diagnosa ICD-10 (RSMST_MSTDIAGS, LOV diagnosa, EMR, PCare). Baca sebelum mengubah/menambah apa pun yang memilih atau menyimpan diagnosa — master ICD-10 bisa punya icdx kembar yang bikin lookup flag naive (value/first) salah baris, plus aturan icdx vs diag_id per konsumen.
---

# Diagnosa Flow (siklik-php82)

Siklik = klinik pratama: konsumen eksternal diagnosa adalah **PCare BPJS** (bukan SEP/VClaim/iDRG seperti di sirus). Dok arsitektur lengkap versi sirus: `sirus-php82/docs/diagnosa-architecture.md` — pola dasarnya sama.

## Peta komponen

| Lapisan | Lokasi |
|---|---|
| Master ICD-10 | `RSMST_MSTDIAGS` (`diag_id` PK, `icdx`, `diag_desc`), UI master-diagnosa di `pages/master/master-klinik/master-diagnosa/` |
| Picker standar (SATU-satunya) | `livewire/lov/diagnosa/lov-diagnosa.blade.php` — semua pemilihan diagnosa lewat sini, jangan bikin picker baru |
| PCare / sistem eksternal BPJS | kirim **icdx** (kode ICD-10), bukan diag_id |

> Catatan port: guard `valid_code !== 1` & `primaryOnly` yang ada di `choose()` LOV sirus **belum diport** ke lov-diagnosa siklik. Kalau modul yang butuh flag iDRG/validasi primer diport, bawa guard-nya sekalian dari sirus.

## Jebakan utama

1. **icdx kembar di master**: satu `icdx` bisa punya >1 baris (`diag_id` beda, flag beda).
   JANGAN lookup flag/desc via `value()`/`first()` by icdx tanpa ordering — bisa dapat baris yang salah.
   - Lookup massal + `keyBy('icdx')`: tambah `->orderBy(...)` agar baris terbaik menang.
   - Lookup **by diag_id saja** (PK unik, mis. dari payload LOV) aman pakai `value()`.
2. **icdx vs diag_id**: ke sistem eksternal (PCare BPJS) kirim `icdx`;
   untuk join/simpan internal pakai `diag_id`.
3. **Jangan hapus baris master** — `diag_id` legacy direferensikan transaksi lama.
   Nonaktifkan via flag, bukan DELETE.
