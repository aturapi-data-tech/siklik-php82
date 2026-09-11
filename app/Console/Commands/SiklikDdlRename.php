<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Generator DDL untuk menyeragamkan prefix tabel/view Oracle siklik.
 *
 * Skema lama warisan Tokoku/RS: DIMST_ IMMST_ LBMST_ LBTXN_ RSMST_ RSTXN_
 * RSVIEW_ SCMST_ SCVIEW_ TKACC_ TKMST_ TKTXN_ TKVIEW_ (huruf modul + jenis).
 * Skema baru: huruf modul diganti prefix program (default SK), jenis
 * dipertahankan → SKMST_ SKTXN_ SKACC_ SKVIEW_.
 *
 * Tidak mengubah database. Hanya menulis berkas SQL ke folder --out
 * yang dijalankan manual lewat sqlplus/DBeaver setelah ditinjau.
 *
 *   php artisan siklik:ddl-rename
 *   php artisan siklik:ddl-rename --prefix=SK --out=database/sql/rename-siklik
 */
class SiklikDdlRename extends Command
{
    protected $signature = 'siklik:ddl-rename
        {--prefix=SK : Prefix program pengganti huruf modul}
        {--out=database/sql/rename-siklik : Folder output berkas SQL}
        {--hanya-view : Hanya tulis ulang 03_recreate_view.sql dari DB yang SUDAH di-rename (langkah 01 selesai)}';

    protected $description = 'Buat DDL rename tabel/view/constraint/index siklik ke prefix seragam (tanpa menyentuh DB)';

    /** Huruf modul lama yang dilebur. */
    private const MODUL_LAMA = ['DI', 'IM', 'LB', 'RS', 'SC', 'TK'];

    /** Jenis objek yang dipertahankan di tengah nama. */
    private const JENIS = ['MST', 'TXN', 'ACC', 'VIEW'];

    private const MAKS_NAMA = 30;

    private string $prefix;

    /** @var array<string,string> nama lama → nama baru (tabel + view) */
    private array $peta = [];

    /** @var array<string,string> nama tabel → dipakai untuk cek unik nama constraint/index */
    private array $namaTerpakai = [];

    public function handle(): int
    {
        $this->prefix = strtoupper(trim($this->option('prefix')));
        if (! preg_match('/^[A-Z][A-Z0-9]{0,3}$/', $this->prefix)) {
            $this->error('Prefix harus 1-4 huruf/angka, diawali huruf. Contoh: SK');

            return self::FAILURE;
        }

        $outDir = base_path($this->option('out'));
        File::ensureDirectoryExists($outDir);

        if ($this->option('hanya-view')) {
            return $this->hanyaView($outDir);
        }

        $tabel = array_map(fn ($r) => $r->table_name, DB::select('select table_name from user_tables order by 1'));
        $view = array_map(fn ($r) => $r->view_name, DB::select('select view_name from user_views order by 1'));

        $this->susunPeta(array_merge($tabel, $view));
        if ($this->peta === []) {
            $this->warn('Tidak ada objek yang cocok pola prefix lama.');

            return self::SUCCESS;
        }

        $bentrok = $this->cekBentrok(array_merge($tabel, $view));
        if ($bentrok !== []) {
            $this->error('Nama baru bentrok: '.implode(', ', $bentrok));

            return self::FAILURE;
        }

        $tabelDipeta = array_values(array_filter($tabel, fn ($t) => isset($this->peta[$t])));
        $viewDipeta = array_values(array_filter($view, fn ($v) => isset($this->peta[$v])));

        $constraint = $this->rencanaConstraint($tabelDipeta);
        $index = $this->rencanaIndex($tabelDipeta, $constraint);
        $teksView = $this->teksView(array_combine($viewDipeta, $viewDipeta));
        $urutanView = $this->urutkanView($viewDipeta);

        File::put("$outDir/peta_nama.csv", $this->csvPeta($tabelDipeta, $viewDipeta));
        File::put("$outDir/01_rename_tabel_view.sql", $this->sql01($tabelDipeta, $viewDipeta));
        File::put("$outDir/02_synonym_kompat_legacy.sql", $this->sql02($tabelDipeta, $viewDipeta));
        File::put("$outDir/03_recreate_view.sql", $this->sql03($urutanView, $teksView));
        File::put("$outDir/04_rename_constraint_index.sql", $this->sql04($constraint, $index));
        File::put("$outDir/05_drop_synonym_legacy.sql", $this->sql05($tabelDipeta, $viewDipeta));
        File::put("$outDir/99_rollback.sql", $this->sql99($tabelDipeta, $urutanView, $teksView, $constraint, $index));
        File::put("$outDir/README.md", $this->readme($tabelDipeta, $viewDipeta, $constraint, $index));

        $this->info(sprintf('Tabel  : %d', count($tabelDipeta)));
        $this->info(sprintf('View   : %d', count($viewDipeta)));
        $this->info(sprintf('Constr : %d', count($constraint)));
        $this->info(sprintf('Index  : %d', count($index)));
        $this->line("Output : $outDir");

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Peta nama
    // ------------------------------------------------------------------

    private function susunPeta(array $namaLama): void
    {
        $pola = '/^('.implode('|', self::MODUL_LAMA).')('.implode('|', self::JENIS).')_(.+)$/';
        foreach ($namaLama as $lama) {
            if (preg_match($pola, $lama, $m)) {
                $this->peta[$lama] = $this->prefix.$m[2].'_'.$m[3];
            }
        }
    }

    /** Nama baru tidak boleh sama satu sama lain, dan tidak boleh menimpa objek yang sudah ada. */
    private function cekBentrok(array $semuaObjek): array
    {
        $bentrok = [];
        $hitung = array_count_values($this->peta);
        foreach ($hitung as $baru => $n) {
            if ($n > 1) {
                $bentrok[] = "$baru (dari ".implode(', ', array_keys($this->peta, $baru)).')';
            }
        }
        $ada = array_flip($semuaObjek);
        foreach ($this->peta as $lama => $baru) {
            if (isset($ada[$baru]) && ! isset($this->peta[$baru])) {
                $bentrok[] = "$baru sudah ada di schema";
            }
            if (strlen($baru) > self::MAKS_NAMA) {
                $bentrok[] = "$baru lebih dari ".self::MAKS_NAMA.' karakter';
            }
        }

        return $bentrok;
    }

    /** Nama tabel tanpa prefix apa pun, dipakai sebagai akar nama constraint/index. */
    private function akar(string $tabelBaru): string
    {
        return preg_replace('/^'.$this->prefix.'(MST|TXN|ACC|VIEW)_/', '', $tabelBaru);
    }

    // ------------------------------------------------------------------
    // Constraint & index
    // ------------------------------------------------------------------

    /**
     * @return list<array{tabel:string,tabelBaru:string,lama:string,baru:string,tipe:string,indexIkut:bool}>
     */
    private function rencanaConstraint(array $tabel): array
    {
        // Oracle 10g belum punya LISTAGG → kolom dikelompokkan di PHP.
        $kolomPer = $this->kolomPerNama(
            'select constraint_name nama, column_name kolom, position urut from user_cons_columns order by 1, 3'
        );
        $rows = DB::select("
            select c.table_name, c.constraint_name, c.constraint_type, c.index_name
              from user_constraints c
             where c.constraint_type in ('P','R','U')
               and c.generated = 'USER NAME'
             order by c.table_name, c.constraint_type, c.constraint_name
        ");

        $hasil = [];
        foreach ($rows as $r) {
            if (! isset($this->peta[$r->table_name])) {
                continue;
            }
            $tabelBaru = $this->peta[$r->table_name];
            $kolom = $kolomPer[$r->constraint_name] ?? [];
            $akhiran = match ($r->constraint_type) {
                'P' => 'PK',
                'U' => 'UK',
                default => 'FK',
            };
            $baru = $this->namaUnik($this->akar($tabelBaru), $r->constraint_type === 'P' ? [] : $kolom, $akhiran);
            $hasil[] = [
                'tabel' => $r->table_name,
                'tabelBaru' => $tabelBaru,
                'lama' => $r->constraint_name,
                'baru' => $baru,
                'tipe' => $r->constraint_type,
                'indexIkut' => $r->index_name !== null && $r->index_name === $r->constraint_name,
            ];
        }

        return $hasil;
    }

    /**
     * Index non-sistem yang bukan pasangan constraint (pasangan PK/UK
     * ikut nama constraint-nya dan ditangani lewat indexIkut).
     *
     * @return list<array{tabel:string,tabelBaru:string,lama:string,baru:string}>
     */
    private function rencanaIndex(array $tabel, array $constraint): array
    {
        $sudah = [];
        foreach ($constraint as $c) {
            if ($c['indexIkut']) {
                $sudah[$c['lama']] = true;
            }
        }

        $kolomPer = $this->kolomPerNama(
            'select index_name nama, column_name kolom, column_position urut from user_ind_columns order by 1, 3'
        );
        $rows = DB::select("
            select i.table_name, i.index_name, i.index_type
              from user_indexes i
             where i.generated = 'N'
               and i.index_type not like 'LOB%'
             order by i.table_name, i.index_name
        ");

        $hasil = [];
        foreach ($rows as $r) {
            if (! isset($this->peta[$r->table_name]) || isset($sudah[$r->index_name])) {
                continue;
            }
            // Index milik constraint tapi namanya beda dari constraint: tetap diberi nama IX.
            $tabelBaru = $this->peta[$r->table_name];
            $kolom = $kolomPer[$r->index_name] ?? [];
            $kolom = array_map(fn ($k) => str_starts_with($k, 'SYS_NC') ? 'FN' : $k, $kolom);
            $baru = $this->namaUnik($this->akar($tabelBaru), $kolom, 'IX');
            $hasil[] = [
                'tabel' => $r->table_name,
                'tabelBaru' => $tabelBaru,
                'lama' => $r->index_name,
                'baru' => $baru,
            ];
        }

        return $hasil;
    }

    /** @return array<string,list<string>> nama constraint/index → daftar kolom urut posisi */
    private function kolomPerNama(string $sql): array
    {
        $hasil = [];
        foreach (DB::select($sql) as $r) {
            $hasil[$r->nama][] = $r->kolom;
        }

        return $hasil;
    }

    /**
     * Bentuk: AKAR_KOLOM1_KOLOM2_AKHIRAN, dipadatkan agar ≤ 30 karakter,
     * lalu diberi nomor bila masih bentrok.
     */
    private function namaUnik(string $akar, array $kolom, string $akhiran): string
    {
        $kolom = array_map(fn ($k) => preg_replace('/_ID$/', '', $k), $kolom);
        $tengah = $kolom === [] ? '' : '_'.implode('_', $kolom);
        $calon = $akar.$tengah.'_'.$akhiran;

        if (strlen($calon) > self::MAKS_NAMA) {
            $sisa = self::MAKS_NAMA - strlen('_'.$akhiran) - strlen($tengah);
            $akarPendek = $sisa >= 4 ? substr($akar, 0, $sisa) : substr($akar, 0, 4);
            $calon = $akarPendek.$tengah.'_'.$akhiran;
        }
        if (strlen($calon) > self::MAKS_NAMA) {
            $calon = substr($calon, 0, self::MAKS_NAMA - strlen('_'.$akhiran)).'_'.$akhiran;
        }

        $dasar = substr($calon, 0, -strlen('_'.$akhiran));
        $n = 1;
        while (isset($this->namaTerpakai[$calon])) {
            $n++;
            $ekor = '_'.$n.'_'.$akhiran;
            $calon = substr($dasar, 0, self::MAKS_NAMA - strlen($ekor)).$ekor;
        }
        $this->namaTerpakai[$calon] = true;

        return $calon;
    }

    // ------------------------------------------------------------------
    // View
    // ------------------------------------------------------------------

    /**
     * Teks SELECT + daftar kolom tiap view, dibaca dengan nama yang ADA di DB saat ini.
     * Daftar kolom wajib disertakan saat CREATE VIEW karena teks di dictionary tidak
     * memuatnya; view lama yang dibuat dengan kolom eksplisit punya ekspresi tanpa alias
     * (ORA-00998 kalau dibuat ulang tanpa daftar kolom).
     *
     * @param  array<string,string>  $namaDiDb  kunci peta → nama view yang ada di DB sekarang
     * @return array<string,array{teks:string,kolom:list<string>}>
     */
    private function teksView(array $namaDiDb): array
    {
        $hasil = [];
        foreach ($namaDiDb as $kunci => $sekarang) {
            $row = DB::selectOne('select text from user_views where view_name = ?', [$sekarang]);
            $kolom = array_map(
                fn ($r) => $r->column_name,
                DB::select('select column_name from user_tab_columns where table_name = ? order by column_id', [$sekarang])
            );
            $hasil[$kunci] = ['teks' => rtrim((string) ($row->text ?? '')), 'kolom' => $kolom];
        }

        return $hasil;
    }

    /** Ganti semua nama berpola lama di teks view dengan nama baru (aturan prefix, case-insensitive). */
    private function gantiNama(string $teks): string
    {
        $pola = '/\b('.implode('|', self::MODUL_LAMA).')('.implode('|', self::JENIS).')_([A-Za-z0-9_]+)\b/i';

        return preg_replace_callback($pola, function (array $m) {
            $prefix = ctype_upper($m[1][0]) ? $this->prefix : strtolower($this->prefix);

            return $prefix.$m[2].'_'.$m[3];
        }, $teks);
    }

    /** Satu pernyataan CREATE OR REPLACE VIEW lengkap dengan daftar kolom, terminator "/". */
    private function sqlView(string $nama, array $kolom, string $teks): string
    {
        $daftar = $kolom === [] ? '' : ' ('.implode(', ', array_map(fn ($k) => '"'.$k.'"', $kolom)).')';

        return sprintf("\nCREATE OR REPLACE VIEW %s%s AS\n%s\n/\n", $nama, $daftar, $teks);
    }

    /** Mode pasca-rename: tulis ulang 03 dari view yang sudah berprefix baru. */
    private function hanyaView(string $outDir): int
    {
        $view = array_map(
            fn ($r) => $r->view_name,
            DB::select("select view_name from user_views where view_name like ? order by 1", [$this->prefix.'VIEW_%'])
        );
        if ($view === []) {
            $this->warn('Tidak ada view berprefix '.$this->prefix.'VIEW_ di DB ini.');

            return self::FAILURE;
        }
        foreach ($view as $v) {
            $this->peta[$v] = $v;
        }
        $teks = $this->teksView(array_combine($view, $view));
        $urut = $this->urutkanView($view);
        File::put("$outDir/03_recreate_view.sql", $this->sql03($urut, $teks));
        $this->info(sprintf('View   : %d → 03_recreate_view.sql (mode --hanya-view)', count($view)));

        return self::SUCCESS;
    }

    /** Urutkan view supaya yang dirujuk view lain dibuat lebih dulu. */
    private function urutkanView(array $view): array
    {
        $dep = [];
        foreach (DB::select("
            select name, referenced_name
              from user_dependencies
             where type = 'VIEW' and referenced_type = 'VIEW'
        ") as $r) {
            $dep[$r->name][] = $r->referenced_name;
        }

        $set = array_flip($view);
        $urut = [];
        $tanda = [];
        $kunjungi = function (string $v) use (&$kunjungi, &$urut, &$tanda, $dep, $set) {
            if (isset($tanda[$v]) || ! isset($set[$v])) {
                return;
            }
            $tanda[$v] = true;
            foreach ($dep[$v] ?? [] as $d) {
                $kunjungi($d);
            }
            $urut[] = $v;
        };
        foreach ($view as $v) {
            $kunjungi($v);
        }

        return $urut;
    }

    // ------------------------------------------------------------------
    // Penulis SQL
    // ------------------------------------------------------------------

    private function kepala(string $judul, string $keterangan): string
    {
        $tgl = now()->format('Y-m-d H:i');

        return <<<SQL
        -- ============================================================
        -- $judul
        -- Dibuat  : php artisan siklik:ddl-rename --prefix={$this->prefix}  ($tgl)
        -- $keterangan
        -- ============================================================
        SET DEFINE OFF
        SET SQLBLANKLINES ON
        SET ECHO ON
        WHENEVER SQLERROR EXIT SQL.SQLCODE


        SQL;
    }

    private function sql01(array $tabel, array $view): string
    {
        $s = $this->kepala(
            'LANGKAH 1 — Rename tabel & view ke prefix '.$this->prefix,
            'Berhenti di error pertama. Kalau berhenti di tengah, jalankan 99_rollback.sql lalu perbaiki.'
        );
        $s .= "-- Tabel (".count($tabel).")\n";
        foreach ($tabel as $t) {
            $s .= sprintf("ALTER TABLE %s RENAME TO %s;\n", $t, $this->peta[$t]);
        }
        $s .= "\n-- View (".count($view).")\n";
        foreach ($view as $v) {
            $s .= sprintf("RENAME %s TO %s;\n", $v, $this->peta[$v]);
        }
        $s .= "\n-- Setelah ini teks view masih merujuk nama lama → INVALID.\n";
        $s .= "-- Jalankan 02 (synonym) supaya view bisa compile, ATAU langsung 03 (recreate).\n";

        return $s;
    }

    private function sql02(array $tabel, array $view): string
    {
        $s = $this->kepala(
            'LANGKAH 2 — Synonym nama lama → nama baru (kompatibilitas siklik-lite legacy)',
            'Opsional. Membuat nama lama tetap bisa dipakai aplikasi lama tanpa ubah kode. Cabut dengan 05.'
        );
        foreach (array_merge($tabel, $view) as $o) {
            $s .= sprintf("CREATE SYNONYM %s FOR %s;\n", $o, $this->peta[$o]);
        }
        $s .= "\n-- Compile ulang view yang INVALID (teksnya masih memakai nama lama, kini lewat synonym)\n";
        foreach ($view as $v) {
            $s .= sprintf("ALTER VIEW %s COMPILE;\n", $this->peta[$v]);
        }

        return $s;
    }

    private function sql03(array $urutanView, array $teksView): string
    {
        $s = $this->kepala(
            'LANGKAH 3 — Recreate view dengan nama baru di dalam teksnya',
            'Melepas ketergantungan view pada synonym. Wajib sebelum 05. Terminator "/" (teks view berisi komentar --), jalankan lewat SQL*Plus.'
        );
        foreach ($urutanView as $v) {
            $s .= $this->sqlView($this->peta[$v], $teksView[$v]['kolom'], $this->gantiNama($teksView[$v]['teks']));
        }
        $s .= "\n-- Pastikan tidak ada yang INVALID\n";
        $s .= "SELECT object_name, status FROM user_objects WHERE object_type = 'VIEW' AND status <> 'VALID'\n/\n";

        return $s;
    }

    private function sql04(array $constraint, array $index): string
    {
        $s = $this->kepala(
            'LANGKAH 4 — Rename constraint & index ke pola AKAR_KOLOM_PK/FK/UK/IX',
            'Opsional tapi disarankan. Tidak mengubah perilaku, hanya nama. Rollback ada di 99.'
        );
        $s .= "-- Constraint (".count($constraint).")\n";
        foreach ($constraint as $c) {
            $s .= sprintf("ALTER TABLE %s RENAME CONSTRAINT %s TO %s;\n", $c['tabelBaru'], $c['lama'], $c['baru']);
            if ($c['indexIkut']) {
                $s .= sprintf("ALTER INDEX %s RENAME TO %s;\n", $c['lama'], $c['baru']);
            }
        }
        $s .= "\n-- Index lepas (".count($index).")\n";
        foreach ($index as $i) {
            $s .= sprintf("ALTER INDEX %s RENAME TO %s;\n", $i['lama'], $i['baru']);
        }

        return $s;
    }

    private function sql05(array $tabel, array $view): string
    {
        $s = $this->kepala(
            'LANGKAH 5 — Drop synonym nama lama',
            'Jalankan HANYA setelah siklik-lite legacy tidak lagi menulis ke schema ini dan 03 sudah dijalankan.'
        );
        $s .= "-- Cek dulu tidak ada objek yang masih bergantung pada synonym:\n";
        $s .= "-- SELECT name, type, referenced_name FROM user_dependencies WHERE referenced_type = 'SYNONYM';\n\n";
        foreach (array_merge($tabel, $view) as $o) {
            $s .= sprintf("DROP SYNONYM %s;\n", $o);
        }

        return $s;
    }

    private function sql99(array $tabel, array $urutanView, array $teksView, array $constraint, array $index): string
    {
        $s = $this->kepala(
            'ROLLBACK — kembalikan semua nama ke semula',
            'Urutan: drop synonym → constraint/index → view → tabel. Baris yang objeknya belum diubah akan error; pakai WHENEVER CONTINUE.'
        );
        $s = str_replace('WHENEVER SQLERROR EXIT SQL.SQLCODE', 'WHENEVER SQLERROR CONTINUE', $s);

        $s .= "-- 1. Synonym\n";
        foreach (array_merge($tabel, $urutanView) as $o) {
            $s .= sprintf("DROP SYNONYM %s;\n", $o);
        }
        $s .= "\n-- 2. Constraint & index\n";
        foreach (array_reverse($index) as $i) {
            $s .= sprintf("ALTER INDEX %s RENAME TO %s;\n", $i['baru'], $i['lama']);
        }
        foreach (array_reverse($constraint) as $c) {
            if ($c['indexIkut']) {
                $s .= sprintf("ALTER INDEX %s RENAME TO %s;\n", $c['baru'], $c['lama']);
            }
            $s .= sprintf("ALTER TABLE %s RENAME CONSTRAINT %s TO %s;\n", $c['tabelBaru'], $c['baru'], $c['lama']);
        }
        $s .= "\n-- 3. View: rename balik lalu pulihkan teks asli\n";
        foreach ($urutanView as $v) {
            $s .= sprintf("RENAME %s TO %s;\n", $this->peta[$v], $v);
        }
        $s .= "\n-- 4. Tabel\n";
        foreach (array_reverse($tabel) as $t) {
            $s .= sprintf("ALTER TABLE %s RENAME TO %s;\n", $this->peta[$t], $t);
        }
        $s .= "\n-- 5. Teks view asli\n";
        foreach ($urutanView as $v) {
            $s .= $this->sqlView($v, $teksView[$v]['kolom'], $teksView[$v]['teks']);
        }

        return $s;
    }

    private function csvPeta(array $tabel, array $view): string
    {
        $s = "jenis,nama_lama,nama_baru\n";
        foreach ($tabel as $t) {
            $s .= "TABLE,$t,{$this->peta[$t]}\n";
        }
        foreach ($view as $v) {
            $s .= "VIEW,$v,{$this->peta[$v]}\n";
        }

        return $s;
    }

    private function readme(array $tabel, array $view, array $constraint, array $index): string
    {
        $p = $this->prefix;
        $nT = count($tabel);
        $nV = count($view);
        $nC = count($constraint);
        $nI = count($index);
        $contoh = '';
        foreach (array_slice($tabel, 0, 6) as $t) {
            $contoh .= sprintf("| %-28s | %-28s |\n", $t, $this->peta[$t]);
        }

        return <<<MD
        # Rename prefix tabel siklik → `{$p}MST_ / {$p}TXN_ / {$p}ACC_ / {$p}VIEW_`

        Dibuat otomatis oleh `php artisan siklik:ddl-rename --prefix={$p}`.
        Jangan edit berkas SQL di folder ini secara manual; ubah generator lalu jalankan ulang.

        ## Aturan penamaan

        Huruf modul lama (`DI IM LB RS SC TK`) diganti `{$p}`, jenis objek (`MST TXN ACC VIEW`) tetap,
        sisa nama tidak berubah. Contoh:

        | Lama                         | Baru                         |
        |------------------------------|------------------------------|
        {$contoh}
        Constraint & index diberi nama baru berpola `AKAR_KOLOM_PK` / `AKAR_KOLOM_FK` / `AKAR_KOLOM_UK` /
        `AKAR_KOLOM_IX`, dengan `AKAR` = nama tabel tanpa prefix (mis. `PASIENS_KEC_FK`). Nama dipadatkan
        agar ≤ 30 karakter (batas Oracle 10g).

        Peta lengkap: `peta_nama.csv`.

        ## Cakupan

        | Objek      | Jumlah |
        |------------|--------|
        | Tabel      | {$nT} |
        | View       | {$nV} |
        | Constraint | {$nC} |
        | Index      | {$nI} |

        Tidak disentuh: tabel sistem Laravel/Spatie, `PASIEN`, `REF_BPJS_TABLE`, `REFERENSI_MOBILEJKN_BPJS`,
        `WEB_LOG_STATUS`, `INSTALL_TOKOKU`, semua sequence, semua trigger.

        ## Urutan eksekusi

        0. **Jalankan generator ini terhadap DB TARGET** (`DB_CONNECTION` mengarah ke schema yang akan di-rename)
           tepat sebelum eksekusi, lalu tinjau `peta_nama.csv`. Daftar objek dibaca dari data dictionary DB yang
           terhubung, jadi hasil dari DB lokal bisa kurang lengkap (mis. tabel fitur lanjutan `TKTXN_SOWHSNON`).
        1. **Backup** schema (`expdp` atau minimal `exp`), lalu hentikan aplikasi yang menulis ke schema ini.
        2. `01_rename_tabel_view.sql` — rename tabel & view. Setelah ini semua view INVALID.
        3. `02_synonym_kompat_legacy.sql` — synonym nama lama → baru, lalu compile view.
           Dengan ini **siklik-lite legacy tetap jalan tanpa ubah kode**.
        4. `03_recreate_view.sql` — recreate view dengan nama baru di dalam teksnya.
        5. `04_rename_constraint_index.sql` — opsional, hanya kosmetik nama.
        6. Deploy siklik-php82. Kode, `database/sql/install_bundle*.sql`, docs, dan skill sudah memakai nama baru
           sejak 11 Sep 2026, jadi versi itu **tidak jalan** di schema yang belum di-rename (ORA-00942).
           Bundle instalasi untuk schema baru dijalankan SETELAH rename ini.
        7. **Nanti**, setelah legacy pensiun: `05_drop_synonym_legacy.sql`.

        Kalau ada yang gagal di tengah: `99_rollback.sql` (pakai `WHENEVER SQLERROR CONTINUE`,
        baris yang objeknya belum diubah memang akan error dan boleh diabaikan).

        ## Verifikasi

        ```sql
        SELECT object_type, status, COUNT(*) FROM user_objects
         WHERE object_type IN ('TABLE','VIEW','SYNONYM') GROUP BY object_type, status;
        SELECT table_name FROM user_tables WHERE REGEXP_LIKE(table_name, '^(DI|IM|LB|RS|SC|TK)(MST|TXN|ACC|VIEW)_');
        -- keduanya: tidak ada INVALID, dan query kedua kosong
        ```

        ## Catatan Oracle 10g

        - `ALTER TABLE ... RENAME TO` mempertahankan data, constraint, index, grant, dan FK dari tabel lain.
        - `RENAME view TO ...` sah untuk view & synonym privat. Teks view TIDAK ikut berubah → wajib langkah 03.
        - Sequence tidak diprefix dan dipakai lewat `.nextval` di kode, jadi sengaja tidak di-rename di tahap ini.
        MD;
    }
}
