# Pola Modul-Dokumen RJ (formulir bertanda tangan)

> Bagian dari [Standar UI & Komponen](standar-ui-komponen.md). Lihat juga
> [Standar Komponen Tombol](standar-komponen-tombol.md),
> [Pola Dokumen Viewer](dokumen-view-pattern.md), dan
> [TTD Pattern di PDF Print](ttd-pattern-pdf-print.md).
>
> Ringkasan aturan untuk agen: `.claude/skills/modul-dokumen/SKILL.md`.

Siklik = klinik pratama, **hanya jalur Rawat Jalan**. Semua yang di bawah ini berlaku untuk
`resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/`.

Dokumen ini punya dua lapis:

- **§1–§6 = TARGET** untuk modul dokumen **baru**. Ikuti apa adanya.
- **§7 = keadaan modul lama** yang belum memenuhi target, plus backlog.

---

## 1. Struktur file (3 titik sentuh)

| # | Berkas | Isi |
|---|---|---|
| 1 | `…/modul-dokumen/<dok>/⚡rm-<dok>-rj-actions.blade.php` | komponen Volt: kartu ringkas + modal (formulir, daftar entri, siklus, cetak) |
| 1b | `…/modul-dokumen/<dok>/partials/{kartu-ringkas,modal-header,form-*,tabel-entri,modal-footer}.blade.php` | potongan template yang di-`@include` dari #1 (12 Sep 2026). Blok PHP & `<x-modal>` tetap di #1; partial memakai properti komponen + `$this` apa adanya. **Variabel `@php` lokal tidak bocor antar-partial** — hitung ulang di partial yang memakainya |
| 2 | `…/pages/components/modul-dokumen/rj/<dok>/⚡cetak-<dok>-rj.blade.php` + `…-print.blade.php` | pemicu cetak (DomPDF) + blade cetaknya |
| 3 | `…/modul-dokumen/⚡modul-dokumen-rj.blade.php` | daftarkan **tab + panel** `<livewire:… :rjNo :disabled wire:key>` + **badge "ada data"** (§6) |

Bila dokumennya perlu tampil di display Rekam Medis: tambah viewer di
`…/pages/components/rekam-medis/rj/dokumen-view/⚡<dok>-view-rj.blade.php`
(pola `docs/dokumen-view-pattern.md`) dan daftarkan di `⚡cetak-rekam-medis-open.blade.php`.

---

## 2. Penyimpanan: satu CLOB, node per modul

Tidak ada tabel sendiri per dokumen. Semuanya menumpang di
`sktxn_rjhdrs.datadaftarpolirj_json` (CLOB, satu baris per kunjungan), lewat
`App\Http\Traits\Txn\Rj\EmrRJTrait`: `findDataRJ` / `lockRJRow` / `updateJsonRJ` /
`checkEmrRJStatus` / `appendAdminLogRJ`.

```php
DB::transaction(function () {
    $this->lockRJRow($this->rjNo);                 // 1. kunci baris (SELECT … FOR UPDATE)
    $data = $this->findDataRJ($this->rjNo);        // 2. baca ULANG setelah lock
    if (empty($data)) { throw new \RuntimeException('Data RJ tidak ditemukan.'); }

    $data['<keyModul>'] = /* … */;                 // 3. ubah HANYA key modul ini
    $this->updateJsonRJ($this->rjNo, $data);       // 4. tulis balik
    $this->dataDaftarPoliRJ = $data;               // 5. sinkronkan properti lokal
    $this->appendAdminLogRJ((int) $this->rjNo, 'Buat <Dokumen> — …', 'MR');   // 6. audit MR
});
```

Node yang dipakai sekarang: `suket`, `generalConsentPasienRJ`, `informConsentPasienRJ` (list).

> **Bentuk node yang sudah tersimpan tidak boleh berubah.** Jangan menambah flag baru
> (mis. `finalized`) hanya untuk kenyamanan kode — status final diturunkan dari data yang
> memang sudah ada (§3).

---

## 3. Siklus hidup entri

```
Simpan Draft (tanpa validasi penuh)  →  pasien/wali TTD (+ saksi bila modulnya ber-saksi)
   →  PETUGAS TTD  =  validasi penuh + stempel + KUNCI entri
   →  entri read-only: hanya Lihat / Cetak
   →  [gate dokumen.bukaKunci]  Buka Kunci  →  kembali draft, TTD petugas dicabut
```

- **TTD petugas adalah aksi terakhir dan sekaligus pengunci.** Tidak boleh ada tombol
  "Simpan & Kunci" terpisah (dua jalan mengunci = perilaku bercabang). Footer cukup
  **Simpan Draft**.
- **Status final diturunkan, bukan disimpan:**
  General Consent → `entriFinal()` = `petugasPemeriksa` terisi;
  Inform Consent → `entriFinal($entri)` = `dokter` (pemberi informasi) terisi.
- **Entri final tak boleh ditimpa** — `save()` menolak dengan toast bila `entriFinal()`.
- **Stempel yang ditulis sebelum `validate()` WAJIB dicabut saat `ValidationException`:**

```php
$stempelLama = ['petugasPemeriksa' => $form['petugasPemeriksa'] ?? '', /* …Code, …Date */];
$form['petugasPemeriksa'] = auth()->user()->myuser_name ?? '';   // stempel
// …
try {
    $this->validate();
} catch (ValidationException $e) {
    $form = array_replace($form, $stempelLama);   // dicabut lagi
    throw $e;                                     // error tetap tampil merah di kolomnya
}
```

  Tanpa itu: stempel tersisa di layar, tombol TTD hilang (komponen mengira sudah TTD),
  lalu draft tampak bertanda tangan tapi berstatus Draft.
- **Buka kunci hanya mencabut TTD petugas.** TTD pasien/wali & saksi DIPERTAHANKAN.
  Wajib `appendAdminLogRJ(..., 'MR')` menyebut pelakunya.
- **Gate dua lapis.** Role terpusat di `App\Support\AksiRole` (`DOKUMEN_HAPUS`,
  `DOKUMEN_BUKA_KUNCI`) → Gate `dokumen.hapus` / `dokumen.bukaKunci` di
  `AppServiceProvider::boot()`. Blade `@can(...)`, server
  `if (!auth()->user()?->can('dokumen.hapus')) { toast; return; }` sebagai **statement pertama**.
  Menambah role = ubah satu berkas itu saja.

---

## 4. Tanda tangan

| Pihak | Cara | Wajib? |
|---|---|---|
| Pasien / wali | `x-signature.signature-pad` → `x-signature.signature-result` bila sudah ada | wajib |
| Saksi | idem | **wajib (`required`) saat kunci** — di dokumen yang memang punya saksi (Inform Consent). Modul tanpa saksi tidak dipaksa punya saksi |
| Petugas | `x-signature.ttd-petugas` (`:framed="false"`, `:allowClear="false"`, `:locked="true"` saat terkunci/final) | wajib; menstempel nama + `myuser_code` + jam user login |

```blade
<x-signature.ttd-petugas :framed="false" :locked="$isFormLocked || $this->entriFinal()"
    :allowClear="false" :ttd="$consent['petugasPemeriksa'] ?? ''"
    :code="$consent['petugasPemeriksaCode'] ?? ''" :date="$consent['petugasPemeriksaDate'] ?? ''"
    sign="setPetugasPemeriksa" nameLabel="Petugas Pemberi Penjelasan"
    signLabel="TTD sebagai Petugas & Kunci" />
```

- **Kartu stempel bespoke DILARANG** (div nama/Kode/tanggal rata tengah). Komponen di atas
  menampilkan gambar TTD user (`x-signature.ttd-gambar` → `App\Support\TtdUser::urlDariKode`)
  di kotak putih selebar kolom, sehingga kolom pasien/saksi/petugas sejajar tingginya.
- TTD masuk `rules()` (`'signature' => 'required|string'`) supaya error tampil **merah di
  kolomnya** + toast — bukan cek manual yang cuma memunculkan toast. Nama/waktu petugas
  TIDAK divalidasi (di-stempel oleh aksinya sendiri).
- **Cetak/viewer: path gambar TTD WAJIB `App\Support\TtdUser::pathBerkasDariKode($kode)`**
  (`DokumenViewSupportTrait::dvTtdPath()` sudah mendelegasikan). Jangan menyusun
  `public_path('storage/' . $nilai)` sendiri; siklik **tidak punya** directive `@ttdSrc()`.
  Layout TTD di PDF wajib `<table>` — lihat `docs/ttd-pattern-pdf-print.md`.

---

## 5. Tabel daftar entri (modul multi-entri)

```
[▸] Tanggal | <ringkasan khas modul, 1–2 kolom> | Petugas (TTD) | Status | Aksi
    ↳ baris rincian <dl> 2 kolom (terbuka saat baris diklik; sel Aksi @click.stop)
```

- Pembungkus `<div class="overflow-x-auto rounded-2xl">`, tabel
  `<table class="ds-table ds-table-entri min-w-full">`, `<thead class="sticky top-0 z-10">`,
  judul kolom `whitespace-nowrap`.
- **Tanpa kolom No** — nomor urut tak bermakna karena daftar diurut ulang.
- **Kolom pertama = panah rincian.** Satu `<tbody wire:key x-data="{ open: false }">` per
  entri; `<tr class="cursor-pointer" @click="open = !open">` dengan
  `<svg :class="{ 'rotate-90': open }">`; lalu `<tr x-show="open" x-cloak><td colspan="N">`
  berisi `<dl class="grid … md:grid-cols-2">`. Semua baris **mulai tertutup**. Isi `<dl>` =
  ringkasan isian yang tidak muat di kolom, bukan seluruh formulir.
- Tanggal `ds-td-token` (mono); ringkasan utama `ds-td-strong`; teks lain `text-muted`.
- **Petugas (TTD)** = nama petugas **hanya bila entri final**, selain itu
  `<x-badge variant="danger">Belum TTD</x-badge>`.
- **Status** = `<x-badge variant="info">Terkunci</x-badge>` / `<x-badge variant="warning">Draft</x-badge>`.
- **Aksi — SATU baris rata kanan**, sel `whitespace-nowrap`:

```blade
<td class="whitespace-nowrap" @click.stop>
    <div class="flex items-center justify-end gap-2">
        <x-lihat-button wire:click="lihat('{{ $entriTgl }}')" />
        <x-cetak-button wire:click="cetak('{{ $entriTgl }}')" />

        {{-- kelompok berisiko: dipisah garis, hanya dirender bila user berhak --}}
        @if (!$isFormLocked && $bolehHapus)
            <div class="flex items-center gap-2 pl-3 ml-1 border-l border-hairline dark:border-gray-700">
                @can('dokumen.hapus')
                    <x-hapus-button :action="'hapus(\'' . $entriTgl . '\')'"
                        title="Hapus …" message="Entri ini akan dihapus beserta tanda tangannya. Lanjutkan?" />
                @endcan
            </div>
        @endif
    </div>
</td>
```

  Urutan tetap: aksi non-destruktif dulu, lalu garis, lalu Buka Kunci
  (`<x-confirm-button variant="warning-soft">`, kuning), **Hapus paling kanan**.
  Semua tombol 40px — **jangan** `class="px-2 py-1 text-xs"`, jangan `x-secondary-button`
  berteks "Cetak", jangan `x-confirm-button variant="danger"` berteks "Hapus".
- **Urutan: TERBARU DI ATAS.** Dihitung di method komponen, bukan di template:

```php
public function daftarEntri(): array
{
    return collect($this->consentList)
        ->sortByDesc(fn($entri) => strtotime(strtr(($entri['signatureDate'] ?? '') ?: '', '/', '-')))
        ->values()->all();
}
```

| Aturan | Alasan |
|---|---|
| Kunci urut = tanggal yang **TAMPIL** di kolom | yang dibaca petugas itu kolomnya, bukan waktu simpan |
| `strtr('/', '-')` sebelum `strtotime` | memaksa pembacaan d-m-Y; nilai tak terbaca → `false` → entri turun ke bawah, bukan error |
| **Jangan** `array_reverse` | itu urutan simpan; entri yang diedit tetap duduk di posisi lamanya → tabel tampak acak |
| **Jangan** `Carbon::parse` | dengan garis miring ia menebak m/d/Y (Amerika): 09/08 jadi 9 Agustus |
| **Jangan** `Carbon::createFromFormat` | melempar exception untuk satu entri berformat menyimpang → modal 500 |

- Tabel **tetap tampil saat kosong**, dengan
  `<td colspan="N" class="ds-c text-muted">Belum ada data tersimpan</td>`.
- Daftar kartu-baris (`<div>` per entri) tidak dipakai.

---

## 5b. Dua layar untuk modul multi-entri (WAJIB)

Formulir **tidak** nongkrong bersama daftarnya. Modal punya dua layar dalam satu `<x-modal>`:
`'daftar'` (tabel §5) ⇄ `'form'` (isi entri baru / lanjutkan draft). Alasan: formulir yang tampil
terus lalu dikosongkan diam-diam sesudah tersimpan membuat petugas mengetik ulang di atasnya →
**draft duplikat**.

```php
public string $layar = 'daftar';
public ?string $editingKey = null;          // kunci entri = signatureDate (stabil, bukan index)

public function diForm(): bool { return !$this->isFormLocked && ($this->editingKey !== null || $this->layar === 'form'); }
public function tambahEntri(): void { $this->cancelEdit(); $this->layar = 'form'; }
public function kembaliKeDaftar(): void { $this->cancelEdit(); }
public function editEntri(string $key): void { /* tolak bila entriFinal(); hydrate form; editingKey = $key; layar = 'form' */ }
private function resetNewConsent(): void { /* … */ $this->layar = 'daftar'; }   // reset = kembali ke daftar
```

| Aturan | Isi |
|---|---|
| Nama method **tetap** | `diForm()`, `tambahEntri()`, `kembaliKeDaftar()`, `editEntri($key)`, `saveDraft()`, `bukaKunci($key)` |
| `reset*()` menyetel `layar = 'daftar'` | sehingga setiap jalur (kunci, batal, hapus entri yang sedang diedit) otomatis balik ke daftar tanpa `$layar = 'daftar'` manual |
| `@if ($this->diForm())` | dipasang **tepat sebelum `<section>` formulir**, `@endif` sesudah section TTD; **bukan** di header modal (badge + display pasien harus tetap tampil di layar daftar) |
| `@unless ($this->diForm())` | membungkus tabel daftar |
| Simpan Draft | upsert by `editingKey` (`persistEntry($key, …)`), hanya kolom kunci (mis. `tindakan`) yang wajib; sesudahnya `editingKey = $key` supaya simpan dua kali tidak membuat entri kembar; **tetap di form** |
| TTD petugas | validasi penuh → stempel → `persistEntry` → `cancelEdit()` (balik ke daftar). Entri final ditolak `persistEntry` (harus Buka Kunci dulu) |
| Footer | layar form: **Kembali ke Daftar** + **Simpan Draft / Simpan Perubahan**; layar daftar: **Tutup** + **Isi Formulir Baru** (`tambahEntri`) |
| Aksi tabel | entri Draft: `<x-primary-button wire:click="editEntri(...)">Lanjutkan Pengisian</x-primary-button>` sebelum Lihat/Cetak; entri final: Buka Kunci (`x-confirm-button warning-soft`, `@can('dokumen.bukaKunci')`) di kelompok berisiko |

Modul **sekali-entri** (General Consent, Suket) tidak memakai dua layar.

---

## 6. Kartu & tab di hub

Kartu ringkas (dirender oleh komponen anaknya, di atas modal):

```
[judul · badge · deskripsi (1 baris + "Selengkapnya")]              [Buka … ]
[ringkasan entri terbaru — opsional]
```

```blade
<div class="flex items-baseline flex-1 gap-2 min-w-0">        {{-- min-w-0 WAJIB --}}
    <h3 class="text-base font-semibold truncate shrink-0 …">Inform Consent</h3>
    <x-badge variant="success" class="shrink-0 whitespace-nowrap">{{ $icCount }} tindakan</x-badge>
    <x-deskripsi-ringkas>Persetujuan tindakan medis per-tindakan: …</x-deskripsi-ringkas>
</div>
```

Tanpa `min-w-0` pada induk `flex-1`, `truncate` tidak menggigit dan kartunya melar sampai
tombol "Buka …" terdorong keluar layar. Badge tanpa `shrink-0 whitespace-nowrap` patah dua baris.

**Badge "ada data" di tab hub** (`⚡modul-dokumen-rj.blade.php`) — wajib untuk setiap tab:

| Tipe | Isi badge | Sumber |
|---|---|---|
| multi-entri | jumlah entri | `$this->jumlahInformConsent()` |
| sekali-entri | `&#10003;` | `$this->generalConsentTerisi()`, `$this->suketTerisi()` |

Gaya seragam `<x-badge variant="success" class="text-[10px] px-1.5 py-0">`.
Hitungannya **di method komponen**, bukan logika di `@php` template (lihat skill
`naming-conventions` §2).

---

## 7. Keadaan modul yang ada sekarang + backlog

Tiga modul RJ sudah diselaraskan ke §3–§6, dan modul multi-entri sudah dua layar (§5b):

| Modul | Dua layar? | Catatan |
|---|---|---|
| Surat Keterangan (`suket`) | — (sekali-entri, tak perlu) | 2 sub-tab (Sehat / Istirahat), `x-cetak-button` per tab; TTD di cetakan = dokter pemeriksa kunjungan, tidak ada stempel petugas di layar; viewer RM `⚡suket-view-rj` (12 Sep 2026) dua baris Sehat/Istirahat, hanya yang terisi |
| General Consent | — (sekali-entri, tak perlu) | TTD petugas = pengunci + Buka Kunci (`dokumen.bukaKunci`); form read-only saat final |
| Inform Consent | **Ya** (11 Sep 2026) | `$layar`/`$editingKey`/`diForm()`; Simpan Draft (hanya tindakan wajib) → TTD pemberi informasi = validasi penuh + kunci; Lanjutkan Pengisian; Buka Kunci per entri |

### Backlog (sengaja belum dikerjakan)

1. **Kelola User menyimpan TTD ke folder `ttd/`**, sedangkan data nyata di kolom
   `myuser_ttd_image` memakai `UserTtd/…`. Keduanya terbaca (`TtdUser` menangani nilai
   ber-slash apa pun), tapi dua folder untuk satu keperluan sebaiknya disatukan.
2. **Isi modal hub Modul Dokumen sudah lazy** (`@if ($rjNo)` di `⚡modul-dokumen-rj`, anak memuat
   dari prop di `mount()`), tetapi tiga tab di dalamnya masih Alpine `x-show` (semua anak mounted
   saat modal terbuka) — sengaja, karena komponen dokumen ringan (lihat `docs/standar-ui-komponen.md` §1b).

---

## 8. Verifikasi

```bash
php artisan view:cache && php artisan view:clear      # EXIT 0 = pipeline Blade asli lolos
```

`php -l` **tidak cukup** untuk Blade — ia lolos walau struktur tag kacau. Hitung
keseimbangan tag per berkas yang diedit (`@if`/`@endif`, `@can`/`@endcan`,
`@forelse`/`@endforelse`, `<x-modal`/`</x-modal`) dan buktikan lewat **hasil render**
Livewire, bukan urutan baris di berkas. Saat menguji: **jangan** memanggil `save()` —
isi daftar entri lewat `->set('consentList', $entriPalsu)` supaya DB tidak tersentuh.
