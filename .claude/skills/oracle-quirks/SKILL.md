---
name: oracle-quirks
description: Gotcha & pola query Oracle untuk repo ini (Laravel + DB Oracle siklik yang dipakai bareng siklik-lite legacy). WAJIB dibaca sebelum menulis/men-debug query DB — terutama saat hasil query kosong tak terduga, error ORA-00904, kolom mixed-case, filter active_status, lookup shift, atau filter akun (gra_status/kas_status).
---

# Oracle Quirks (siklik-php82)

DB Oracle siklik dipakai bareng oleh aplikasi legacy **siklik-lite** (Laravel 10 + Livewire 2) dan aplikasi baru **siklik-php82**. DB = source of truth — JANGAN bikin migration/seeder untuk tabel existing. Banyak perilaku menyimpang dari MySQL/Postgres. Cek daftar ini sebelum menulis query.

## 1. Empty string `''` = NULL
Oracle memperlakukan `''` sebagai `NULL`. Jangan pernah pakai `<> ''` atau `= ''`.

```php
// SALAH — selalu false di Oracle
->whereRaw("nama <> ''")
// BENAR
->whereRaw("nama IS NOT NULL")
->whereRaw("LENGTH(TRIM(nama)) > 0")
```

## 2. Tidak support JSON_VALUE / JSON_QUERY / JSON_TABLE
Versi Oracle di sini melempar **ORA-00904** untuk fungsi JSON. Jangan dipakai di SQL.

- Pakai `INSTR(...)` / pattern-match string di SQL, **atau**
- Ambil kolom mentah lalu `json_decode()` di PHP.

## 3. Kolom mixed-case harus di-quote via DB::raw
Oracle melipat identifier ke UPPERCASE kecuali di-quote. Kolom mixed-case (mis. `requestTransferTime`) wajib:

```php
->select(DB::raw('"requestTransferTime" as request_transfer_time'))
```

## 4. active_status = string '1' / '0' (BUKAN 'Y'/'N')
Master legacy menyimpan `active_status` sebagai string `'1'` (aktif) / `'0'` (nonaktif).

```php
->where('active_status', '1')
```

## 5. Tabel shift = `rstxn_shiftctls` (BUKAN rsmst_shifts)
Lookup shift berjalan berdasar jam sekarang:

```php
->whereRaw("to_char(sysdate,'HH24:MI:SS') between shift_start and shift_end")
```

## 6. Akun: gra_status = N/L (BUKAN flag aktif), kas_status untuk akun kas
- `tkacc_gr_accountses.gra_status` = `'N'` (Neraca) / `'L'` (Laba-Rugi) — **klasifikasi laporan**, BUKAN flag aktif. JANGAN filter `gra_status = '1'`.
- Akun pusat siklik = `TKACC_ACCOUNTSES` (tidak ada `acmst_*`). Flag akun kas = kolom `kas_status` (tidak ada flag rj/ugd/ri/ci/co seperti di sirus).

## 7. Carbon 3 diffInSeconds sign terbalik
Repo pakai Carbon 3.11.0. `diffInSeconds($other, false)` tandanya kebalik dari Carbon 2. Untuk durasi pakai timestamp mentah:

```php
$durasi = $end->getTimestamp() - $start->getTimestamp();
```

## 8. Data dibuat lewat siklik-lite bisa bypass pola cache/JSON aplikasi baru
Entry yang dibuat lewat siklik-lite legacy bisa tidak mengikuti pola JSON/cache siklik-php82 → tak kelihatan di UI baru walau ada di DB. Saat data "hilang" di UI tapi ada di tabel, curigai jalur ini.

## 9. Cek nama kolom dulu — jangan menebak
Banyak kolom siklik beda dari dugaan (mis. `users.myuser_code`, bukan `emp_id`; `dimst_identitases` untuk identitas, bukan `rsmst_identitases`). Selalu verifikasi kolom ke DB/dokumentasi skema sebelum SELECT/INSERT.
