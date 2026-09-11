---
name: blade-safe-edit
description: Aturan keselamatan saat mengedit file Blade / Volt di repo ini. Baca sebelum melakukan edit bulk atau pakai sed/perl/regex pada *.blade.php — mencegah match melebar yang merusak banyak file. Juga mencakup jebakan compiler Volt.
---

# Blade Safe Edit

File Blade di repo ini besar dan banyak nested tag. Edit ceroboh gampang merusak banyak file sekaligus.

## 1. JANGAN regex multiline untuk Blade
`perl -0` / `sed` multiline rawan match melebar dan merusak banyak file diam-diam.

- Pakai tool **Edit** dengan `old_string` presisi (sertakan konteks unik).
- Untuk perubahan berulang yang identik, pakai `replace_all: true` pada Edit — bukan sed.

## 2. Verifikasi sesudah edit — `php -l` TIDAK cukup
`php -l` lolos walau struktur tag Blade kacau. Selalu cek:

```bash
git diff --stat                 # pastikan jumlah file/baris berubah masuk akal
# hitung balance tag yang diedit, mis. modal/div pembuka vs penutup
grep -c '@if' file.blade.php; grep -c '@endif' file.blade.php
grep -c '<x-modal' file.blade.php; grep -c '</x-modal' file.blade.php
```
Diff-stat yang membengkak = tanda match melebar → batalkan.

## 3. Volt: hindari kata "use" di komentar PHP
Compiler Volt salah-strip komentar `//` bila ada substring `re-use` / `reuse` — sisanya terbaca sebagai statement `use` → **ParseError**.

```php
// SALAH di blok <?php Volt:  // re-use komponen ini
// BENAR: tulis ulang tanpa "use", mis. "pakai ulang komponen ini"
```

## 3b. Jangan tulis `@directive` di komentar PHP
Blade mengompilasi `@foreach`/`@if`/`@php` dst. **di mana pun** ia muncul, termasuk di komentar `//` dalam blok
`<?php` komponen ⚡ — `php artisan view:cache` gagal `Malformed @foreach statement`. Tulis "perulangan" /
"kondisi", bukan nama directive-nya.

## 3c. Jebakan lain yang pernah lolos review
- `$this->$rjNo = $rjNo;` (variable-variable, `$` ganda) lolos `php -l` dan Volt, tapi menulis properti bernama
  angka — bukan `$this->rjNo`. Grep `\$this->\$` sesudah edit bulk.
- Variabel `@php $x = … @endphp` di satu partial `@include` TIDAK terbaca di partial lain / induk; properti
  komponen & `$this` yang terbagi. Hitung ulang di partial yang memakainya.

## 4. Pola UI sudah terdokumentasi — jangan reinvent
Sebelum bikin komponen, cek `docs/` (lihat skill `ui-pattern-docs`): tombol standar, UI komponen umum, page-frame, dirty-modal, print PDF/TTD, tinymce, stable-lookup. Ikuti pola yang ada agar konsisten.
