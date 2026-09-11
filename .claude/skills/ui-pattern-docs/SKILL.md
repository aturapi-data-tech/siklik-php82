---
name: ui-pattern-docs
description: Indeks pola UI/komponen terdokumentasi di folder docs/. Baca sebelum membuat komponen baru (tombol, modal, halaman, cetak PDF, editor, list) agar konsisten dengan pola repo dan tidak reinvent. Mengarahkan ke file docs/ yang relevan.
---

# Indeks Pola UI/Komponen (docs/)

Sebelum membuat komponen baru, cek apakah polanya sudah ada di `docs/`. Ikuti pola yang ada agar konsisten. Baca file docs terkait sebelum implementasi.

| Kebutuhan | Baca file |
|---|---|
| Standar tombol (varian, ukuran, warna, ikon, tombol sampah, toolbar berwarna) | `docs/standar-komponen-tombol.md` |
| Standar UI komponen umum | `docs/standar-ui-komponen.md` |
| Tombol aksi per entri di tabel (cetak/hapus/lihat, 40px) — `x-cetak-button`, `x-hapus-button`, `x-lihat-button` | `docs/standar-komponen-tombol.md` §"Tombol aksi per entri" + `docs/standar-ui-komponen.md` §"Tombol aksi per entri di tabel (BAKU)" |
| Penanda langkah alur berurutan — `x-stepper`, `x-step-number` | `docs/standar-ui-komponen.md` §"`<x-stepper>` & `<x-step-number>`" |
| Deskripsi panjang dipotong + "Selengkapnya" — `x-deskripsi-ringkas` | `docs/standar-ui-komponen.md` §"`<x-deskripsi-ringkas>`" |
| Tabel ber-tema `.ds-table` (+ `.ds-td-*`, `.ds-c`, `.ds-table-entri/-rapat`, `.ds-toggle-tumpuk`, `.ds-form-title`) | `docs/standar-ui-komponen.md` §"`.ds-table` — tabel ber-tema" |
| Halaman bertabel full-height (frame, toolbar sticky, pagination, empty state) | `docs/page-frame-pattern.md` |
| Modal dengan deteksi perubahan (konfirmasi keluar bila dirty) | `docs/dirty-modal-pattern.md` |
| Cetak PDF + tanda tangan (TTD) | `docs/ttd-pattern-pdf-print.md` |
| Struktur folder & penamaan berkas (⚡ SFC vs partial, suffix -rj, Trait vs Support, batas ukuran) | `docs/standar-struktur-folder.md` |
| Modul master CRUD (kontrak LIST/FORM, event, LOV, delete dua lapis ORA-02292) | `docs/standar-master-module.md` |
| Whitelist IP BPJS & proxy VPS (BpjsHttp, config/bpjs.php) | `docs/bpjs-whitelist-ip-proxy.md` |
| Struktur tabel Oracle (prefix SK, modul, relasi FK) | `docs/struktur-tabel.md` + menu Sistem → Struktur Tabel |
| Modul dokumen bertanda tangan RJ (consent, surat keterangan): siklus draft→TTD→kunci, tabel daftar entri, gate `dokumen.*` | `docs/modul-dokumen-rj-pattern.md` + skill `modul-dokumen` |
| Stempel TTD petugas di layar — `x-signature.ttd-petugas`, `x-signature.ttd-gambar`, `App\Support\TtdUser` | `docs/ttd-pattern-pdf-print.md` §6 |
| Editor rich text | `docs/tinymce-editor-pattern.md` |
| List/lookup stabil (decouple dari filter) | `docs/stable-lookup-list-pattern.md` |
| Trait untuk integrasi API eksternal (BPJS/PCare dll.) | `docs/trait-template-api-eksternal.md` |
| Integrasi PCare BPJS | `docs/PCARE_INTEGRATION.md` |
| Diagnosa ICD-10 (master, LOV) | skill `diagnosa-flow` (+ dok lengkap versi sirus: `sirus-php82/docs/diagnosa-architecture.md`) |

> Catatan: docs pola diport dari sirus-php82 — contoh path file di dalamnya (mis. `transaksi/ri/eresep-ri/...`) merujuk repo sirus; pakai sebagai acuan pola, bukan path literal. `idrg-bridging.md` sengaja TIDAK diport (iDRG/INACBG = klaim RS, klinik pratama pakai PCare).

## Catatan kunci per pola
- **Page frame / tabel full-height**: yang bikin tabel isi penuh layar = card-level `flex flex-col flex-1 min-h-0` (bukan empty row-nya). Empty state cukup `@forelse`/`@empty` + `<td colspan py-16 text-center>`. JANGAN bikin panel `flex-1` / `@if($this->rows->isEmpty())` sendiri. **Gotcha:** wrapper perantara `wire:poll` (`<div ... class="mt-4">`) di atas card WAJIB ikut `flex flex-col flex-1 min-h-0`, kalau tidak card menciut & tabel kosong tampak pendek. **Header tabel list baku:** `text-sm font-semibold tracking-wide text-left text-gray-600 uppercase` (jangan `text-base`/`text-xs`; `font-semibold`, bukan medium/bold).
- **TTD print**: pola `h-16` + `text-center` + `&nbsp;` fallback. HINDARI `display:flex` / `mx-auto` / `<br>` / bracket yang belum di-rebuild. Path gambar TTD WAJIB `App\Support\TtdUser::pathBerkasDariKode($kode)` — siklik TIDAK punya directive `@ttdSrc()`, dan kolom `myuser_ttd_image` punya dua format.
- **Tombol aksi baris tabel**: cetak/hapus/lihat WAJIB `x-cetak-button` / `x-hapus-button` / `x-lihat-button` (tinggi 40px = `p-2.5` + ikon `w-5 h-5`). JANGAN tambah `px-2 py-1 text-xs` / `!py-1`. `x-hapus-button` punya 2 mode: `confirm="…"` (dialog browser) dan `:action="…"` (dialog modal lewat `x-confirm-button` varian `danger-soft`).
- **`.ds-table`**: kelas CSS di `resources/css/app.css`, bukan komponen — token warnanya ikut ber-swap di mode gelap tanpa `dark:`. `ds-c`/`ds-td-*` hanya berefek DI DALAM `<table class="ds-table">`.
- **Stable lookup list**: list HANYA depend tanggal; decouple dari filterStatus/filter lain.
- **Trait API eksternal**: ikuti pola trait sirus — event split per concern, suffix per-modul. Acuan lokal: `PcareTrait`.

Lihat juga skill terkait: `blade-safe-edit`, `livewire-input-patterns`.
