# Standar Modul Master (CRUD List + Form)

README standarisasi pengkodingan & UI/UX untuk semua modul di `resources/views/pages/master/`.
Tujuan: kode ringkas, seragam, dan mudah diaudit programmer lain — cukup hafal SATU pola.

**Acuan kanonik: `master-agama`**
(`resources/views/pages/master/master-klinik/master-agama/` — 160 baris LIST + 231 baris FORM).
Halaman referensi skema tabel: route `/panduan-dev/struktur-tabel`.

> Struktur folder & penamaan berkas di luar isi modul diatur di `docs/standar-struktur-folder.md`.
> Markup umum (tombol, modal, frame halaman) di `docs/standar-komponen-tombol.md`,
> `docs/standar-ui-komponen.md`, `docs/page-frame-pattern.md`, `docs/dirty-modal-pattern.md`.

> **Kondisi sekarang (audit 11 Sep 2026):** 42 modul master di 6 grup — `master-klinik` 19,
> `master-apotek` 11, `master-akuntansi` 4, `master-wilayah` 4, `master-tarif` 3, `master-lab` 1.
> 43 berkas LIST + 40 berkas `-actions`; 38 dari 41 LIST tingkat-modul memakai
> `x-action-edit`/`x-action-delete`. Deviasi nyata tercatat di §9.

---

## 1. Struktur File & Routing

Satu modul master = **satu folder, dua berkas Volt SFC**:

```
resources/views/pages/master/master-<grup>/master-<nama>/
├── ⚡master-<nama>.blade.php           # LIST  : tabel + toolbar + pagination
└── ⚡master-<nama>-actions.blade.php   # FORM  : modal create/edit + delete handler
```

`<grup>` adalah salah satu dari: `akuntansi`, `apotek`, `klinik`, `lab`, `tarif`, `wilayah`.
Level grup **hanya** urusan pemfolderan — ia tidak ikut ke namespace event maupun nama route.

- Semua berkas = **Volt SFC anonymous class** (`new class extends Component {...}`), tanpa Controller.
- Prefix `⚡` wajib untuk keduanya (lihat `standar-struktur-folder.md` §3.1).
- Routing eksplisit di `routes/web.php` (bukan Folio):

```php
Route::livewire('/master/<nama>', 'pages::master.master-<grup>.master-<nama>.master-<nama>')
    ->name('master.<nama>');
```

Perhatikan: URL **tidak** memuat grup (`/master/agama`, bukan `/master/klinik/agama`) — grup adalah
detail organisasi berkas, bukan hierarki yang dilihat petugas.

- LIST me-mount FORM sebagai child di akhir markup:

```blade
<livewire:pages::master.master-<grup>.master-<nama>.master-<nama>-actions
    wire:key="master-<nama>-actions" />
```

**Kapan boleh menyimpang** (varian resmi, lihat §8): master-detail hierarkis, form multi-section
multi-partial, atau halaman integrasi/konfigurasi tunggal.

---

## 2. Kontrak Penamaan

| Hal | Standar | Contoh |
|---|---|---|
| State pencarian | `searchKeyword` | — |
| State per halaman | `itemsPerPage` (default 10) | — |
| Reset filter | method `resetFilters()` | dipanggil `x-toolbar-refresh-reset` |
| Data list | `#[Computed] rows()` | `$this->rows` di markup |
| Event namespace | `master.<namafolder-tanpa-prefix>.*` | `master.agama.openCreate` |
| Verb event | `openCreate` / `openEdit` / `requestDelete` / `saved` | — |
| Mode form | `$formMode` (`'create'`\|`'edit'`) + `$originalId` | — |
| State form | array `$form = [...]` (key = nama kolom DB) | `form.rel_desc` |
| `wire:key` baris | `<slug>-{{ $row->pk }}` | `agama-{{ $row->rel_id }}` |

Aturan tambahan:
- Event namespace **harus sama dengan nama folder** (`master-cara-bayar` → `master.cara-bayar.*`,
  BUKAN `master.carabayar.*`). Status sekarang: **semua 39 modul ber-event sudah patuh.**
- LIST **tidak boleh berisi validasi/simpan** — ia hanya dispatch event ke FORM. Semua
  `validate()`/insert/update/delete hidup di berkas `-actions`. Deviasi tercatat di §9.
- Satu pengecualian yang sah: **toggle status** (`active_status`, `kas_status`) boleh tinggal di
  LIST karena ia bukan form — satu klik, satu kolom, tanpa validasi. Dipakai `master-akun`,
  `master-tucico`, `master-cara-bayar`, `master-signa-catatan`, `master-dokter`.

---

## 3. Komponen LIST — anatomi

Kerangka lengkap: lihat `⚡master-agama.blade.php`. Ringkasan kelas PHP:

```php
new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void { /* reset + resetPage */ }

    public function openCreate(): void            { $this->dispatch('master.<x>.openCreate'); }
    public function openEdit(int $id): void       { $this->dispatch('master.<x>.openEdit', relId: $id); }
    public function requestDelete(int $id): void  { $this->dispatch('master.<x>.requestDelete', relId: $id); }

    #[On('master.<x>.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        $q = DB::table('skmst_...')->select(...)->orderBy(...);
        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(kolom) LIKE ?', ["%{$kw}%"]);
        }
        return $q->paginate($this->itemsPerPage);
    }
};
```

- Query pakai **`DB::table()`** (bukan Eloquent) — tabel Oracle warisan tidak punya model, dan
  memang tidak boleh dibuatkan (`standar-struktur-folder.md` §6.0).
- Nama tabel memakai prefix `SKMST_/SKTXN_/SKACC_/SKVIEW_` — daftar lengkap di
  `docs/struktur-tabel.md`.
- Pencarian **case-insensitive Oracle**: `UPPER(kolom) LIKE` + `mb_strtoupper`
  (baca skill `oracle-quirks` — Oracle di sini 10g, tanpa fungsi JSON native).
- Query kompleks (join banyak / dipakai ulang untuk export): pisahkan **`baseQuery()`** privat,
  `rows()` tinggal `->paginate()`.

Markup (urutan wajib):

1. `<x-page-title title="Master X" subtitle="..." />`
2. Frame flex-fill: `h-[calc(100vh-5rem)]` → `flex flex-col flex-1 min-h-0`
   (detail: `docs/page-frame-pattern.md`)
3. **Toolbar sticky**, isi: search (`wire:model.live.debounce.300ms="searchKeyword"`) ·
   select `itemsPerPage` · `<x-primary-button wire:click="openCreate">+ Tambah …</x-primary-button>` ·
   `<x-toolbar-refresh-reset :label="null" />`
4. **Card tabel** `flex flex-col flex-1 min-h-0` + border/rounded sesuai token
5. **Tabel** + `<thead class="sticky top-0 z-10">`
6. Baris: `wire:key` unik + kolom Aksi:

```blade
<td class="text-center">
    <div class="flex justify-center gap-2">
        <x-action-edit wire:click="openEdit({{ $row->rel_id }})" />
        <x-action-delete :action="'requestDelete(' . $row->rel_id . ')'"
            title="Hapus Agama" message="Yakin hapus {{ $row->rel_desc }}?" />
    </div>
</td>
```

7. Empty state: `@forelse/@empty` + `<td colspan="N">` ikon + teks (JANGAN panel `isEmpty()` sendiri)
8. Pagination sticky bottom: `{{ $this->rows->links() }}`
9. Mount FORM sebagai child (§1)

> **Catatan token tabel.** Kelas utilitas `.ds-table`, `.ds-td-token`, `.ds-td-strong`, `.ds-c`
> sudah tersedia di `resources/css/app.css`, tetapi **belum dipakai satu pun modul master** — semua
> masih menulis kelas header/sel manual (`min-w-full text-sm`, `text-gray-600 bg-gray-50 …`).
> Migrasi ke kelas `ds-*` adalah pekerjaan design-system yang sedang berjalan; ikuti
> `docs/standar-ui-komponen.md` sebagai sumber kebenaran markup, dan jangan menulis ulang kelas
> header manual di modul baru.

---

## 4. Komponen FORM (`-actions`) — anatomi

```php
new class extends Component {
    use WithRenderVersioningTrait;               // renderKey utk remount modal bersih

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $form = [ /* key = kolom DB, semua string '' */ ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[On('master.<x>.openCreate')]    public function openCreate(): void { ... }
    #[On('master.<x>.openEdit')]      public function openEdit(int $id): void { ... }
    #[On('master.<x>.requestDelete')] public function delete<X>(int $id): void { ... }

    public function save(): void { ... }
    public function closeModal(): void { ... }
    private function resetForm(): void { ... }
};
```

Status: **40 dari 40 berkas `-actions` sudah memakai `WithRenderVersioningTrait` dan
`x-dirty-modal-content`** — tidak ada deviasi di titik ini.

Alur wajib tiap handler:

- **openCreate/openEdit**: `resetForm()` → set `formMode`/`originalId`/`form` → `incrementVersion('modal')`
  → `dispatch('open-modal', name: '...')` → dispatch event fokus field pertama.
- **save()**: `validate()` DULUAN (jangan early-return sebelum validate — field merah tak muncul)
  → insert/update → `dispatch('toast', type:'success', ...)` → `closeModal()` → `dispatch('master.<x>.saved')`.
- **closeModal()**: `resetForm()` → `dispatch('close-modal', ...)` → `resetVersion()`.

Markup modal (3 bagian — header/body/footer):

```blade
<x-modal name="master-<x>-actions" size="full" height="full" focusable>
    <x-dirty-modal-content name="master-<x>-actions" event="master.<x>.saved" label="X"
        :wireKey="$this->renderKey('modal', [$formMode, $originalId])">
        {{-- HEADER: logo + judul Tambah/Ubah + <x-badge> Mode + close X (x-icon-button tryClose()) --}}
        {{-- BODY  : x-enter-chain + <x-border-form title="...">fields</x-border-form> --}}
        {{-- FOOTER: sticky bottom — hint kbd Enter · Batal (tryClose()) · Simpan (wire:loading) --}}
    </x-dirty-modal-content>
</x-modal>
```

- Field wajib pakai `:error="$errors->has(...)"` + `<x-input-error>` di bawahnya.
- Navigasi keyboard: `x-enter-chain` di body + `x-on:keydown.enter.prevent` per field
  (field terakhir → `$wire.save()`); fokus via `x-ref` + event window
  (baca skill `livewire-input-patterns` — di sanalah jebakan digit hilang & race Enter dicatat).
- Field nominal uang / angka → `<x-text-input-number>` (jangan `type="number"` biasa).
- Form multi-section: `<x-dirty-modal-content>` + `@include` partial per section (lihat §7 & §8).
  Untuk form **bertab**, komponen `<x-tabbed-dirty-modal-content>` tersedia di
  `resources/views/components/` tetapi **belum dipakai satu pun modul** — pakai itu, jangan bikin
  tabs manual di dalam `x-dirty-modal-content`.
- **Field FK ke master lain → LOV** (`resources/views/livewire/lov/<entitas>/`, **31 tersedia** —
  jangan bikin dropdown pencarian manual). Kontrak: mount `<livewire:lov...>` dengan `target` unik
  + `wire:key` ber-`renderVersions`; LOV dispatch `lov.selected.<target>`; parent tangkap via
  `#[On]` → isi `$form` + `resetValidation`; validasi `Rule::exists` tetap di parent.
  Mode edit: FK terkunci → field readonly; FK boleh ubah → prop `initial*Id`.

LOV yang tersedia (31):

```
akun · asuhan-keperawatan · cara-bayar · cat-product · clabitem-group · desa · diag-kep ·
diagnosa · dokter · group-akun · group-product · jasa-dokter · jasa-karyawan · jasa-medis ·
kabupaten · kasir · lain-lain · loinc · outs · pasien · poli · procedure · product ·
product-non · propinsi · radiologi · room · snomed · supplier · tucico · uom
```

> **Jebakan `diagnosa`:** master ICD-10 (`SKMST_MSTDIAGS`) bisa punya `icdx` kembar, sehingga
> lookup naif (`value`/`first`) mengambil baris yang salah. Baca skill `diagnosa-flow` sebelum
> menyentuh apa pun yang memilih/menyimpan diagnosa.

---

## 5. Validasi

- Pesan **selalu Bahasa Indonesia** + `$attributes` nama field manusiawi.
- **Form kecil** (≤ ~5 field): tiga array inline di `save()` —
  `$this->validate($rules, $messages, $attributes)` (gaya baseline `master-agama`).
- **Form besar**: pisahkan method `rules()` / `messages()` / `validationAttributes()` supaya `save()`
  tetap pendek (gaya `master-pasien`/`master-dokter`/`master-product` — resmi, bukan deviasi).
- Rule unik hanya saat create:
  `formMode === 'create' ? 'required|...|unique:tabel,kolom' : 'required|...'`
  dan field PK `:disabled="$formMode === 'edit'"` di markup.

---

## 6. Delete — dua lapis pengaman (WAJIB)

1. **Konfirmasi UI**: selalu lewat `<x-action-delete>` (confirm-button danger) — JANGAN `wire:confirm`.
2. **Guard FK Oracle**: bungkus delete dengan catch `ORA-02292` → toast ramah:

```php
try {
    $deleted = DB::table('skmst_...')->where('pk', $id)->delete();
    if ($deleted === 0) { $this->dispatch('toast', type:'error', message:'Data tidak ditemukan.'); return; }
    $this->dispatch('toast', type:'success', message:'... berhasil dihapus.');
    $this->dispatch('master.<x>.saved');
} catch (QueryException $e) {
    if (str_contains($e->getMessage(), 'ORA-02292')) {
        $this->dispatch('toast', type:'error', message:'... tidak bisa dihapus karena masih dipakai di ....');
        return;
    }
    throw $e;
}
```

`ORA-02292` = *integrity constraint violated - child record found*. Tanpa catch, petugas melihat
error 500, bukan pesan yang bisa ditindaklanjuti. Status sekarang: **39 dari 40 `-actions` sudah
menangkapnya** — sisanya di §9.

Opsional (lebih informatif): cek eksplisit tabel pemakai sebelum delete — dianjurkan untuk master
bervolume tinggi seperti `master-pasien` (cek `sktxn_rjhdrs`) dan `master-dokter`.

---

## 7. Batas ukuran & kapan pecah berkas

- LIST ideal ≤ ~300 baris; FORM ≤ ~400 baris.
- FORM > ~400 baris ATAU > 1 section logis → pecah **partial per section**
  (`master-<x>-actions-<section>.blade.php`, di-`@include`) — contoh nyata `master-pasien`
  (9 partial: data-dasar-pasien, identitas, alamat-identitas, alamat-domisili, kontak,
  hubungan-keluarga, data-sosial, data-budaya, footer).
- Partial = markup murni (tanpa kelas Volt); state tetap di berkas `-actions` induk.
- **Partial tidak mewarisi `use` dari blok kelas induk.** Kalau blok yang dipindah memanggil nama
  kelas pendek, tambahkan `@use('App\…')` di kolom 0 pada partial — bukan `@php use …; @endphp`.
  Syarat lengkap (imbang tag, rekonstruksi byte-eksak, impor ikut pindah) ada di
  `standar-struktur-folder.md` §5.

Yang melewati batas sekarang: LIST `master-jadwal-mingguan` 704 & `master-dokter` 390;
FORM `master-pasien` 933, `master-dokter` 498, `master-product` 486.

---

## 8. Level kompleksitas & varian resmi

Tidak semua master sama beratnya — mulai SELALU dari Level 1, naik level hanya bila domain menuntut:

| Level | Ciri | Teknik tambahan | Contoh di siklik |
|---|---|---|---|
| **1 · Dasar** | satu tabel, CRUD murni | pola §1–§7 persis | `master-agama`, `master-pekerjaan`, `master-pendidikan`, `master-kemasan`, `master-uom`, seluruh `master-wilayah` |
| **2 · Menengah** | + FK / status / query berat | LOV, `baseQuery()`, toggle status, rules/messages method, form multi-section | `master-product`, `master-dokter`, `master-poli`, `master-pasien`, `master-akun` |
| **3 · Expert** | hierarki induk-anak / sub-list dalam form | verb event spesifik + payload konteks, panel detail, sub-form validasi bertahap | `master-laborat` (clab → clabitem), `master-jasa-dokter`/`master-jasa-karyawan`/`master-jasa-paramedis` |

Pola Level 3 yang terstandar — **hierarki (`master-laborat`)**: dua sub-folder
(`clab/`, `clabitem/`), masing-masing punya pasangan LIST + `-actions` sendiri, tetapi **berbagi
satu namespace event** `master.laborat.*` dengan verb spesifik
(`openCreateClab` / `openEditClab` / `deleteClab`, `openCreateClabitem` / `openEditClabitem` /
`deleteClabitem`); child list mendengarkan pemilihan induk; event `saved` memicu refresh presisi.

Level 3 BUKAN izin menaruh `validate()`/simpan di LIST.

### Varian struktur resmi (boleh menyimpang dari 2-berkas)

| Varian | Kapan | Contoh acuan di siklik |
|---|---|---|
| **Master-detail hierarkis** | Data induk-anak dikelola satu layar | `resources/views/pages/master/master-lab/master-laborat/{clab,clabitem}/` — folder modul induk tidak punya berkas sendiri; namespace event bersama + verb spesifik; child list embedded tanpa `x-page-title`/frame penuh |
| **Form multi-section** | Field sangat banyak, terbagi beberapa section | `master-pasien` (`x-dirty-modal-content` + 9 partial `@include`). Untuk versi bertab, pakai `x-tabbed-dirty-modal-content` (tersedia, belum ada pemakai) |
| **Konfigurasi tunggal (single-record)** | Bukan daftar; satu baris konfigurasi yang diedit langsung di halaman | `master-identitas` (kop identitas klinik, `SKMST_IDENTITASES`) |
| **Sinkronisasi/integrasi eksternal** | Bukan CRUD murni; tarik-simpan dari API | `master-ref-bpjs`, impor massal ICD-10 di `master-diagnosa` — trait API ikut `docs/trait-template-api-eksternal.md` |

Di luar empat varian ini, WAJIB ikut pola §1–§7.

---

## 9. Backlog deviasi (audit 11 Sep 2026)

Hasil pemeriksaan seluruh 42 modul master. **Dicatat, belum diperbaiki.**

### 🔴 Delete tanpa catch `ORA-02292` (§6)

| Modul | Berkas |
|---|---|
| `master-signa-catatan` | `resources/views/pages/master/master-apotek/master-signa-catatan/⚡master-signa-catatan-actions.blade.php` |

Satu-satunya dari 40 berkas `-actions`. Dampaknya: menghapus signa yang masih dirujuk resep akan
memunculkan error 500, bukan toast.

### 🟡 `validate()` / tulis DB di komponen LIST (§2)

| Modul | Berkas | Apa yang ada di LIST |
|---|---|---|
| `master-identitas` | `resources/views/pages/master/master-klinik/master-identitas/⚡master-identitas.blade.php` | `validate()` + `DB::table('skmst_identitases')->update(...)` (baris 70) — tidak ada berkas `-actions`, tidak ada `x-modal` |
| `master-jadwal-mingguan` | `resources/views/pages/master/master-klinik/master-jadwal-mingguan/⚡master-jadwal-mingguan.blade.php` | `->update()` + `->insert()` ke `skmst_scpolis` (baris 288, 304); 704 baris, tanpa `-actions` |
| `master-ref-bpjs` | `resources/views/pages/master/master-klinik/master-ref-bpjs/⚡master-ref-bpjs.blade.php` | `->delete()` + `->insert()` ke `ref_bpjs_table` (baris 74–75), tanpa `-actions` |
| `master-diagnosa` | `resources/views/pages/master/master-klinik/master-diagnosa/⚡master-diagnosa.blade.php` | `->insert()` ke `skmst_mstdiags` (baris 107) — impor massal ICD-10 dari API |
| `master-poli` | `resources/views/pages/master/master-klinik/master-poli/⚡master-poli.blade.php` | `->update(['kd_poli_bpjs' => …])` (baris 102) — pemetaan kode BPJS |
| `master-dokter` | `resources/views/pages/master/master-klinik/master-dokter/⚡master-dokter.blade.php` | `->update(['kd_dr_bpjs' => …])` (baris 99) — pemetaan kode BPJS |

Tiga yang pertama sebetulnya **varian resmi** (§8: konfigurasi tunggal / sinkronisasi eksternal),
jadi yang perlu diputuskan bukan “pindahkan ke `-actions`” melainkan **mengakui bentuknya secara
eksplisit** dan tetap memenuhi kontrak §2 yang relevan. Tiga yang terakhir adalah aksi integrasi
per-baris — kandidat dipindah ke `-actions` atau ke trait BPJS.

Toggle `active_status`/`kas_status` di `master-akun`, `master-tucico`, `master-cara-bayar`,
`master-signa-catatan`, `master-dokter` **bukan** deviasi (§2, pengecualian toggle).

### 🟡 Kontrak penamaan §2 tidak lengkap

| Modul | Yang hilang |
|---|---|
| `master-identitas` | `searchKeyword`, `itemsPerPage`, `resetFilters()`, `#[Computed] rows()` |
| `master-jadwal-mingguan` | `searchKeyword`, `itemsPerPage`, `resetFilters()` |
| `master-ref-bpjs` | `searchKeyword`, `itemsPerPage`, `resetFilters()` |

Ketiganya juga tidak memakai `x-action-edit`/`x-action-delete` (38 dari 41 LIST memakainya).
Untuk `master-identitas` sebagian wajar (tidak ada daftar); untuk dua lainnya perlu diperiksa.

### 🟢 Namespace event

**Tidak ada deviasi.** Seluruh 39 modul ber-event memakai `master.<nama-folder>.*` persis.
Tiga modul tanpa event sama sekali adalah tiga varian di atas (`master-identitas`,
`master-jadwal-mingguan`, `master-ref-bpjs`).

Catatan kecil: `master-laborat` memakai verb `deleteClab`/`deleteClabitem`, bukan
`requestDeleteClab`. Konsisten internal, tapi menyimpang dari tabel verb §2 — rapikan bila
kebetulan menyentuh berkas itu.

### 🟢 Ukuran berkas melewati batas §7

`master-jadwal-mingguan` LIST 704 · `master-pasien` FORM 933 · `master-dokter` FORM 498 /
LIST 390 · `master-product` FORM 486. `master-pasien` sudah dipecah 9 partial — sisanya blok kelas
Volt, jadi memecah markup lagi tidak menolong (lihat `standar-struktur-folder.md` §5).

### 🟢 Kelas tabel `ds-*` belum dipakai

`.ds-table`, `.ds-td-token`, `.ds-td-strong`, `.ds-c` sudah ada di `resources/css/app.css` tetapi
**0 modul master memakainya**; semua masih menulis kelas header/sel manual. Bagian dari pekerjaan
standardisasi UI yang sedang berjalan — ikuti `docs/standar-ui-komponen.md`.

### Modul yang lolos seluruh titik audit

Titik yang diperiksa: 2 berkas `⚡` per folder · kontrak state §2 · namespace event = nama folder ·
tanpa `validate()`/tulis DB di LIST · `x-action-edit`/`x-action-delete` · catch `ORA-02292` ·
`WithRenderVersioningTrait` + `x-dirty-modal-content` · batas ukuran §7.

Seluruh `master-wilayah` (4), seluruh `master-tarif` (3), `master-agama`, `master-cara-keluar`,
`master-cara-masuk`, `master-klaim`, `master-medik`, `master-others`, `master-parameter`,
`master-pekerjaan`, `master-pendidikan`, `master-procedure`, `master-radiologis`, dan seluruh
`master-apotek` kecuali `master-signa-catatan`.

---

## 10. Checklist audit modul master baru

- [ ] Folder `master-<grup>/master-<nama>/` + 2 berkas `⚡` (list & actions), route `Route::livewire`
      + `->name('master.<nama>')`, URL tanpa segmen grup
- [ ] Kontrak penamaan §2 (state, event `master.<folder>.*`, verb standar)
- [ ] LIST: page-title → frame flex-fill → toolbar sticky → tabel sticky head →
      `x-action-edit`/`x-action-delete` → empty state → pagination sticky → mount child `-actions`
- [ ] LIST tanpa validasi/DB-write (kecuali toggle status); semua mutasi di `-actions`
- [ ] FORM: `WithRenderVersioningTrait` + `x-modal` + `x-dirty-modal-content` + header/body/footer standar
- [ ] `validate()` sebelum logika lain; pesan Bahasa Indonesia + `validationAttributes`
- [ ] Delete: `x-action-delete` + catch `ORA-02292`
- [ ] FK ke master lain memakai LOV dari `livewire/lov/`, bukan dropdown manual
- [ ] Query `DB::table('sk…')` + `UPPER(kolom) LIKE` untuk pencarian; tanpa model Eloquent baru
- [ ] `x-enter-chain` + Enter di field terakhir = simpan; fokus otomatis saat modal buka
- [ ] Toast sukses/gagal via `dispatch('toast', ...)`; refresh list via event `saved`
- [ ] Berkas tidak melebihi batas §7; pecah partial bila perlu

---

## Referensi

| Apa | Di mana |
|---|---|
| Template kanonik | `resources/views/pages/master/master-klinik/master-agama/` |
| Struktur folder & penamaan berkas | `docs/standar-struktur-folder.md` |
| Nama tabel & relasi FK Oracle | `docs/struktur-tabel.md`, route `/panduan-dev/struktur-tabel` |
| `x-action-edit` / `x-action-delete` | `resources/views/components/action-{edit,delete}.blade.php` |
| `x-toolbar-refresh-reset` | `resources/views/components/toolbar-refresh-reset.blade.php` |
| `x-page-title`, `x-border-form`, `x-dirty-modal-content`, `x-tabbed-dirty-modal-content`, `x-text-input-number` | `resources/views/components/` |
| `WithRenderVersioningTrait` | `app/Http/Traits/WithRenderVersioning/WithRenderVersioningTrait.php` |
| LOV | `resources/views/livewire/lov/<entitas>/` (31 entitas) |
| Pola frame halaman | `docs/page-frame-pattern.md` |
| Modal dirty-guard | `docs/dirty-modal-pattern.md` |
| Tombol & UI umum | `docs/standar-komponen-tombol.md`, `docs/standar-ui-komponen.md` |
| Input numerik & Enter→`$wire` | skill `livewire-input-patterns` |
| Jebakan query Oracle 10g | skill `oracle-quirks` |
| Field & jebakan data pasien | skill `master-pasien` |
| Jebakan ICD-10 kembar | skill `diagnosa-flow` |
