# PCare BPJS Integration — Klinik Pratama

Dokumentasi alur integrasi BPJS PCare di siklik-php82 (klinik pratama).

## Komponen

- **Trait**: `app/Http/Traits/BPJS/PcareTrait.php` — endpoint PCare (signature HMAC, AES decrypt, dll)
- **Config**: `config/bpjs.php` grup `pcare` — semua kredensial & URL (dibaca lewat `config()`, bukan `env()`)
- **Transport**: `app/Support/Bpjs/BpjsHttp.php` — satu pintu keluar semua panggilan BPJS (batas waktu + proxy whitelist IP)
- **Helper trait**: `app/Http/Traits/customErrorMessagesTrait.php` — Indonesian validation messages
- **Wired ke**: `resources/views/pages/transaksi/rj/daftar-rj/⚡daftar-rj-actions.blade.php`
- **Trigger UI**: dropdown menu di `⚡daftar-rj.blade.php` (kolom Actions per baris pasien)

## Konfigurasi — `.env` → `config/bpjs.php`

`.env` tetap tempat menyimpan nilainya, tapi **kode membacanya lewat `config()`**, bukan `env()`.
`env()` mengembalikan `null` begitu `php artisan config:cache` dijalankan, sehingga semua panggilan
BPJS diam-diam gagal di produksi. Aturan: **jangan ada `env()` di dalam `app/`**.

Isi di `.env`:

```env
PCARE_URL=https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev/
PCARE_CONS_ID=<consumer ID dari BPJS>
PCARE_SECRET_KEY=<consumer secret>
PCARE_USER_KEY=<user key>
PCARE_USERNAME=<username PCare>
PCARE_PASSWORD=<password PCare>
PCARE_PROVIDER=<kode faskes 8 digit, e.g., 0184B007>
PCARE_DESC=<keterangan faskes>
```

Pemetaan env → config (`config/bpjs.php`):

| `.env` | dibaca di kode sebagai |
|---|---|
| `PCARE_URL` | `config('bpjs.pcare.url')` |
| `PCARE_CONS_ID` | `config('bpjs.pcare.cons_id')` |
| `PCARE_SECRET_KEY` | `config('bpjs.pcare.secret_key')` |
| `PCARE_USER_KEY` | `config('bpjs.pcare.user_key')` |
| `PCARE_USERNAME` | `config('bpjs.pcare.username')` |
| `PCARE_PASSWORD` | `config('bpjs.pcare.password')` |
| `PCARE_PROVIDER` | `config('bpjs.pcare.provider')` |
| `PCARE_DESC` | `config('bpjs.pcare.desc')` |

> Untuk test pakai BPJS dev: minta kredensial sandbox ke BPJS via help desk.

Setelah edit `.env`, jalankan:
```bash
php artisan config:clear
```

### Jalur keluar (whitelist IP BPJS)

Sejak 2 Sep 2026 BPJS hanya melayani IP publik yang di-whitelist (pengajuan lewat ITSM BPJS).
Semua panggilan PCare keluar lewat `App\Support\Bpjs\BpjsHttp::mulai()`, yang bisa dialihkan ke
forward proxy VPS dengan saklar `BPJS_PROXY_AKTIF=true` + `BPJS_PROXY_URL`.
Cek dengan `php artisan bpjs:cek-proxy`. Rinciannya: `docs/bpjs-whitelist-ip-proxy.md`.

## Alur Pendaftaran BPJS (PCare addPedaftaran)

### Auto-trigger
Setelah save daftar-rj sukses (pasien BPJS, klaimId='JM'), `afterSave()` panggil `pushPendaftaranBPJS()` otomatis. Kalau **vital signs belum lengkap**, di-skip silent (tunggu perawat input pemeriksaan dulu).

### Manual trigger (retry)
Klik dropdown "⋮" di baris pasien → **"Kirim Pendaftaran BPJS"**.

### Conditions (dispatch dari menu)
- `klaim_status === 'BPJS'` ATAU `klaim_id === 'JM'`
- Role: Admin, Mr, Perawat

### Required vital signs (dari `pemeriksaanFisik` di JSON):
- `sistole` (>0)
- `diastole` (>0)
- `nadi` (>0)
- `rr` atau `respirasi` (>0)
- `beratBadan`, `tinggiBadan`, `lingkarPerut` (boleh 0, tapi BPJS prefer non-zero)

> **Sisa pekerjaan:** `resources/views/.../⚡daftar-rj-actions.blade.php` masih memakai
> `env('PCARE_PROVIDER')` — harus diganti `config('bpjs.pcare.provider')` (file view sedang
> dikerjakan paralel, jadi belum disentuh di perubahan ini). Selama belum diganti,
> `php artisan config:cache` akan membuat `kdProviderPeserta` kosong.

### Field yang dikirim ke BPJS
| Field BPJS | Source |
|------------|--------|
| `kdProviderPeserta` | `config('bpjs.pcare.provider')` |
| `tglDaftar` | `dataDaftarPoliRJ.rjDate` (format d-m-Y) |
| `noKartu` | `dataPasien.pasien.identitas.nokartuBpjs` (digit-only) |
| `kdPoli` | `dataDaftarPoliRJ.kdpolibpjs` |
| `keluhan` | `anamnesa.keluhanUtama` atau `anamnesa.anamnesa` (fallback `-`) |
| `kunjSakit` | `1` (selalu) |
| `sistole/diastole/heartRate/respRate/lingkarPerut/beratBadan/tinggiBadan` | dari `pemeriksaanFisik` |
| `rujukBalik` | `'N'` |
| `kdTkp` | `'10'` (RJTP klinik pratama) |

## Alur Kunjungan BPJS (PCare addKunjungan)

### Manual trigger
Klik dropdown "⋮" → **"Kirim Kunjungan BPJS"**.

### Conditions
- klaim BPJS (sama)
- `rj_status === 'L'` (RJ status Selesai)
- Role: Admin atau Dokter
- **Pendaftaran sudah sukses** (`pcarePendaftaran.code` = 200/201) — wajib

### Required EMR data
- `diagnosis[0].icdX` — diagnosa primer (ICD-10 code) — wajib
- `diagnosis[1].icdX`, `diagnosis[2].icdX` — sekunder/tertier (opsional)
- `pemeriksaanFisik` (vital signs) — sama dgn pendaftaran
- `perencanaan.terapiObat` (opsional, default `-`)
- `perencanaan.terapiNonObat` (opsional)
- `perencanaan.kdStatusPulang` (opsional, default `4` = Berobat Jalan)
- `perencanaan.kdPrognosa` (opsional, default `01` = Sanam/sembuh)

### Field tambahan yg dihardcode
- `noKunjungan`: `'RJ-{rjNo}'` (klinik internal — tidak ada konflik dgn BPJS)
- `kdSadar`: `'01'` (Compos Mentis default — bisa override dari `pf.kdSadar`)
- `tglPulang`: `now()->format('d-m-Y')`
- `kdTacc`: `-1` (no TACC)
- `alergiMakan/Udara/Obat`: `'00'` default (no allergy)

## Status Tracking

Setelah call BPJS sukses/gagal, status disimpan di `datadaftarpolirj_json`:

```json
{
  "taskIdPelayanan": {
    "pcarePendaftaran": {
      "code": 200,
      "message": "OK",
      "sentAt": "2026-04-28 14:32:11",
      "response": {...}
    },
    "pcareKunjungan": {
      "code": 200,
      "message": "OK",
      "sentAt": "2026-04-28 15:45:09",
      "response": {...}
    }
  }
}
```

Idempotent — kalau sudah `code=200/201`, manual trigger akan SKIP (cek log).

## Logging & Debugging

### Laravel log (`storage/logs/laravel.log`)
Setiap call PCare di-log:
- `INFO PCare addPedaftaran request`: payload yg dikirim
- `INFO PCare addPedaftaran skipped`: alasan skip (vital signs / sudah sent)
- `ERROR PCare addPedaftaran exception`: error API/network

### `web_log_status` table (Oracle)
PcareTrait insert ke tabel ini setiap call:
```sql
SELECT * FROM web_log_status
WHERE http_req LIKE '%pcare%'
ORDER BY date_ref DESC
FETCH FIRST 20 ROWS ONLY;
```

Kolom utama:
- `code`: HTTP/BPJS status code
- `http_req`: URL endpoint
- `response`: JSON response (BPJS sudah di-decrypt)
- `requesttransfertime`: durasi network call (detik)
- `date_ref`: timestamp

## Test Steps

### Persiapan
1. Edit `.env` — isi `PCARE_*` credentials sandbox.
2. `php artisan config:clear` (wajib; kode membaca lewat `config('bpjs.pcare.*')`)
3. Pastikan ada minimal 1 pasien BPJS aktif di siklik dengan `nokartu_bpjs` valid (13 digit).
4. Pastikan `master.poli` punya `kd_poli_bpjs` valid untuk poli yang dipakai.
5. Pastikan `master.dokter` punya `kd_dr_bpjs` valid untuk dokter yang dipakai.

### Test Pendaftaran
1. Login → menu **Daftar Rawat Jalan**.
2. Tambah pendaftaran baru: pasien BPJS, poli, dokter, klaimId=BPJS (JM), tanggal hari ini.
3. Save. Cek toast: kalau vital signs belum ada → tidak ada toast PCare (silent skip).
4. Buka EMR → Pemeriksaan → input vital signs (TD, nadi, RR, BB, TB).
5. Kembali ke daftar RJ → klik "⋮" → **"Kirim Pendaftaran BPJS"**.
6. Cek toast hasil. Cek `storage/logs/laravel.log` + `web_log_status` table.

### Test Kunjungan
1. Setelah pendaftaran sukses, lanjut input EMR:
   - Diagnosa (minimal primer ICD-10)
   - Perencanaan (terapi obat/non-obat, status pulang, prognosa)
2. Set `rj_status = 'L'` (Selesai).
3. Klik "⋮" → **"Kirim Kunjungan BPJS"**.
4. Cek toast + log.

## Common Issues

| Error | Penyebab | Fix |
|-------|----------|-----|
| `code=400` invalid signature | `PCARE_*` env salah | Re-check env, `config:clear` |
| `code=201` validation error | Field wajib kosong | Cek log payload, isi vital signs / diagnosa |
| `code=404` peserta tidak ditemukan | `noKartu` salah | Verify `nokartu_bpjs` di master pasien |
| `code=408` timeout | Network ke BPJS lambat | Retry, cek koneksi |
| Skip pendaftaran (silent) | Vital signs belum lengkap | Input dulu di EMR Pemeriksaan |
| Skip kunjungan (with toast) | Pendaftaran belum sukses | Kirim Pendaftaran dulu |

## Catatan

- **kdPoli BPJS** di `skmst_polis.kd_poli_bpjs` harus terisi sesuai master BPJS PCare. Cek via `PcareTrait::getPoliFktp()` (perlu di-wire ke UI).
- **kdDokter BPJS** di `skmst_doctors.kd_dr_bpjs` harus terisi.
- Method PcareTrait lain yg belum di-wire: `getRiwayatKunjungan` (riwayat pasien), `getDiagnosa` (cari ICD-10 dari BPJS), `editKunjungan/deleteKunjungan` (revisi).
- TaskID 1-7 + 99 internal state tracking masih ada (di `task-id-pelayanan/`), tapi **TIDAK dipush ke BPJS Antrol** (klinik pratama tidak pakai Antrol).
