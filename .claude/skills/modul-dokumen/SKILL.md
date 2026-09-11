---
name: modul-dokumen
description: Pola modul dokumen bertanda tangan di EMR Rawat Jalan siklik (Surat Keterangan, General Consent, Inform Consent) — kartu+tombol→modal, siklus Draft→TTD pasien→TTD petugas(=kunci)→Lihat/Cetak, daftar entri multi, gate dokumen.hapus/dokumen.bukaKunci, stempel TTD petugas & path TTD di cetak. WAJIB dibaca sebelum membuat/menyunting formulir dokumen bertanda tangan atau menambah viewer rekam medisnya.
---

# Modul Dokumen RJ (formulir bertanda tangan)

Klinik pratama → **hanya jalur Rawat Jalan**. Tidak ada RI/UGD di repo ini; kalau menemukan
token `-ri`/`-ugd` di berkas hasil salinan dari sirus, itu sisa porting yang harus dibersihkan.

Acuan lengkap: **`docs/modul-dokumen-rj-pattern.md`** (bentuk baku dua layar, tabel daftar,
siklus entri, jebakan). Pendukung: `docs/dokumen-view-pattern.md` (Lihat = render blade cetak
ke iframe), `docs/ttd-pattern-pdf-print.md` (TTD di PDF + `App\Support\TtdUser`),
`docs/standar-komponen-tombol.md`, `docs/standar-ui-komponen.md` (`.ds-table`).

## Di mana modulnya

| Modul | Berkas actions | Tipe | Key JSON |
|---|---|---|---|
| Surat Keterangan (sehat/istirahat) | `…/modul-dokumen/suket/⚡rm-suket-rj-actions.blade.php` | sekali-entri | `suket` |
| General Consent | `…/modul-dokumen/general-consent/⚡rm-general-consent-rj-actions.blade.php` | sekali-entri | `generalConsentPasienRJ` |
| Inform Consent | `…/modul-dokumen/inform-consent/⚡rm-inform-consent-rj-actions.blade.php` | multi-entri | `informConsentPasienRJ` (list) |

Semua di bawah `resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/`, dipasang sebagai
tab di hub `⚡modul-dokumen-rj.blade.php`. Blade cetak PDF di
`resources/views/pages/components/modul-dokumen/rj/<dok>/`. Viewer rekam medis di
`resources/views/pages/components/rekam-medis/rj/dokumen-view/`.

Template General/Inform Consent dipecah ke `<dok>/partials/*.blade.php` (kartu-ringkas, modal-header,
form-*, tabel-entri, modal-footer) yang di-`@include` dari komponen ⚡. Blok PHP tetap di ⚡; partial
memakai properti komponen & `$this`. Variabel `@php` lokal TIDAK bocor antar-partial.

## Penyimpanan: satu CLOB, banyak node

Semua isi dokumen menumpang di **satu kolom CLOB** `sktxn_rjhdrs.datadaftarpolirj_json`,
satu baris per kunjungan RJ. Aksesnya lewat `App\Http\Traits\Txn\Rj\EmrRJTrait`:

| Method | Untuk |
|---|---|
| `findDataRJ($rjNo)` | baca seluruh JSON kunjungan (array) |
| `lockRJRow($rjNo)` | `SELECT … FOR UPDATE` — WAJIB sebelum menulis |
| `updateJsonRJ($rjNo, $data)` | tulis balik seluruh JSON |
| `checkEmrRJStatus($rjNo)` | EMR sudah terkunci? → `$isFormLocked` |
| `appendAdminLogRJ($rjNo, $pesan, 'MR')` | jejak audit Rekam Medis |

**Tulis data SELALU** `DB::transaction` → `lockRJRow` → `findDataRJ` (baca ulang setelah lock)
→ ubah **hanya key modulnya** → `updateJsonRJ` → `appendAdminLogRJ(..., 'MR')`.
Muat entri lama dengan `array_replace_recursive(defaultForm(), $tersimpan)` supaya record legacy aman.

> **JANGAN mengubah bentuk node JSON yang sudah tersimpan.** Menambah flag baru
> (mis. `finalized`) dilarang tanpa alasan kuat — status final diturunkan dari data
> yang sudah ada (lihat aturan #1).

## Aturan keras

1. **TTD petugas = aksi TERAKHIR yang sekaligus MENGUNCI.** Tidak ada tombol
   "Simpan & Kunci" terpisah; footer cukup **Simpan Draft**. Status final **diturunkan**,
   bukan disimpan sebagai flag baru: General Consent final = `petugasPemeriksa` terisi
   (`entriFinal()`), Inform Consent per entri final = `dokter` terisi (`entriFinal($entri)`).
2. **Stempel yang ditulis SEBELUM `validate()` wajib dicabut saat `ValidationException`.**
   Simpan nilai lama → stempel → `try { $this->validate(); } catch (ValidationException $e)
   { kembalikan nilai lama; throw $e; }`. Kalau tidak: stempel tersangkut di layar, tombol
   TTD hilang (komponen mengira sudah TTD), padahal tak ada yang tersimpan.
2b. **Modul multi-entri WAJIB dua layar** (`docs/modul-dokumen-rj-pattern.md` §5b): `$layar`
   `'daftar'` ⇄ `'form'`, `$editingKey` = `signatureDate`, `diForm()`, `tambahEntri()`,
   `kembaliKeDaftar()`, `editEntri($key)`, `saveDraft()` upsert by key, `bukaKunci($key)`;
   `reset*()` menyetel `layar = 'daftar'`; guard `@if ($this->diForm())` tepat sebelum
   `<section>` formulir (bukan di header modal), `@unless` membungkus tabel.
3. **Role Hapus & Buka Kunci = SATU SUMBER** `App\Support\AksiRole`
   (`DOKUMEN_HAPUS`, `DOKUMEN_BUKA_KUNCI`), didaftarkan sebagai Gate di
   `AppServiceProvider::boot()`. **JANGAN** tulis `@hasanyrole('Admin|Mr')` literal —
   pakai `@can('dokumen.hapus')` / `@can('dokumen.bukaKunci')` di blade **DAN**
   `auth()->user()?->can('dokumen.hapus')` sebagai **statement pertama** method servernya
   (`wire:click` memanggil method publik; guard blade saja bisa ditembus).
4. **Buka kunci hanya mencabut TTD PETUGAS.** TTD pasien/wali & saksi DIPERTAHANKAN
   (tak boleh dihapus sepihak oleh staf). Wajib `appendAdminLogRJ(..., 'MR')` yang
   menyebut pelakunya.
5. **Tombol aksi per entri = komponen baku, tinggi 40px, tanpa `px-2 py-1 text-xs`:**
   `<x-cetak-button>` (ikon biru), `<x-lihat-button>` (ikon mata),
   `<x-hapus-button action="hapus('…')" …>` (ikon tong sampah merah, dialog modal),
   Buka Kunci = `<x-confirm-button variant="warning-soft">` (kuning — aksi koreksi, bukan merah).
   Sel Aksi **satu baris rata kanan** (`flex items-center justify-end gap-2`, sel
   `whitespace-nowrap`); kelompok berisiko dibungkus
   `<div class="flex items-center gap-2 pl-3 ml-1 border-l border-hairline dark:border-gray-700">`
   yang **hanya dirender bila user punya salah satu hak** (supaya tak menyisakan garis kosong).
6. **Tabel daftar entri (modul multi-entri)** — `<table class="ds-table ds-table-entri">`,
   kolom `[▸] Tanggal · ringkasan khas modul · Petugas (TTD) · Status · Aksi`, **tanpa kolom No**.
   Kolom pertama panah rincian: `<tbody wire:key x-data="{ open: false }">`,
   `<tr @click="open = !open">` + `<svg :class="{ 'rotate-90': open }">`, lalu
   `<tr x-show="open" x-cloak><td colspan="N"><dl class="… md:grid-cols-2">`. Semua mulai
   **tertutup**. Sel Aksi diberi `@click.stop`.
7. **Entri terbaru di atas.** `collect($daftar)->sortByDesc(fn($entri) =>
   strtotime(strtr($entri['signatureDate'] ?? '', '/', '-')))->values()->all()` —
   **jangan** `array_reverse` (itu urutan simpan), **jangan** `Carbon::parse` (menebak m/d/Y),
   **jangan** `Carbon::createFromFormat` (exception untuk satu entri menyimpang → modal 500).
8. **Kolom "Petugas (TTD)" hanya menampilkan nama bila entri final**; draft →
   `<x-badge variant="danger">Belum TTD</x-badge>`, walau data lama membawa stempel tertinggal.
9. **Stempel petugas di layar = `x-signature.ttd-petugas`** (`:framed="false"`,
   `:locked="true"` pada keadaan sudah TTD/terkunci, `:allowClear="false"`). Kartu stempel
   bespoke (div nama/Kode/tanggal rata tengah) **DILARANG**. Gambar TTD user lewat
   `x-signature.ttd-gambar :code="…"` → `App\Support\TtdUser::urlDariKode()`.
10. **Saksi WAJIB saat kunci di dokumen yang memang punya saksi** (Inform Consent:
   `saksi` + `signatureSaksi` `required`). Modul tanpa saksi tidak usah dipaksa punya saksi.
11. **Cetak PDF/viewer: path gambar TTD WAJIB `TtdUser::pathBerkasDariKode($kode)`** —
   jangan `public_path('storage/' . $nilai)` sendiri (kolom `myuser_ttd_image` punya dua
   format; format nama-berkas-saja gagal `file_exists`). Siklik **tidak punya** directive
   `@ttdSrc()`. `DokumenViewSupportTrait::dvTtdPath()` sudah mendelegasikan ke sana.
   Tabel `USERS` siklik **tidak punya `emp_id`** — pencarian selalu `myuser_code`.
12. **Penanda "ada data" di hub** — tiap tab di `⚡modul-dokumen-rj.blade.php` wajib
   menampilkan badge saat modulnya berisi: **multi** → jumlah entri
   (`{{ $this->jumlahInformConsent() }}`), **sekali-entri** → `&#10003;`
   (`$this->generalConsentTerisi()`, `$this->suketTerisi()`). Gaya seragam
   `<x-badge variant="success" class="text-[10px] px-1.5 py-0">`. Hitungannya di **method
   komponen**, bukan logika di `@php` template.
13. **Deskripsi panjang pakai `<x-deskripsi-ringkas>`** (> ~90 karakter), di dalam baris judul
   `flex items-baseline flex-1 gap-2 min-w-0` — tanpa `min-w-0` di induknya `truncate` tak
   menggigit dan kartunya melar.

## Verifikasi (WAJIB sebelum lapor selesai)

```bash
php artisan view:cache && php artisan view:clear          # EXIT 0 = pipeline Blade asli lolos
# keseimbangan tag per berkas yang diedit (php -l TIDAK cukup untuk Blade)
grep -c '@if' <berkas>; grep -c '@endif' <berkas>
grep -c '@can' <berkas>; grep -c '@endcan' <berkas>        # @can di komentar ikut terhitung — cek manual
grep -c '<x-modal' <berkas>; grep -c '</x-modal' <berkas>
```

Lalu render nyata lewat tinker (JANGAN memanggil `save()` — dilarang menulis DB saat uji):

```php
$rjNo = DB::table('sktxn_rjhdrs')->orderByDesc('rj_no')->value('rj_no');
Livewire\Livewire::test('pages::transaksi.rj.emr-rj.modul-dokumen.modul-dokumen-rj')
    ->call('openModulDokumen', (int) $rjNo)->html();          // hub + 3 anak ter-mount
Livewire\Livewire::test('pages::transaksi.rj.emr-rj.modul-dokumen.inform-consent.rm-inform-consent-rj-actions',
    ['rjNo' => (int) $rjNo])->set('consentList', $entriPalsu)->html();   // uji tabel tanpa menyentuh DB
```

Periksa lewat HASIL RENDER, bukan urutan baris: `str_contains($html, 'display-pasien')`,
badge `Belum TTD` untuk entri draft, dan tombol berisiko **tidak** muncul untuk user tanpa hak.

Ikuti skill `blade-safe-edit` saat menyunting `*.blade.php` (token presisi, jangan regex multiline).
