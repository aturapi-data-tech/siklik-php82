# Standar Struktur Folder & Penempatan Berkas

Aturan **di mana sebuah berkas harus tinggal** dan **bagaimana ia dinamai** — untuk `app/` dan
`resources/views/`. Tujuannya satu: programmer baru bisa menebak lokasi berkas tanpa `grep`, dan
programmer lama tidak perlu memutuskan ulang tiap kali menambah modul.

Dokumen ini **melengkapi**, bukan menggantikan:
- `docs/standar-master-module.md` — isi & anatomi modul master (2 berkas `⚡list` + `⚡actions`)
- `docs/dokumen-view-pattern.md`, `docs/ttd-pattern-pdf-print.md` — isi modul dokumen & cetakan
- `docs/page-frame-pattern.md`, `docs/standar-ui-komponen.md` — markup di dalam berkas
- `docs/struktur-tabel.md` — nama tabel & view Oracle (prefix `SKMST_/SKTXN_/SKACC_/SKVIEW_`)

Di sini kita hanya bicara **pohon direktori dan nama berkas**.

> **Konteks siklik ≠ sirus.** siklik adalah SIM **klinik pratama (FKTP)**: hanya ada jalur
> **Rawat Jalan (`rj`)**. Tidak ada UGD, Rawat Inap, casemix/INA-CBG, kamar operasi, maupun
> pelaporan SIRS/RL. Semua aturan "per jalur" di dokumen ini karena itu hanya punya satu penghuni
> — tapi bentuk aturannya tetap dipertahankan supaya penambahan jalur baru (mis. UGD 24 jam)
> tidak perlu mendesain ulang.

> **Kondisi sekarang (audit 11 Sep 2026):** 362 berkas Blade — **220 SFC Volt** berkelas
> + **142 partial** markup murni; `app/` 62 berkas PHP (31 trait, 5 class `Support`);
> `routes/web.php` 74 `Route::livewire`; 31 LOV; 69 komponen anonim.
> Rename ⚡ massal (109 berkas) selesai di commit `448b18d` — lihat §3.1.

---

## 1. Prinsip

| # | Prinsip | Konsekuensi praktis |
|---|---|---|
| P1 | **Satu modul = satu folder.** | Semua berkas milik satu layar hidup berdampingan; tidak ada berkas modul yang nyempil di folder induk. |
| P2 | **Nama folder = nama modul = nama berkas utama = namespace event.** | `master-agama/` → `⚡master-agama.blade.php` → event `master.agama.*`. Tiga-tiganya harus sinkron. |
| P3 | **Lokasi mengikuti PEMILIK, bukan pemakai.** | Cetakan kwitansi RJ dipakai kasir, apotek, dan EMR → tinggal di `pages/components/modul-dokumen/rj/`, bukan di dalam salah satu pemakainya. |
| P4 | **`⚡` menandai komponen, ketiadaannya menandai partial.** | Terlihat dari `ls` mana yang punya state dan mana yang cuma markup. |
| P5 | **Kebab-case, Bahasa Indonesia, tanpa singkatan baru.** | Akronim domain yang sudah baku (`rj`, `emr`, `lov`, `bpjs`, `rm`, `tu`) dipakai apa adanya. |
| P6 | **Kedalaman maksimum 4 level di bawah `pages/`.** | `pages/transaksi/rj/emr-rj/modul-dokumen/<modul>/` sudah 5 — lihat §4.3 untuk pengecualian resmi. |

---

## 2. Peta direktori resmi — `resources/views/`

```
resources/views/
├── components/                  # KOMPONEN BLADE ANONIM  <x-...>  — TANPA state, TANPA kelas Volt
│   ├── <nama>.blade.php         #   umum lintas modul: x-modal, x-text-input, x-now-button (69 berkas)
│   └── <namespace>/             #   berkelompok: consent/, logo/, lov/, pdf/, rm/, signature/
│
├── layouts/                     # layouts::app, layouts::guest
│
├── livewire/                    # KOMPONEN LIVEWIRE LINTAS-MODUL (dipanggil <livewire:...>)
│   ├── ⚡dashboard.blade.php
│   └── lov/<entitas>/⚡lov-<entitas>.blade.php    # 31 LOV — acuan konsistensi terbaik di repo
│
└── pages/                       # HALAMAN & KOMPONEN MILIK HALAMAN (namespace `pages::`)
    ├── components/              #   komponen pages LINTAS-LAYAR (dipakai >1 area) — §4.4
    │   ├── modul-dokumen/rj/    #     cetakan + modal dokumen RJ
    │   └── rekam-medis/         #     viewer & cetakan rekam medis (rj/, penunjang/, etiket/,
    │                            #     rekam-medis-display/)
    ├── master/master-<grup>/master-<nama>/       #   §4.1
    ├── transaksi/<jalur|fungsi>/<modul>/         #   §4.2
    ├── manajemen/rs/<unit>/<modul>/              #   §4.3
    ├── database-monitor/<modul>/
    └── panduan-dev/<modul>/
```

**Aturan tegas — tiga tempat komponen, tiga tujuan berbeda:**

| Folder | Isinya | Cara dipanggil | Boleh punya kelas Volt? |
|---|---|---|---|
| `components/` | komponen presentasi anonim | `<x-nama>` / `<x-ns.nama>` | ❌ tidak — kalau butuh state, ia bukan penghuni sini |
| `livewire/` | komponen ber-state **lintas modul** (LOV, dialog global) | `<livewire:lov.dokter>` | ✅ wajib |
| `pages/` | halaman + komponen ber-state **milik halaman** | `Route::livewire` / `<livewire:pages::…>` | ✅ (kecuali partial `@include`) |

Uji cepat sebelum menaruh: *“Apakah ia punya state/`wire:model`?”* → tidak = `components/`.
*“Apakah dipakai lebih dari satu area?”* → ya = `livewire/`, tidak = `pages/<area>/<modul>/`.

---

## 3. Kontrak penamaan berkas

### 3.1 Prefix `⚡` — wajib, dan artinya tunggal

> **`⚡` = berkas ini SFC Volt berkelas** (`new [#[Layout]] class extends Component`).
> **Tanpa `⚡` = partial markup murni** yang di-`@include` (tanpa blok `<?php new class`).

Ini konvensi native Livewire 4 dan sudah disetel di `config/livewire.php`
(`make_command.emoji => true`), jadi setiap `php artisan make:livewire` sudah patuh otomatis.

`⚡` **tidak** ikut dalam string resolusi Livewire (`pages::master.master-klinik.master-agama.master-agama`
cocok dengan `⚡master-agama.blade.php`), jadi menambah/melepas prefix **tidak memutus referensi** —
selama referensinya lewat resolver Livewire, bukan view finder.

**Keadaan sekarang: 220 SFC ber-`⚡` (100%), 0 SFC tanpa `⚡`, 0 partial ber-`⚡`.**
109 berkas di-rename sekaligus di commit `448b18d` (pages + LOV + welcome). Tidak ada pengecualian.

> **Jebakan yang harus dihindari:** kalau sebuah SFC menulis `render()` yang memanggil
> `view('<nama-dirinya>')`, ia mengikat nama berkas ke **view finder** (bukan resolver Livewire)
> sehingga berkasnya tidak bisa di-rename dan `⚡` akan memutusnya. Jangan tulis `render()` di SFC
> kecuali ia mengembalikan view yang **berbeda** dari dirinya.

Cek kepatuhan (dua-duanya harus kosong):

```bash
cd resources/views
# Harus kosong: SFC Volt yang belum ber-⚡
for f in $(find . -name '*.blade.php' ! -name '⚡*'); do
  grep -qE '^new .*class extends' "$f" && echo "KURANG ⚡: $f"; done

# Harus kosong: berkas ber-⚡ yang ternyata partial
for f in $(find . -name '⚡*.blade.php'); do
  grep -qE '^new .*class extends' "$f" || echo "SALAH ⚡: $f"; done
```

### 3.2 Suffix peran berkas

| Suffix | Peran | SFC (`⚡`) atau partial? | Sebaran nyata di siklik |
|---|---|---|---|
| *(tanpa suffix)* = nama modul | LIST / layar utama modul | **SFC** | — |
| `-actions` | modal create/edit + semua `validate()`/insert/update/delete | **SFC** | 66 SFC, 0 partial |
| `-tab` | isi satu tab dari layar bertab | **partial** | 0 SFC, 26 partial |
| `-view` | penampil read-only (viewer rekam medis) | **partial** | 0 SFC, 4 partial |
| `-print` | badan cetak dompdf (dirender via layout `x-pdf.layout-*`) | **partial** | 0 SFC, 11 partial |
| `-<bagian>` | pecahan section dari berkas yang kebesaran (§5) | **partial** | mis. 9 partial `master-pasien-actions-*` |

Perhatikan: **hanya layar utama dan `-actions` yang jadi komponen.** Semua yang lain partial —
konsisten dengan §5: memecah berkas dilakukan dengan `@include`, bukan dengan menambah komponen
Livewire anak (tiap komponen anak = satu round-trip + satu titik race Alpine/morph). Jadi `-tab`
BUKAN komponen per tab; ia markup tab yang state-nya tetap di induk.

Tab yang jumlahnya banyak dikumpulkan di subfolder **`tabs/`** (jamak):
`pages/transaksi/rj/emr-rj/<section>/tabs/<nama>-tab.blade.php` — dipakai di `anamnesa/`,
`pemeriksaan/`, `penilaian/`, `perencanaan/`.

Suffix di luar tabel ini **tidak dibuat baru**. Prefix `_` untuk menandai partial **tidak dipakai**
(peran partial sudah dinyatakan oleh absennya `⚡`).

### 3.3 Prefix area khusus

| Prefix | Arti | Lokasi |
|---|---|---|
| `rm-` | kartu + modal satu dokumen rekam medis di dalam EMR | `pages/transaksi/rj/emr-rj/<section>/` dan `…/modul-dokumen/<modul>/` |
| `cetak-` | pembungkus cetak (tombol/modal) berpasangan dengan `-print` | `pages/components/modul-dokumen/rj/<modul>/`, `pages/components/rekam-medis/rj/<modul>/` |
| `lov-` | list-of-value | `livewire/lov/<entitas>/` |

### 3.4 Suffix jalur `-rj` — wajib untuk SEMUA modul-dokumen, di folder DAN nama berkas

**Setiap** folder & berkas di bawah `emr-rj/modul-dokumen/` menyandang jalurnya — bukan hanya yang
kebetulan ada di lebih dari satu jalur. Alasannya bukan estetika: saat jalur kedua lahir (UGD),
berkasnya **berbeda isi** (tabel sumber, kolom, guard), sering dibuka bersamaan, dan tanpa suffix
tab editor bernama identik. Menetapkan aturannya sekarang jauh lebih murah daripada me-rename
belasan folder nanti.

Bentuk benar:

```
pages/transaksi/rj/emr-rj/modul-dokumen/general-consent-rj/⚡rm-general-consent-rj-actions.blade.php
pages/transaksi/rj/emr-rj/modul-dokumen/suket-rj/tabs/suket-sehat-rj-tab.blade.php
```

Jalur ditulis **sebelum** suffix peran (`-rj-tab`, bukan `-tab-rj`).

**Satu pengecualian: jangan stutter.** Kalau nama dokumen sudah memuat jalurnya, tidak ditambah lagi.

> Status nyata: **nama berkas sudah patuh** (`⚡rm-general-consent-rj-actions`,
> `⚡rm-inform-consent-rj-actions`, `⚡rm-suket-rj-actions`), tapi **nama foldernya belum**
> (`general-consent/`, `inform-consent/`, `suket/`), dan `suket/tab/` masih tunggal dengan dua
> partial tanpa `-rj`. Tercatat sebagai backlog §8 item 1–2.

### 3.5 Akronim di nama folder

Akronim ditulis **utuh dan huruf kecil**: `rj`, `bpjs`, `emr`, `rm`, `tu`, `rs`.
**JANGAN** memecahnya per huruf — pola `r-j/`, `b-p-j-s/` adalah artefak konversi otomatis dari
PascalCase (`RJ` → `r-j`), bukan keputusan desain.

Status: sudah bersih. `pages/components/modul-dokumen/r-j/` dan `pages/components/rekam-medis/r-j/`
sudah menjadi `…/rj/`; tidak ada lagi folder akronim terpecah di `resources/` maupun `app/`.

### 3.6 Nama berkas ≠ kunci data

Aturan kebab-case berlaku untuk **nama berkas**, bukan untuk nilai yang sudah tersimpan di DB.
Bila sebuah pengenal dipakai ganda — sebagai nama berkas DAN sebagai nilai yang dipersistensi —
yang boleh diubah hanya nama berkasnya; nilai tersimpan tetap apa adanya, dan penurunan nama
berkas dilakukan eksplisit di kode (mis. `\Illuminate\Support\Str::kebab($id)`).

Cek pertanyaan ini sebelum me-rename apa pun: *“apakah nama ini pernah ditulis ke DB?”*
Di siklik jebakan terbesarnya ada di kunci JSON EMR (`AdministrasiRJ.userLogs`, `userLogs`,
`AdministrasiRj.userLog` — tiga ejaan hidup berdampingan di data lama): **kunci JSON tidak boleh
di-rename**, yang boleh dirapikan hanya berkas pembacanya.

---

## 4. Aturan penempatan per area

### 4.1 `pages/master/` — dua level: grup lalu modul

```
pages/master/master-<grup>/master-<nama>/
       grup: akuntansi | apotek | klinik | lab | tarif | wilayah
```

Ikuti `docs/standar-master-module.md` apa adanya: `master-<nama>/` + 2 berkas `⚡`.
Acuan kanonik **`master-klinik/master-agama/`**.

Sebaran sekarang (42 modul): `master-klinik` 19, `master-apotek` 11, `master-akuntansi` 4,
`master-wilayah` 4, `master-tarif` 3, `master-lab` 1.

Level grup **tidak** ikut ke namespace event maupun nama route — keduanya tetap
`master.<nama>` / `master.<nama-tanpa-prefix>` (§7, dan `standar-master-module.md` §2).

### 4.2 `pages/transaksi/` — jalur pelayanan & fungsi

```
pages/transaksi/
├── rj/                        # SATU-SATUNYA jalur pelayanan di klinik pratama
│   ├── daftar-rj/             # pendaftaran (LIST + *-actions per integrasi)
│   ├── booking-rj/  display-pasien-rj/
│   ├── pelayanan-rj/
│   ├── administrasi-rj/       # 1 berkas per pos biaya: kasir-rj, obat-rj, …
│   ├── eresep-rj/
│   ├── antrian-apotek-rj/  antrian-kasir-rj/
│   ├── emr-rj/
│   │   ├── ⚡emr-rj.blade.php          # shell EMR + tab  (sekarang masih ⚡erm-rj — backlog §8/8)
│   │   ├── <section>/                  # anamnesa, pemeriksaan, penilaian, perencanaan,
│   │   │   └── tabs/                   # diagnosa, log-aktivitas
│   │   └── modul-dokumen/<modul>-rj/
│   ├── satu-sehat/  task-id-pelayanan/  # integrasi eksternal
└── <fungsi>/                  # apotek, gudang, keuangan, penunjang
    └── <modul>/
```

Satu berkas per pos biaya di `administrasi-rj/` adalah pola yang benar dan dipertahankan — pos
biaya tumbuh terus, dan tiap pos punya tarif + audit log sendiri.

Modul milik fungsi (bukan jalur) tinggal di `transaksi/<fungsi>/<modul>/`:
`keuangan/` 9 modul, `gudang/` 4, `penunjang/laborat/`, `apotek/`.

### 4.3 `pages/manajemen/` — laporan

```
manajemen/<sumber>/<unit>/<modul>/
       sumber: rs          (daftar tertutup; `sirs`/`vclaim` belum ada di siklik)
       unit  : rj | tu     (klinik pratama; belum ada penunjang/ri/ugd)
```

Contoh nyata: `manajemen/rs/rj/laporan-kunjungan-rj/`, `manajemen/rs/tu/pendapatan-klinik/`.
Ini satu-satunya tempat kedalaman 5 diizinkan (pengecualian resmi atas P6), karena format laporan
ditentukan pihak luar dan pengelompokan per sumber membuat penambahan regulator baru tidak
mengacak folder yang sudah ada.

Laporan lintas unit / hub dashboard boleh memakai bentuk pendek `manajemen/<modul>/`.

### 4.4 `pages/components/` — komponen pages lintas-layar

Isi: cetakan (`-print` + pembungkus `cetak-*`), viewer dokumen (`*-view-rj`), dan modal yang
dipanggil dari beberapa layar sekaligus. Struktur:

```
pages/components/<domain>/<jalur|kelompok>/<modul>/<berkas>.blade.php
       domain          : modul-dokumen | rekam-medis
       jalur/kelompok  : rj  (atau kelompok fungsi: etiket, penunjang, rekam-medis-display)
```

Sebuah berkas naik ke sini **hanya** kalau pemakainya >1 layar. Kalau cuma dipakai satu layar, ia
tinggal di folder modulnya (P3).

---

## 5. Batas ukuran berkas

Melanjutkan `standar-master-module.md` §7, digeneralisasi ke semua area:

| Jenis | Ideal | Wajib pecah di |
|---|---|---|
| LIST / layar utama | ≤ 300 baris | > 600 |
| `-actions` (form/modal) | ≤ 400 baris | > 800 |
| `-print` | ≤ 500 baris | > 900 |
| Trait / class `Support` | ≤ 400 baris | > 700 |

Cara pecah: **partial per section logis** (`<modul>-<bagian>.blade.php`, di-`@include`) — markup
murni, state tetap di induk. Bukan dengan menambah komponen Livewire anak, karena tiap komponen
anak menambah satu round-trip dan satu titik race Alpine/morph.

**Tiga syarat partial**, semuanya wajib dicek sebelum memecah:

1. **Imbang tag** — `<div>`, komponen `<x-*>`, `@if`, `@foreach`, dan penanda komentar Blade harus
   imbang DI DALAM partial. Batas yang enak dibaca belum tentu imbang. (Awas menghitung komponen
   self-closing multi-baris: `<x-text-input\n … />` — lookahead `/>` tidak melewati newline, jadi
   regex naif mengiranya tag pembuka.)
2. **Rekonstruksi byte-eksak** — induk dengan tiap `@include` diganti kembali oleh isi partial-nya
   harus sama byte-per-byte dengan berkas asli. Ini invarian **tekstual**, jadi ia tidak bergantung
   pada apakah suatu cabang `@if` kebetulan ikut dirender saat diuji — kelemahan yang dimiliki
   verifikasi berbasis render.
3. **Impor kelas ikut dipindah** — dan ini yang paling mudah terlewat.
   **Partial `@include` dikompilasi ke berkas TERPISAH, jadi ia TIDAK mewarisi `use` dari blok
   kelas induk.** Ia mewarisi **variabel**, bukan **import**. Begitu blok yang diekstrak memanggil
   nama kelas pendek (`KamusData::…`, `Carbon::…`), partial-nya meledak `Class not found`.

   Perbaikannya: tambah directive `@use('App\Support\Skema\KamusData')` di kolom 0 pada partial —
   **bukan** `@php use …; @endphp` yang dilarang di berkas komponen.

> **Syarat 2 tidak menjamin syarat 3.** Teks identik ≠ scope import identik — dan render komponen
> `-actions` sendirian pun bisa lolos, karena cabang yang memanggil kelas itu hanya hidup kalau
> induk mengirim props. Yang menangkapnya: sapu halaman utuh lewat HTTP kernel, plus pemeriksa
> nama kelas pendek di partial (§8).

Yang berubah setelah pecah hanyalah **baris kosong**: `@include` menelan newline di sekitarnya.
Tidak berpengaruh pada tampilan karena letaknya antar-blok.

**Batas ini hanya berlaku untuk MARKUP.** Berkas yang besar karena blok kelas Volt-nya tidak
terbantu oleh pemecahan partial; yang perlu dikurangi kelasnya (pisah ke trait/`Support`), dan itu
keputusan desain per modul.

Sebaran sekarang: **0 berkas > 1.500 baris**, 13 berkas 801–1.500, 46 berkas 401–800, 303 ≤ 400.
Yang melewati ambang “wajib pecah”: 19 LIST (terbesar `⚡daftar-rj` 1.093, `⚡laboratorium-display`
1.036, `⚡kasir-rj` 995), 7 `-actions` (`⚡penerimaan-medis-actions` 1.429,
`⚡penerimaan-non-medis-actions` 1.407, `⚡daftar-rj-actions` 1.374), 0 `-print`, dan 1 trait
(`BPJS/PcareTrait.php` 1.220).

---

## 6. Peta direktori resmi — `app/`

```
app/
├── Console/Commands/          # perkakas: siklik:dok-tabel, migrasi:infokes-dump
├── Http/
│   ├── Controllers/           # HANYA sisa Breeze (auth, profile) + endpoint API non-UI.
│   │                          # Fitur berlayar = Volt SFC, bukan controller
│   ├── Middleware/  Requests/
│   └── Traits/                # mixin untuk komponen Livewire — §6.1
├── Models/                    # Eloquent hanya untuk tabel milik Laravel (User, SnomedCode).
│                              # Tabel Oracle warisan diakses via Query Builder —
│                              # JANGAN bikin model baru untuk SKMST_*/SKTXN_*/SKACC_*/SKVIEW_*
├── Providers/  Services/
├── Support/                   # class stateless, dipanggil statis — §6.2
└── View/Components/           # AppLayout, GuestLayout
```

### 6.0 Kenapa tidak ada model Eloquent untuk tabel Oracle

Skema Oracle siklik **dimiliki bersama** dengan aplikasi legacy `siklik-lite` dan dipakai langsung
oleh laporan lain. Ia bukan milik Laravel: tidak ada migration, tidak ada seeder, dan DDL-nya
diubah lewat skrip SQL (`database/sql/`), bukan lewat artisan. Konsekuensinya:

- Akses selalu lewat **`DB::table('skmst_…')`** (Query Builder), bukan Eloquent.
- Nama tabel memakai prefix seragam `SKMST_` (master), `SKTXN_` (transaksi), `SKACC_` (akuntansi),
  `SKVIEW_` (view Oracle). Daftar lengkap + relasi FK: **`docs/struktur-tabel.md`**
  (versi hidup: menu Sistem → Struktur Tabel, route `/panduan-dev/struktur-tabel`).
- Oracle di lingkungan ini **10g** — tanpa fungsi JSON native, dan `UPPER(kolom) LIKE` wajib untuk
  pencarian case-insensitive. Baca skill `oracle-quirks` sebelum menulis query.

Membuat model Eloquent untuk tabel warisan akan memunculkan asumsi yang tidak dijamin
(`id` auto-increment, `created_at`/`updated_at`, soft delete) dan menutup kenyataan bahwa penulis
tabel itu bukan hanya aplikasi ini.

### 6.1 `app/Http/Traits/<Grup>/<Nama>Trait.php`

Trait = **mixin ber-state** yang di-`use` oleh kelas Volt (boleh menyentuh `$this`, `dispatch()`,
properti komponen). Grup yang hidup sekarang:

| Grup | Isi | Contoh nyata |
|---|---|---|
| `Concerns/` | lintas-komponen, bukan domain | `WithRenderVersioningTrait`, `WithValidationToastTrait` |
| `BPJS/` `SATUSEHAT/` | klien API eksternal, satu trait per resource/layanan | `PcareTrait`, `AntrianTrait`, `EncounterTrait`, `PatientTrait` |
| `Txn/<Jalur>/` | logika transaksi per jalur | `Txn/Rj/EmrRJTrait`, `Txn/Rj/EmrCompletenessRJTrait` |
| `Manajemen/<Sumber>/<Unit>/` | query laporan, cermin §4.3 | `Manajemen/Rs/Rj/KunjunganRJTrait` |
| `Master/<Modul>/` | logika master berat | `Master/MasterPasien/MasterPasienTrait` |
| `<Area>/` | area domain lain | `Dokumen/DokumenViewSupportTrait` |

Bukan daftar tertutup — grup baru boleh lahir untuk area domain baru. Yang menentukan penghuni
`Traits/` bukan nama grupnya, melainkan **satu uji**: trait menyentuh `$this` (properti komponen,
`dispatch()`, `validate()`). Kalau tidak, ia bukan mixin dan tempatnya di `Support/` (§6.2).

Nama grup ditulis PascalCase atau akronim huruf besar utuh (`BPJS`, `SATUSEHAT`). Nama berkas
PascalCase berakhiran `Trait`. **Folder yang namanya sama dengan satu-satunya berkas di dalamnya
adalah nesting mubazir** — isinya masuk `Concerns/` (backlog §8 item 3).

### 6.2 `app/Support/<SubNamespace>/<Nama>.php`

Support = **class stateless**, semua method `static`, tidak tahu-menahu soal Livewire.

**Aturan pembentukan: sub-namespace dibuat HANYA bila anggotanya ≥ 2.** Folder berisi satu berkas
menambah kedalaman tanpa memberi informasi — biarkan ia di akar `App\Support`.

| Sub-namespace | Isi | Penghuni |
|---|---|---|
| `Skema/` (2) | metadata skema Oracle untuk halaman Struktur Tabel | `KamusData`, `ModulTabel` |
| *(akar)* (2) | pembantu tunggal per domain — nama sudah menjelaskan dirinya | `OracleLob`, `LogText` |
| `Bpjs/` (1) | ⚠️ melanggar aturan ≥2 — kandidat dipindah ke akar | `BpjsHttp` |

> **Jangan mengandalkan resolusi satu-namespace antar kelas Support.** Dua kelas yang kebetulan
> sama-sama di `App\Support` bisa saling panggil tanpa `use`; begitu salah satunya pindah
> sub-namespace, panggilan itu putus — dan putusnya **senyap**, baru meledak saat jalur kode
> dijalankan. Tulis `use` eksplisit, selalu (baca skill `naming-conventions`).

**Batas Trait vs Support** (pertanyaan yang paling sering salah dijawab): butuh `$this` /
`dispatch()` / properti komponen → **Trait**. Murni input→output → **Support**. Kalau sebuah trait
tidak pernah menyentuh `$this`, ia salah tempat.

---

## 7. Routing & URL

```php
Route::livewire('/<area>/<modul>', 'pages::<area>.<…>.<modul>')->name('<area>.<modul>');
```

Tiga hal harus sejalan: **segmen URL pertama = folder area = prefix nama route.**

Standar untuk route **baru**:

| Area view | Prefix URL | Prefix nama route |
|---|---|---|
| `pages/master/master-<grup>/` | `/master/` | `master.` |
| `pages/transaksi/rj/` | `/rj/` | `rj.` |
| `pages/transaksi/<fungsi>/` | `/<fungsi>/` (`keuangan`, `gudang`, `apotek`, `penunjang`) | `<fungsi>.` |
| `pages/manajemen/rs/<unit>/` | `/manajemen/<unit>/` | `manajemen.<unit>.` |
| `pages/database-monitor/` | `/database-monitor/` | `database-monitor.` |
| `pages/panduan-dev/` | `/panduan-dev/` | `panduan-dev.` |

Nama modul **tidak mengulang jalurnya di URL**: `/rj/daftar`, bukan `/rj/daftar-rj`
(nama *berkas* tetap `daftar-rj` sesuai §3.4 — yang diringkas hanya URL).

Kondisi sekarang (74 route) belum sepenuhnya sejalan: `/master/` 43, `/keuangan/` 9,
`/database-monitor/` 6, `/transaksi/` 4, `/gudang/` 4, `/rawat-jalan/` 3, `/manajemen/` 2,
`/panduan-dev/` 1, `/dashboard` 1. Tiga penyimpangan: `/rawat-jalan/*` memakai kata panjang padahal
folder & berkasnya `rj`; `/transaksi/apotek`, `/transaksi/penunjang/laborat`,
`/transaksi/rj/antrian-*-rj` masih membawa segmen `transaksi` (nama folder view, bukan area URL);
`/manajemen/rj/…` & `/manajemen/keuangan/…` tidak mencerminkan `manajemen/rs/{rj,tu}/`.
Lihat backlog §8 item 6.

URL yang sudah live **tidak diubah tanpa alasan** — ia ada di bookmark & pintasan petugas.
Perubahan prefix wajib disertai `Route::redirect` lama→baru (302, bukan 301).

---

## 8. Backlog penyeragaman (audit 11 Sep 2026)

Semua item di bawah **hanya pindah/rename berkas atau route**, tanpa perubahan logika. Urut dari
yang paling aman. Tiap langkah: `git mv` → jalankan pemeriksa §3.1 → `Livewire::test()` render tiap
komponen tersentuh (**bukan** `view:cache` — ia tidak menangkap galat kelas Volt, lihat skill
`blade-safe-edit`) → commit terpisah per langkah.

| # | Item | Volume | Risiko | Catatan |
|---|---|---|---|---|
| ✅ 0a | `⚡` untuk semua SFC Volt | 109 berkas | 🟢 | **SELESAI** commit `448b18d`. Sekarang 220/220 patuh, tanpa pengecualian |
| ✅ 0b | Folder akronim `r-j/` `b-p-j-s/` → `rj/` | 2 folder, 15 berkas | 🟡 | **SELESAI** — `pages/components/modul-dokumen/` & `pages/components/rekam-medis/` |
| ✅ 0c | Prefix tabel `SKMST_/SKTXN_/SKACC_/SKVIEW_` | 113 tabel + 40 view | 🔴 | **SELESAI** commit `4f4a4be` — lihat `docs/struktur-tabel.md` |
| 🟡 1 | Suffix `-rj` pada folder modul-dokumen: `general-consent/` `inform-consent/` `suket/` → `…-rj/` | 3 folder | 🟢 | Nama berkasnya sudah patuh; yang tertinggal hanya nama foldernya (§3.4) |
| 🟡 2 | `suket/tab/` → `suket-rj/tabs/` + 2 partial `suket-{sehat,istirahat}-tab` → `…-rj-tab` | 1 folder, 2 berkas | 🟢 | `tab/` tunggal menyalahi §3.2; partial tanpa `-rj` menyalahi §3.4 |
| 🟡 3 | `Traits/WithRenderVersioning/` & `Traits/WithValidationToast/` → `Traits/Concerns/` | 2 berkas | 🟡 | Nesting mubazir (§6.1). Rujukannya banyak — sapu `use App\Http\Traits\…` sekaligus |
| 🟢 4 | `Traits/customErrorMessagesTrait.php` — nama berkas huruf kecil di awal + letaknya di akar | 1 berkas | 🟢 | Mestinya `Concerns/CustomErrorMessagesTrait.php` (PSR-1). Cek dulu masih dipakai atau tidak |
| 🟢 5 | `Support/Bpjs/BpjsHttp.php` → akar `App\Support` | 1 berkas | 🟢 | Aturan sub-namespace ≥2 anggota (§6.2). Boleh ditunda kalau anggota kedua segera lahir |
| 🔴 6 | Seragamkan prefix URL (§7) + `Route::redirect` lama→baru | 9 route | 🔴 | `/rawat-jalan/*` → `/rj/*`; buang segmen `transaksi` dari 4 route; `/manajemen/*` disesuaikan ke `rs/{rj,tu}`. Nama route ikut berubah — jangan sapu buta, banyak string serupa adalah nama KOMPONEN |
| 🟡 7 | `emr-rj/⚡erm-rj.blade.php` → `⚡emr-rj.blade.php` | 1 berkas | 🟢 | Foldernya sudah `emr-rj/`, berkasnya masih `erm-` (huruf tertukar). Sapu juga tag `<livewire:>` & penyebutan di komentar/docs |
| 🔴 8 | Pecah berkas melewati ambang §5 | 19 LIST + 7 `-actions` + 1 trait | 🔴 | Cek dulu proporsinya: kalau bulk-nya blok kelas Volt (seperti `⚡daftar-rj-actions`), memecah markup tidak menurunkannya ke bawah ambang — yang perlu dikurangi kelasnya |

### Cara memverifikasi

1. **Dua pemeriksa `⚡` (§3.1)** — murah, tidak butuh DB, harus selalu kosong.
2. **`Livewire::test()` mount** tiap komponen tersentuh + induk yang tag `<livewire:>`-nya diedit.
   Mount-only, tanpa memanggil aksi.
3. **Sapu halaman utuh lewat HTTP kernel** — pemeriksa yang paling dekat dengan kenyataan:
   middleware + layout + seluruh komponen anak ikut dirender, termasuk cabang yang hanya hidup
   kalau induk mengirim props (satu-satunya yang menangkap pelanggaran syarat 3 di §5).

   ```php
   $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
   auth()->loginUsingId(1);
   foreach ($routeGetTanpaParameter as $uri) {
       $res = $kernel->handle(Illuminate\Http\Request::create('/'.$uri, 'GET'));
       if ($res->getStatusCode() >= 500) { /* laporkan */ }
   }
   ```

   Potong per-batch (±20 halaman/proses) — halaman berat menghabiskan memori kalau dirender
   sekaligus. Dan **pisahkan yang bukan salahmu**: buktikan lewat `git log <base>..HEAD -- <berkas>`
   per berkas, jangan diasumsikan. Galat `ORA-00904` biasanya kolom DDL yang memang belum ada di
   environment itu, bukan akibat rename berkas.
4. **Pemeriksa nama kelas pendek di partial** — untuk tiap berkas **tanpa** blok kelas Volt, cari
   rujukan `NamaKelas::` yang tidak punya import lokal. Tiga jebakan yang membuat pemeriksa ini
   melaporkan berkas sehat sebagai rusak: lupa mengenali directive `@use('App\Foo')`; regex `use`
   yang menuntut kolom 0 padahal `use` sering berindentasi di dalam blok `@php`; dan nama kelas
   yang muncul sebagai **prosa** di komentar Blade atau halaman tutorial `/panduan-dev`.

Sesudah rename massal, hapus isi `storage/framework/views/` (`view:clear` butuh boot penuh, dan
aplikasi tidak bisa boot saat Oracle mati karena `AppServiceProvider::boot()` menarik permission
dari DB).

---

## 9. Checklist saat menambah modul baru

- [ ] Satu folder baru di `pages/<area>/`, nama kebab-case Indonesia, = nama berkas utama
- [ ] Berkas utama + `-actions` ber-`⚡`; partial tanpa `⚡`
- [ ] Suffix jalur `-rj` untuk apa pun di bawah `emr-rj/modul-dokumen/` (folder DAN berkas, §3.4)
- [ ] Akronim ditulis utuh huruf kecil (`rj`, `bpjs`, `emr`), tidak dipecah per huruf (§3.5)
- [ ] Route: segmen URL pertama = folder area = prefix nama route (§7)
- [ ] Namespace event = nama folder (`standar-master-module.md` §2)
- [ ] Cetakan/viewer yang dipakai >1 layar naik ke `pages/components/<domain>/<jalur>/`
- [ ] Logika stateless → `app/Support/` (sub-namespace hanya bila anggota ≥2); mixin komponen →
      `app/Http/Traits/<Grup>/`
- [ ] Query lewat `DB::table('sk…')`; **tidak** menambah model Eloquent untuk tabel Oracle warisan
- [ ] Tidak ada berkas > batas §5 sejak lahir
- [ ] Dua pemeriksa §3.1 kembali kosong

---

## 10. Referensi

| Apa | Di mana |
|---|---|
| Anatomi isi modul master | `docs/standar-master-module.md` |
| Nama tabel & view Oracle + relasi FK | `docs/struktur-tabel.md`, route `/panduan-dev/struktur-tabel` |
| Viewer dokumen & cetakan bertanda tangan | `docs/dokumen-view-pattern.md`, `docs/ttd-pattern-pdf-print.md` |
| Frame halaman & tabel full-height | `docs/page-frame-pattern.md` |
| Komponen UI & tombol | `docs/standar-ui-komponen.md`, `docs/standar-komponen-tombol.md` |
| Modal dirty-guard | `docs/dirty-modal-pattern.md` |
| Konvensi penamaan variable/method & `use` | skill `naming-conventions` |
| Keselamatan rename/edit massal Blade | skill `blade-safe-edit` |
| Jebakan query Oracle 10g | skill `oracle-quirks` |
| Konfigurasi lokasi komponen & `⚡` | `config/livewire.php` (`component_locations`, `component_namespaces`, `make_command.emoji`) |
