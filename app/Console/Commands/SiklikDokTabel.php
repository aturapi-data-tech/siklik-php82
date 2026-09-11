<?php

namespace App\Console\Commands;

use App\Support\Skema\KamusData;
use App\Support\Skema\ModulTabel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Tulis docs/struktur-tabel.md dari data dictionary Oracle yang terhubung:
 * peta rename, tabel per modul, relasi FK + implisit, diagram Mermaid per modul.
 * Versi web interaktifnya: /panduan-dev/struktur-tabel.
 *
 *   php artisan siklik:dok-tabel
 */
class SiklikDokTabel extends Command
{
    protected $signature = 'siklik:dok-tabel {--out=docs/struktur-tabel.md : Berkas markdown tujuan}';

    protected $description = 'Tulis docs/struktur-tabel.md (peta rename, modul, relasi antar tabel) dari data dictionary Oracle';

    public function handle(): int
    {
        $out = base_path($this->option('out'));
        $tabel = KamusData::tabel();
        $perModul = KamusData::perModul();
        $relasi = KamusData::relasi();
        $implisit = KamusData::relasiImplisit();
        $peta = KamusData::petaRename();
        $keterangan = ModulTabel::daftar();

        $relasiPer = [];
        foreach ($relasi as $r) {
            $relasiPer[$r['modul']][] = $r;
        }
        $implisitPer = [];
        foreach ($implisit as $r) {
            $implisitPer[$r['modul']][] = $r;
        }

        $md = [];
        $md[] = '# Struktur Tabel siklik';
        $md[] = '';
        $md[] = sprintf('Dibuat otomatis oleh `php artisan siklik:dok-tabel` pada %s dari data dictionary Oracle. Jangan edit manual.', now()->format('d/m/Y H:i'));
        $md[] = 'Versi interaktif (selalu terbaru): menu **Sistem → Struktur Tabel** (`/panduan-dev/struktur-tabel`).';
        $md[] = '';
        $md[] = sprintf('| Tabel | View | Relasi FK | Relasi implisit | Objek di-rename |');
        $md[] = '|---|---|---|---|---|';
        $md[] = sprintf('| %d | %d | %d | %d | %d |',
            count(array_filter($tabel, fn ($t) => $t['jenis'] === 'TABLE')),
            count(array_filter($tabel, fn ($t) => $t['jenis'] === 'VIEW')),
            count($relasi), count($implisit), count($peta));
        $md[] = '';
        $md[] = '## 1. Aturan nama (sejak 11 Sep 2026)';
        $md[] = '';
        $md[] = 'Huruf modul lama `DI IM LB RS SC TK` dilebur menjadi prefix program `SK`; jenis objek dipertahankan.';
        $md[] = '';
        $md[] = '| Prefix | Arti |';
        $md[] = '|---|---|';
        foreach (ModulTabel::prefix() as $p => $k) {
            $md[] = "| `$p` | $k |";
        }
        $md[] = '';
        $md[] = 'Tidak di-rename: tabel sistem Laravel/Spatie, `PASIEN`, `REF_BPJS_TABLE`, `REFERENSI_MOBILEJKN_BPJS`, `WEB_LOG_STATUS`, `INSTALL_TOKOKU`, semua sequence & trigger.';
        $md[] = 'Nama lama tetap bisa dipakai lewat synonym (untuk siklik-lite legacy); kode siklik-php82 wajib memakai nama baru.';
        $md[] = 'Skrip & urutan eksekusi: `database/sql/rename-siklik/README.md`.';
        $md[] = '';
        $md[] = '## 2. Peta rename';
        $md[] = '';
        $md[] = '| Jenis | Lama | Baru | Modul |';
        $md[] = '|---|---|---|---|';
        foreach ($peta as $p) {
            $md[] = sprintf('| %s | `%s` | `%s` | %s |', $p['jenis'], $p['lama'], $p['baru'], $p['modul']);
        }
        $md[] = '';
        $md[] = '## 3. Tabel per modul & relasi';
        $md[] = '';
        $md[] = 'Panah `→` = FK terdeklarasi (dijaga Oracle). Panah `⇢` = relasi implisit: kolom bernama sama dengan PK tabel lain tanpa FK, dijaga oleh kode.';
        $md[] = '';
        foreach ($perModul as $modul => $daftar) {
            $md[] = "### {$modul}";
            $md[] = '';
            $md[] = '_'.($keterangan[$modul] ?? '').'_';
            $md[] = '';
            $md[] = '| Objek | Jenis | Kolom |';
            $md[] = '|---|---|---|';
            foreach ($daftar as $t) {
                $md[] = sprintf('| `%s` | %s | %d |', $t['nama'], $t['jenis'], $t['kolom']);
            }
            $md[] = '';
            if (! empty($relasiPer[$modul]) || ! empty($implisitPer[$modul])) {
                $md[] = '| Anak.kolom | | Induk.kolom | Constraint |';
                $md[] = '|---|---|---|---|';
                foreach ($relasiPer[$modul] ?? [] as $r) {
                    $md[] = sprintf('| `%s.%s` | → | `%s.%s` | `%s` |', $r['anak'], $r['kolomAnak'], $r['induk'], $r['kolomInduk'], $r['constraint']);
                }
                foreach ($implisitPer[$modul] ?? [] as $r) {
                    $md[] = sprintf('| `%s.%s` | ⇢ | `%s` | implisit |', $r['anak'], $r['kolom'], $r['induk']);
                }
                $md[] = '';
            }
            if (! empty($relasiPer[$modul])) {
                $md[] = '```mermaid';
                $md[] = KamusData::mermaid($modul);
                $md[] = '```';
                $md[] = '';
            }
        }
        $md[] = '## 4. Konvensi tabel baru';
        $md[] = '';
        $md[] = '- Prefix `SKMST_/SKTXN_/SKACC_/SKVIEW_`, nama jamak Inggris huruf besar, ≤ 30 karakter; daftarkan polanya di `App\Support\Skema\ModulTabel`.';
        $md[] = '- Constraint: `AKAR_PK`, `AKAR_KOLOM_FK`, `AKAR_KOLOM_UK`; index `AKAR_KOLOM_IX` (`AKAR` = nama tabel tanpa prefix, `_ID` kolom dibuang).';
        $md[] = '- Perubahan schema = SQL idempoten di `database/sql/`, bukan migration Laravel. Sequence `NAMA_SEQ` via `.nextval`.';
        $md[] = '- Dokumen per kunjungan yang bentuknya berubah → satu kolom CLOB JSON; Oracle 10g tanpa `JSON_VALUE`/`LISTAGG`.';
        $md[] = '';

        File::ensureDirectoryExists(dirname($out));
        File::put($out, implode("\n", $md));
        $this->info(sprintf('%s ditulis (%d baris, %d tabel/view, %d relasi).', $this->option('out'), count($md), count($tabel), count($relasi)));

        return self::SUCCESS;
    }
}
