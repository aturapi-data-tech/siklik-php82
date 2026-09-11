# Standar UI & Komponen

Panduan standarisasi tampilan dan penggunaan komponen Blade di seluruh aplikasi SIRUS.

---

## 1. Struktur Modal (x-modal)

Semua modal full-screen mengikuti pola 3 bagian: **Header**, **Body**, **Footer**.

### Header

```blade
<div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
    {{-- Dot pattern background --}}
    <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
        style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
    </div>
    <div class="relative flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                {{-- Ikon modul --}}
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10">
                    ...
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $formMode === 'edit' ? 'Ubah Data ...' : 'Tambah Data ...' }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Deskripsi singkat.</p>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                </x-badge>
            </div>
        </div>

        {{-- Close X --}}
        <x-icon-button color="gray" type="button" wire:click="closeModal">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </x-icon-button>
    </div>
</div>
```

### Body

```blade
<div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
    <div class="max-w-full mx-auto">
        {{-- Content menggunakan x-border-form --}}
    </div>
</div>
```

### Footer

```blade
<div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Pastikan data sudah benar sebelum menyimpan.
        </p>
        <div class="flex gap-2">
            <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
            <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading><x-loading /> Menyimpan...</span>
            </x-primary-button>
        </div>
    </div>
</div>
```

### Footer dengan Navigasi (Transaksi)

```blade
<div class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex justify-between gap-3">
        {{-- Kiri: navigasi --}}
        <a href="{{ route('master.pasien') }}" wire:navigate>
            <x-ghost-button type="button">
                <svg>{{-- ikon user --}}</svg>
                Master Pasien
            </x-ghost-button>
        </a>
        {{-- Kanan: batal + simpan --}}
        <div class="flex gap-3">
            <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                wire:loading.attr="disabled" :disabled="$isFormLocked">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading><x-loading /> Menyimpan...</span>
            </x-primary-button>
        </div>
    </div>
</div>
```

---

## 2. Form Section (`<x-border-form>`)

Gunakan `<x-border-form>` untuk mengelompokkan field dalam card. Jangan buat card manual dengan `<div class="bg-white border...">` + `<h3>`.

```blade
{{-- Satu section --}}
<x-border-form title="Data Dokter">
    <div class="space-y-4">
        {{-- fields --}}
    </div>
</x-border-form>

{{-- Dua kolom --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-border-form title="Data Dokter">
        <div class="space-y-4">
            {{-- fields kolom kiri --}}
        </div>
    </x-border-form>

    <x-border-form title="Tarif & Administrasi">
        <div class="space-y-4">
            {{-- fields kolom kanan --}}
        </div>
    </x-border-form>
</div>
```

**Props:**

| Prop | Default | Keterangan |
|------|---------|------------|
| `title` | `''` | Judul section (tampil di header card) |
| `align` | `start` | Alignment judul: `start` / `center` / `end` |
| `bgcolor` | `bg-white` | Warna background card |
| `class` | `''` | Class tambahan (misal `max-w-xl`) |
| `padding` | `p-4` | Padding content area |

**Komponen sudah handle:** `border`, `rounded-2xl`, `shadow-sm`, `dark:bg-gray-900`, header dengan `bg-gray-50` + `border-b`.

**Jangan lakukan:**
```blade
{{-- JANGAN: card manual + h3 --}}
<div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
    <h3 class="text-sm font-semibold ...">Data Dokter</h3>
    ...
</div>

{{-- LAKUKAN: pakai x-border-form --}}
<x-border-form title="Data Dokter">
    ...
</x-border-form>
```

---

## 3. Halaman Tabel Master (List Page)

Pola standar untuk halaman daftar data master.

### Toolbar

```blade
<div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full lg:max-w-md">
            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                placeholder="Cari..." class="block w-full" />
        </div>
        <div class="flex items-center justify-end gap-2">
            <div class="w-28">
                <x-select-input wire:model.live="itemsPerPage">
                    <option value="10">10</option>
                    <option value="20">20</option>
                </x-select-input>
            </div>
            <x-primary-button type="button" wire:click="openCreate">
                + Tambah Data
            </x-primary-button>
        </div>
    </div>
</div>
```

### Tombol Aksi di Baris Tabel

```blade
<td class="px-4 py-3">
    <div class="flex flex-wrap gap-2">
        {{-- Edit --}}
        <x-secondary-button type="button"
            wire:click="openEdit('{{ $row->id }}')" class="px-2 py-1 text-xs">
            Edit
        </x-secondary-button>

        {{-- Hapus --}}
        <x-confirm-button variant="danger"
            :action="'requestDelete(\'' . $row->id . '\')'"
            title="Hapus Data"
            :message="'Yakin hapus ' . $row->name . '?'"
            confirmText="Ya, hapus" cancelText="Batal"
            class="px-2 py-1 text-xs">
            Hapus
        </x-confirm-button>
    </div>
</td>
```

> **Catatan:** pola `px-2 py-1 text-xs` di atas adalah gaya lama. Untuk halaman baru
> ikuti bagian "Tombol aksi per entri di tabel (BAKU)" di akhir dokumen ini —
> tinggi tombol aksi baris diseragamkan 40px dan aksi cetak/hapus/lihat memakai
> komponen `x-cetak-button` / `x-hapus-button` / `x-lihat-button`.

**Aturan tabel master (gaya lama):**
- Edit selalu `<x-secondary-button>` + `class="px-2 py-1 text-xs"`
- Hapus selalu `<x-confirm-button variant="danger">` + `class="px-2 py-1 text-xs"`
- Jangan pakai `x-outline-button` untuk Edit di tabel
- Jangan pakai `x-danger-button` + `wire:confirm` untuk Hapus

---

## 4. Input Harga / Tarif (`<x-text-input-number>`)

Semua field yang berisi nominal uang (harga, tarif, gaji, biaya) **wajib** menggunakan `<x-text-input-number>`.

```blade
<x-text-input-number wire:model="basicSalary"
    :error="$errors->has('basicSalary')"
    class="w-full mt-1"
    x-ref="inputBasicSalary"
    x-on:keydown.enter.prevent="$refs.nextField?.focus()" />
```

**Fitur otomatis:**
- Format ribuan saat display (999,999)
- Hapus format saat focus (user ketik angka biasa)
- Sync integer bersih ke Livewire saat blur via `$wire.set()`
- `inputmode="numeric"` untuk keyboard mobile
- Alignment kanan (`text-right`) + `tabular-nums`

**Jangan lakukan:**
```blade
{{-- JANGAN: text-input biasa untuk harga --}}
<x-text-input wire:model="price" type="number" />

{{-- JANGAN: wrapper Rp manual --}}
<div class="relative">
    <span class="absolute ...">Rp</span>
    <x-text-input wire:model="price" class="pl-10" />
</div>

{{-- LAKUKAN: --}}
<x-text-input-number wire:model="price" />
```

**Catatan:** `wire:model.live` tidak dipakai — komponen sync via `$wire.set()` saat blur. Gunakan `wire:model` (tanpa `.live`).

---

## 5. Komponen Tombol

Lihat [standar-komponen-tombol.md](standar-komponen-tombol.md) untuk panduan lengkap penggunaan tombol.

### Ringkasan Cepat

| Komponen | Warna | Kegunaan |
|----------|-------|----------|
| `x-primary-button` | Hijau solid | Simpan, Submit (1 per modal) |
| `x-secondary-button` | Abu-abu | Batal, Edit (di tabel) |
| `x-outline-button` | Tint hijau + border | Tab navigasi |
| `x-ghost-button` | Tint tipis + border | Link navigasi (Master Pasien) |
| `x-icon-button` | Transparan kotak | Close X, Cetak (ikon saja) |
| `x-info-button` | Biru solid | BPJS / SEP |
| `x-success-button` | Lime solid | Serah obat |
| `x-danger-button` | Merah solid | Hapus ringan (dalam form) |
| `x-confirm-button` | Multi-variant | Hapus penting + dialog konfirmasi |
| `x-warning-button` | Kuning solid | Aksi perlu perhatian |

---

## Aturan Umum

1. **Jangan pakai `!important` override** — pilih komponen yang tepat
2. **Jangan pakai `wire:confirm`** (browser native) — pakai `<x-confirm-button>`
3. **Jangan buat card manual** untuk form section — pakai `<x-border-form>`
4. **Jangan pakai `x-text-input` untuk harga** — pakai `<x-text-input-number>`
5. **Satu `<x-primary-button>` per modal** — hanya untuk aksi utama
6. **Close X selalu `<x-icon-button color="gray">`**
7. **Body modal selalu `px-4 py-4 bg-gray-50/70`** — jangan variasikan padding
8. **Tombol aksi per entri pakai komponen baku** — `x-cetak-button` / `x-hapus-button` / `x-lihat-button`, jangan rakit ikon sendiri

---

## Tombol aksi per entri di tabel (BAKU)

Tinggi semua tombol aksi baris = **40px**: tombol berteks
(`x-primary/secondary/outline/confirm-button`) memakai padding bawaan `px-5 py-2.5`;
tombol ikon memakai `p-2.5` + ikon `w-5 h-5`. **Jangan** menambah `px-2 py-1 text-xs`
atau `!py-1` pada tombol aksi baris tabel.

| Aksi | Komponen | Catatan |
|---|---|---|
| Lihat per entri | `<x-lihat-button wire:click="viewEntry(…)" />` | ikon mata abu-abu; `label="…"` bila perlu teks |
| Cetak per entri | `<x-cetak-button wire:click="cetak(…)" />` | ikon printer biru; `label="…"` bila perlu teks (Cetak E-Resep, Etiket) |
| Hapus per entri | `<x-hapus-button wire:click.prevent="hapus(…)" confirm="…" />` | dialog browser (`wire:confirm`) |
| Hapus, dialog modal | `<x-hapus-button :action="'hapusBaris(' . $indeks . ')'" title="…" :message="…" />` | dirender lewat `x-confirm-button` varian `danger-soft` |
| Buka Kunci / konfirmasi lain | `<x-confirm-button variant="warning-soft" action="…">` | ukuran bawaan; teks tak patah baris (`whitespace-nowrap` bawaan) |
| Menu titik-3 | `<x-secondary-button class="p-2.5">` + ikon 20px | `p-2` hanya 36px |

Ketiganya mengambil `wire:target` + spinner otomatis dari `wire:click` (timpa lewat
prop `target`), dan `title` default = `label` atau nama aksi. Sel `<td>` Aksi diberi
`whitespace-nowrap` supaya teks tombol tidak patah dua baris.

Yang sengaja di luar aturan ini: chip toggle (`x-ghost-button` `!py-0.5`), baris
edit-inline administrasi, dan X penutup header modal (`x-icon-button` bawaan).

Rincian varian tombol: `docs/standar-komponen-tombol.md`.

---

## `<x-combobox>` — combobox ketik-saring baku

Semua combobox ketik-saring memakai **satu** basis:
`resources/views/components/combobox.blade.php` (diporting dari sirus). Pemakai tidak
memanggilnya langsung, melainkan lewat pembungkus yang membawa sumber datanya:

| Pembungkus | Sumber | Yang disimpan |
|---|---|---|
| `<x-catatan-signa-combobox>` | `skmst_signa_catatans` (dikirim induk) | teks |
| `<x-ppa-combobox>` | `users.myuser_name` (cache 5 mnt) | teks |

Pembungkus baru = file tipis berisi `@props` + query daftarnya, lalu meneruskan ke
`<x-combobox>`. Jangan menyalin ulang markup/Alpine-nya.

### Aturan pokok: yang diketik ADALAH nilainya

Daftar itu bantuan ketik, **bukan pagar**. Isian di luar daftar tetap sah dan tersimpan apa
adanya — catatan signa tak baku, nama PPA yang belum punya akun. Komponen **tidak pernah**
mengubah, mengembalikan, atau menghapus ketikan petugas; nilainya diikat `wire:model`
langsung di input.

`wire-model-id` menambah satu hal saja: selama teks di kotak **cocok persis** dengan salah
satu baris daftar, id-nya ikut disimpan; begitu teksnya menyimpang, id dikosongkan. Jadi id
itu **bonus** (tautan ke master kalau kebetulan cocok), bukan syarat — dan tak pernah ada id
basi yang menunjuk baris lain dari yang tertulis. (`wire-model-jenis` WAJIB diisi bila satu
daftar mencampur dua master.)

> **Konsekuensi untuk induk:** yang diwajibkan di `rules()` harus **teksnya**, bukan id-nya.
> Mewajibkan id sama saja diam-diam melarang isian di luar master. Kalau butuh id yang
> **dijamin** ada (mis. memesan stok/kamar), itu pekerjaan komponen **LOV**
> (`<livewire:lov.…>`), bukan combobox ini.

### Enter & Tab

Enter **tidak** memilih baris kecuali ada yang tersorot lewat panah/hover — ia menjalankan
`enter-action`, karena yang diketik sudah jadi nilainya. Tab mengambil baris yang tersorot
(kalau ada) lalu fokus tetap lanjut ke isian berikutnya.

**JANGAN menyalakan sorot-otomatis pada baris teratas** — ia merebut Enter dari
`enter-action` di form yang memakai Enter untuk menambah baris (e-resep).

```blade
{{-- baris entri e-resep: Enter = tambah baris --}}
<x-catatan-signa-combobox wire-model="formEresep.catatanKhusus" :options="$signaCatatans"
    enter-action="$wire.addItemResep()" :error="$errors->has('formEresep.catatanKhusus')" />
```

### Lain-lain

- Dropdown wajib tetap `wire:ignore` + `<template x-if="open">` — lihat komentar di komponennya
  (mencegah `<li>` x-for tercabut morph Livewire dan membatalkan sisa batch `initTree`).
- Daftar dibekukan ke `x-data` saat render → pembungkus bertanggung jawab men-**cache** query
  masternya supaya tak di-query ulang tiap render Livewire.
- `pick()` mengembalikan fokus ke kotak; jangan menambah pemanggilan `pick()` dari `blur`.

---

## `<x-stepper>` & `<x-step-number>`

Penanda langkah untuk alur berurutan (mis. rujukan, pendaftaran bertahap).

```blade
{{-- deret langkah mendatar --}}
<x-stepper :steps="$this->langkahRujukan()" />

{{-- lingkaran angka di judul kelompok isian --}}
<x-step-number :n="1" /> Identitas Pasien
```

Bentuk tiap langkah:

```php
['n' => 1, 'title' => 'Diagnosa & Kriteria', 'hint' => 'opsional',
 'state' => 'done' | 'current' | 'todo' | 'error']
```

- `done` → lingkaran hijau tint + centang, `current` → hijau solid,
  `todo` → abu, `error` → merah tint + tanda seru.
- `error` dipakai untuk langkah yang **gagal/ditolak** — bukan sekadar belum
  dikerjakan — supaya petugas tahu harus mundur, bukan lanjut.
- `x-step-number` memakai kelas/warna yang sama dengan lingkaran `x-stepper`
  agar nomor di judul bagian terbaca satu keluarga.

---

## `<x-deskripsi-ringkas>`

Deskripsi panjang yang dipotong sebaris + tombol "Selengkapnya" untuk membuka penuh.
Dipakai di kartu modul dokumen dan baris judul modal — judul + badge + deskripsi
dijejer satu baris supaya kartunya ringkas, tapi keterangannya tetap bisa dibaca utuh.

```blade
<x-deskripsi-ringkas>{{ $modul['deskripsi'] }}</x-deskripsi-ringkas>
```

Tombolnya `x-on:click.stop` supaya tidak ikut memicu aksi baris/kartu di belakangnya.
Pakai bila deskripsi berpotensi lebih dari ~90 karakter.

---

## `.ds-table` — tabel ber-tema

Kelas CSS (bukan komponen) di `resources/css/app.css`. Sudah mengatur font, padding,
header uppercase, garis antar record, dan hover — **jangan tulis ulang kelas header
tabel manual**. Semua warnanya lewat var token yang ikut ber-swap di mode gelap
tanpa perlu menulis `dark:` di tiap elemen.

```blade
<table class="ds-table">
    <thead class="sticky top-0 z-10">
        <tr><th>Nama</th><th class="ds-c">Kode</th><th class="ds-c">Aksi</th></tr>
    </thead>
    <tbody>
        <tr>
            <td class="ds-td-strong">{{ $row->nama }}</td>
            <td class="ds-c ds-td-token">{{ $row->kode }}</td>
            <td class="ds-c whitespace-nowrap"><x-lihat-button wire:click="lihat({{ $row->id }})" /></td>
        </tr>
    </tbody>
</table>
```

| Kelas | Kegunaan |
|---|---|
| `.ds-table` | tabel dasar (header + padding 24px + garis antar record + hover) |
| `.ds-td-strong` | sel penekanan (warna ink, font-medium) |
| `.ds-td-token` | kode/nomor — mono, nowrap |
| `.ds-td-meta` | keterangan kecil — mono, muted-soft |
| `.ds-td-class` | nama kelas/kode teknis — mono, warna primary |
| `.ds-c` | rata tengah (hanya berlaku di dalam `.ds-table`) |
| `.ds-table-foot` | baris kaki tabel (ringkasan/pagination) |
| `.ds-table-entri` | varian padat untuk baris input yang menyatu dengan tabel (spinner `input[type=number]` disembunyikan) |
| `.ds-table-rapat` | varian rapat untuk tabel di kolom sempit; melepas `nowrap` milik `.ds-td-*` |
| `.ds-toggle-tumpuk` | `x-toggle` ditumpuk (sakelar di atas, label di bawah) untuk kolom sempit |
| `.ds-form-title` | judul bagian pada kartu form (`x-border-form`) — ink + uppercase, bukan `ds-caption-up` yang muted |

**Gotcha:** `ds-c` / `ds-td-*` hanya berefek di dalam `<table class="ds-table">`;
dipakai di luar itu sel akan tampak berdempet tanpa padding.
