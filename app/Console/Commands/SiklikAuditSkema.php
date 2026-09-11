<?php

namespace App\Console\Commands;

use App\Support\Skema\KamusData;
use App\Support\Skema\ModulTabel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Audit skema Oracle siklik (Tahap 3 pembersihan) → laporan + DDL, TANPA mengubah DB.
 *
 * Yang diperiksa (semua dari data dictionary DB yang terhubung):
 *  1. Relasi implisit (kolom = PK tabel lain tanpa FK): kecocokan tipe + jumlah baris yatim
 *     → DDL ADD CONSTRAINT (VALIDATE bila bersih, NOVALIDATE bila ada yatim) + index kolom FK.
 *  2. FK terdeklarasi yang kolom pertamanya belum ber-index → CREATE INDEX (cegah lock tabel).
 *  3. Tabel tanpa PK → laporan.
 *  4. Objek PL/SQL INVALID → backup DDL (DBMS_METADATA) + DROP.
 *  5. Sequence yang tidak dirujuk kode siklik-php82, siklik-lite, maupun PL/SQL/trigger → DROP (rollback: CREATE ulang).
 *  6. Tabel yang tidak dirujuk kode mana pun → laporan saja.
 *
 *   php artisan siklik:audit-skema
 *   php artisan siklik:audit-skema --out=database/sql/skema-tahap3 --legacy=/home/avro/Desktop/LARAVEL/siklik-lite
 */
class SiklikAuditSkema extends Command
{
    protected $signature = 'siklik:audit-skema
        {--out=database/sql/skema-tahap3 : Folder output DDL}
        {--laporan=docs/audit-skema.md : Berkas laporan markdown}
        {--legacy=/home/avro/Desktop/LARAVEL/siklik-lite : Repo siklik-lite untuk cek pemakaian nama objek}';

    protected $description = 'Audit relasi implisit, FK tanpa index, tabel tanpa PK, objek INVALID, sequence/tabel tak terpakai → laporan + DDL (tanpa menyentuh DB)';

    private const MAKS_NAMA = 30;

    /** Tabel yang sengaja tidak diberi FK (warisan / bukan milik aplikasi). */
    private const TANPA_FK = ['INSTALL_TOKOKU'];

    /** Kolom yang kebetulan senama dengan PK master tetapi isinya teks bebas — bukan relasi. */
    private const BUKAN_RELASI = [
        'SKTXN_RJOBATRACIKANS.CATATAN' => 'teks signa bebas; SKMST_SIGNA_CATATANS hanya LOV saran',
    ];

    private array $namaTerpakai = [];

    public function handle(): int
    {
        $out = base_path($this->option('out'));
        File::ensureDirectoryExists($out);

        $tipe = $this->tipeKolom();
        $implisit = $this->auditImplisit($tipe);
        $fkTanpaIndex = $this->auditFkTanpaIndex();
        $tanpaPk = array_map(fn ($r) => $r->table_name, DB::select("
            select t.table_name from user_tables t
             where not exists (select 1 from user_constraints c where c.table_name = t.table_name and c.constraint_type = 'P')
             order by 1"));
        $invalid = $this->auditInvalid();
        $sequence = $this->auditSequence();
        $tabelTakTerpakai = $this->auditTabelTakTerpakai();

        File::put("$out/01_fk_implisit.sql", $this->sqlFk($implisit, $fkTanpaIndex));
        File::put("$out/02_drop_objek_invalid.sql", $this->sqlDropInvalid($invalid));
        File::put("$out/_backup_objek_invalid.sql", $this->sqlBackupInvalid($invalid));
        File::put("$out/03_drop_sequence_tak_terpakai.sql", $this->sqlDropSequence($sequence));
        File::put("$out/99_rollback.sql", $this->sqlRollback($implisit, $fkTanpaIndex, $invalid, $sequence));
        File::put(base_path($this->option('laporan')), $this->laporan($implisit, $fkTanpaIndex, $tanpaPk, $invalid, $sequence, $tabelTakTerpakai));

        $this->info(sprintf('Implisit: %d (bersih %d, yatim %d, dilewati %d)', count($implisit),
            count(array_filter($implisit, fn ($r) => $r['keputusan'] === 'VALIDATE')),
            count(array_filter($implisit, fn ($r) => $r['keputusan'] === 'NOVALIDATE')),
            count(array_filter($implisit, fn ($r) => $r['keputusan'] === 'LEWATI'))));
        $this->info(sprintf('FK tanpa index: %d | tabel tanpa PK: %d | objek INVALID: %d | sequence drop: %d/%d | tabel tak terpakai: %d',
            count($fkTanpaIndex), count($tanpaPk), count($invalid),
            count(array_filter($sequence, fn ($s) => $s['drop'])), count($sequence), count($tabelTakTerpakai)));
        $this->line('Output: '.$this->option('out').' + '.$this->option('laporan'));

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ audit

    /** @return array<string,array<string,string>> tabel → kolom → tipe ringkas (NUMBER/VARCHAR2/...) */
    private function tipeKolom(): array
    {
        $hasil = [];
        foreach (DB::select('select table_name, column_name, data_type from user_tab_columns') as $r) {
            $hasil[$r->table_name][$r->column_name] = $r->data_type;
        }

        return $hasil;
    }

    private function auditImplisit(array $tipe): array
    {
        $hasil = [];
        foreach (KamusData::relasiImplisit() as $r) {
            $anak = $r['anak'];
            $kolom = $r['kolom'];
            $induk = $r['induk'];
            $pkInduk = DB::selectOne("
                select cc.column_name from user_constraints c join user_cons_columns cc on cc.constraint_name = c.constraint_name
                 where c.table_name = ? and c.constraint_type = 'P'", [$induk])->column_name ?? $kolom;

            $baris = [
                'anak' => $anak, 'kolom' => $kolom, 'induk' => $induk, 'kolomInduk' => $pkInduk,
                'tipeAnak' => $tipe[$anak][$kolom] ?? '?', 'tipeInduk' => $tipe[$induk][$pkInduk] ?? '?',
                'total' => 0, 'yatim' => 0, 'keputusan' => 'LEWATI', 'alasan' => '',
                'constraint' => $this->namaUnik($this->akar($anak), [$kolom], 'FK'),
                'index' => null,
            ];

            if (in_array($anak, self::TANPA_FK, true)) {
                $baris['alasan'] = 'tabel warisan di luar aplikasi';
                $hasil[] = $baris;
                continue;
            }
            if (isset(self::BUKAN_RELASI["$anak.$kolom"])) {
                $baris['alasan'] = self::BUKAN_RELASI["$anak.$kolom"];
                $hasil[] = $baris;
                continue;
            }
            if ($baris['tipeAnak'] !== $baris['tipeInduk']) {
                $baris['alasan'] = 'tipe kolom beda ('.$baris['tipeAnak'].' vs '.$baris['tipeInduk'].')';
                $hasil[] = $baris;
                continue;
            }

            $baris['total'] = (int) DB::selectOne("select count(*) c from $anak where $kolom is not null")->c;
            $baris['yatim'] = (int) DB::selectOne("
                select count(*) c from $anak a where a.$kolom is not null
                   and not exists (select 1 from $induk i where i.$pkInduk = a.$kolom)")->c;
            if ($baris['total'] > 0 && $baris['yatim'] === $baris['total']) {
                $baris['alasan'] = 'SEMUA baris yatim — kemungkinan bukan relasi nyata, periksa manual';
                $hasil[] = $baris;
                continue;
            }
            $baris['keputusan'] = $baris['yatim'] === 0 ? 'VALIDATE' : 'NOVALIDATE';
            $baris['alasan'] = $baris['yatim'] === 0 ? 'semua baris cocok' : 'ada baris yatim, hanya baris baru yang dijaga';
            if (! $this->adaIndexKolomPertama($anak, $kolom)) {
                $baris['index'] = $this->namaUnik($this->akar($anak), [$kolom], 'IX');
            }
            $hasil[] = $baris;
        }

        return $hasil;
    }

    private function auditFkTanpaIndex(): array
    {
        $hasil = [];
        foreach (DB::select("
            select c.table_name, c.constraint_name, cc.column_name
              from user_constraints c
              join user_cons_columns cc on cc.constraint_name = c.constraint_name and cc.position = 1
             where c.constraint_type = 'R'
             order by 1, 2") as $r) {
            if (ModulTabel::dari($r->table_name) === 'Sistem Laravel' || $this->adaIndexKolomPertama($r->table_name, $r->column_name)) {
                continue;
            }
            $hasil[] = [
                'tabel' => $r->table_name, 'kolom' => $r->column_name, 'constraint' => $r->constraint_name,
                'index' => $this->namaUnik($this->akar($r->table_name), [$r->column_name], 'IX'),
            ];
        }

        return $hasil;
    }

    private function adaIndexKolomPertama(string $tabel, string $kolom): bool
    {
        return (int) DB::selectOne('select count(*) c from user_ind_columns where table_name = ? and column_name = ? and column_position = 1', [$tabel, $kolom])->c > 0;
    }

    /**
     * Objek PL/SQL INVALID. Dicoba COMPILE dulu (benign): yang pulih VALID tidak disentuh,
     * TRIGGER tidak pernah di-drop (hanya compile), yang masih dirujuk kode dilaporkan tapi
     * tidak di-drop. Sisanya = kandidat drop dengan backup DDL.
     */
    private function auditInvalid(): array
    {
        $hasil = [];
        foreach (DB::select("
            select object_type, object_name from user_objects
             where status = 'INVALID' and object_type in ('FUNCTION','PROCEDURE','PACKAGE','PACKAGE BODY','TRIGGER','TYPE')
             order by 1, 2") as $r) {
            try {
                DB::unprepared($r->object_type === 'PACKAGE BODY'
                    ? "ALTER PACKAGE {$r->object_name} COMPILE BODY"
                    : "ALTER {$r->object_type} {$r->object_name} COMPILE");
            } catch (\Throwable $e) {
            }
            $status = DB::selectOne('select status from user_objects where object_name = ? and object_type = ?', [$r->object_name, $r->object_type])->status ?? 'INVALID';
            $dirujuk = $this->dirujukKode($r->object_name, base_path())
                || ($this->option('legacy') && is_dir($this->option('legacy')) && $this->dirujukKode($r->object_name, $this->option('legacy')));
            if ($status === 'VALID') {
                $this->line("  compile pulih: {$r->object_type} {$r->object_name}");
                continue;
            }
            if ($r->object_type === 'TRIGGER' || $dirujuk) {
                $this->warn("  TIDAK di-drop ({$r->object_type} {$r->object_name}): ".($dirujuk ? 'masih dirujuk kode' : 'trigger hanya di-compile'));
                $hasil[] = ['tipe' => $r->object_type, 'nama' => $r->object_name, 'ddl' => '', 'drop' => false,
                    'alasan' => $dirujuk ? 'masih dirujuk kode' : 'trigger: compile saja, jangan drop'];
                continue;
            }
            $ddl = '';
            try {
                $row = DB::selectOne("select dbms_metadata.get_ddl(?, ?) ddl from dual", [str_replace(' ', '_', $r->object_type), $r->object_name]);
                $ddl = trim((string) ($row->ddl ?? ''));
            } catch (\Throwable $e) {
                $ddl = '-- gagal mengambil DDL: '.$e->getMessage();
            }
            $hasil[] = ['tipe' => $r->object_type, 'nama' => $r->object_name, 'ddl' => $ddl, 'drop' => true, 'alasan' => 'INVALID, tidak bisa compile, tidak dirujuk kode'];
        }

        return $hasil;
    }

    private function auditSequence(): array
    {
        $rows = DB::select('select sequence_name, last_number, increment_by, min_value, max_value, cache_size, cycle_flag from user_sequences order by 1');
        $sumberPlsql = strtoupper(implode("\n", array_map(fn ($r) => $r->text, DB::select('select text from user_source'))));
        $sumberTrigger = strtoupper(implode("\n", array_map(fn ($r) => (string) $r->trigger_body, DB::select('select trigger_body from user_triggers'))));

        $hasil = [];
        foreach ($rows as $r) {
            $nama = $r->sequence_name;
            $dipakai = [];
            if ($this->dirujukKode($nama, base_path())) {
                $dipakai[] = 'siklik-php82';
            }
            if ($this->option('legacy') && is_dir($this->option('legacy')) && $this->dirujukKode($nama, $this->option('legacy'))) {
                $dipakai[] = 'siklik-lite';
            }
            if (str_contains($sumberPlsql, $nama) || str_contains($sumberTrigger, $nama)) {
                $dipakai[] = 'PL/SQL';
            }
            $sistem = (bool) preg_match('/^(USERS|ROLES|PERMISSIONS|MIGRATIONS|FAILED_JOBS|JOBS|PERSONAL_ACCESS_TOKENS)_/', $nama);
            if ($sistem) {
                $dipakai[] = 'Laravel';
            }
            $hasil[] = [
                'nama' => $nama, 'last' => (int) $r->last_number, 'inc' => (int) $r->increment_by,
                'min' => (int) $r->min_value, 'max' => $r->max_value, 'cache' => (int) $r->cache_size, 'cycle' => $r->cycle_flag,
                'dipakai' => $dipakai,
                // Konservatif: hanya yang tak dirujuk siapa pun DAN belum pernah dipakai (last_number 1)
                // atau sisa skema contoh Oracle (DEMO_). Yang pernah dipakai dilaporkan untuk tinjauan manual.
                'drop' => $dipakai === [] && ((int) $r->last_number <= 1 || str_starts_with($nama, 'DEMO_')),
            ];
        }

        return $hasil;
    }

    private function auditTabelTakTerpakai(): array
    {
        $hasil = [];
        foreach (KamusData::tabel() as $t) {
            if ($t['jenis'] !== 'TABLE' || $t['modul'] === 'Sistem Laravel') {
                continue;
            }
            $dipakai = [];
            if ($this->dirujukKode($t['nama'], base_path())) {
                $dipakai[] = 'siklik-php82';
            }
            if ($this->option('legacy') && is_dir($this->option('legacy')) && $this->dirujukKode($t['nama'], $this->option('legacy'))) {
                $dipakai[] = 'siklik-lite';
            }
            if ((int) DB::selectOne('select count(*) c from user_dependencies where referenced_name = ? and referenced_type = ?', [$t['nama'], 'TABLE'])->c > 0) {
                $dipakai[] = 'view/PL-SQL';
            }
            if ($dipakai === []) {
                $baris = (int) DB::selectOne('select count(*) c from '.$t['nama'])->c;
                $hasil[] = ['nama' => $t['nama'], 'modul' => $t['modul'], 'baris' => $baris];
            }
        }

        return $hasil;
    }

    /** Nama objek dirujuk di berkas kode (php/blade/sql/py/js) di bawah folder, case-insensitive, batas kata. */
    private function dirujukKode(string $nama, string $folder): bool
    {
        // Nama lama (prefix RS/TK/...) di siklik-lite tetap terhitung: cocokkan juga versi tanpa prefix modul.
        $pola = '\b'.preg_quote($nama, '/').'\b';
        if (preg_match('/^SK(MST|TXN|ACC|VIEW)_(.+)$/', $nama, $m)) {
            $pola = '\b(DI|IM|LB|RS|SC|TK|SK)'.$m[1].'_'.preg_quote($m[2], '/').'\b';
        }
        $cmd = sprintf(
            'grep -rIliE %s %s --include=*.php --include=*.sql --include=*.py --include=*.js --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=storage --exclude-dir=public --exclude-dir=skema-tahap3 --exclude-dir=rename-siklik --exclude-dir=_dev 2>/dev/null | head -1',
            escapeshellarg($pola), escapeshellarg($folder)
        );

        return trim((string) shell_exec($cmd)) !== '';
    }

    // ------------------------------------------------------------------ nama

    private function akar(string $tabel): string
    {
        return preg_replace('/^SK(MST|TXN|ACC|VIEW)_/', '', $tabel);
    }

    private function namaUnik(string $akar, array $kolom, string $akhiran): string
    {
        $kolom = array_map(fn ($k) => preg_replace('/_ID$/', '', $k), $kolom);
        $tengah = $kolom === [] ? '' : '_'.implode('_', $kolom);
        $calon = $akar.$tengah.'_'.$akhiran;
        if (strlen($calon) > self::MAKS_NAMA) {
            $sisa = self::MAKS_NAMA - strlen('_'.$akhiran) - strlen($tengah);
            $calon = substr($akar, 0, max(4, $sisa)).$tengah.'_'.$akhiran;
        }
        if (strlen($calon) > self::MAKS_NAMA) {
            $calon = substr($calon, 0, self::MAKS_NAMA - strlen('_'.$akhiran)).'_'.$akhiran;
        }
        $ada = (int) DB::selectOne('select count(*) c from user_objects where object_name = ? union all select count(*) from user_constraints where constraint_name = ?', [$calon, $calon])->c;
        $dasar = substr($calon, 0, -strlen('_'.$akhiran));
        $n = 1;
        while (isset($this->namaTerpakai[$calon]) || $ada > 0) {
            $n++;
            $ekor = '_'.$n.'_'.$akhiran;
            $calon = substr($dasar, 0, self::MAKS_NAMA - strlen($ekor)).$ekor;
            $ada = (int) DB::selectOne('select count(*) c from user_objects where object_name = ?', [$calon])->c;
        }
        $this->namaTerpakai[$calon] = true;

        return $calon;
    }

    // ------------------------------------------------------------------ SQL

    private function kepala(string $judul, string $ket, string $errMode = 'EXIT SQL.SQLCODE'): string
    {
        return "-- ============================================================\n-- $judul\n-- Dibuat  : php artisan siklik:audit-skema (".now()->format('Y-m-d H:i').")\n-- $ket\n-- ============================================================\nSET DEFINE OFF\nSET SQLBLANKLINES ON\nSET ECHO ON\nWHENEVER SQLERROR $errMode\n\n";
    }

    private function sqlFk(array $implisit, array $fkTanpaIndex): string
    {
        $s = $this->kepala('TAHAP 3 / 1 — Deklarasi FK relasi implisit + index kolom FK',
            'VALIDATE = semua baris lama cocok. NOVALIDATE = ada baris yatim; hanya baris baru/ubah yang dijaga (legacy siklik-lite ikut terjaga).');
        foreach ($implisit as $r) {
            if ($r['keputusan'] === 'LEWATI') {
                $s .= sprintf("-- LEWATI %s.%s → %s : %s\n", $r['anak'], $r['kolom'], $r['induk'], $r['alasan']);
                continue;
            }
            $s .= sprintf("-- %s.%s → %s.%s  (%d baris, %d yatim)\n", $r['anak'], $r['kolom'], $r['induk'], $r['kolomInduk'], $r['total'], $r['yatim']);
            if ($r['index']) {
                $s .= sprintf("CREATE INDEX %s ON %s (%s);\n", $r['index'], $r['anak'], $r['kolom']);
            }
            $s .= sprintf("ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ENABLE %s;\n\n",
                $r['anak'], $r['constraint'], $r['kolom'], $r['induk'], $r['kolomInduk'], $r['keputusan']);
        }
        $s .= "-- Index untuk FK yang sudah ada tapi kolomnya belum ber-index (cegah lock tabel anak saat induk diubah)\n";
        // Komentar di baris SENDIRI: SQL*Plus tidak mengeksekusi baris yang terminator ';'-nya diikuti teks.
        foreach ($fkTanpaIndex as $i) {
            $s .= sprintf("-- %s\nCREATE INDEX %s ON %s (%s);\n", $i['constraint'], $i['index'], $i['tabel'], $i['kolom']);
        }

        return $s;
    }

    private function sqlDropInvalid(array $invalid): string
    {
        $s = $this->kepala('TAHAP 3 / 2 — Drop objek PL/SQL INVALID (warisan bridging RS, sudah tidak bisa dieksekusi)',
            'Backup DDL: _backup_objek_invalid.sql. Jalankan HANYA setelah dipastikan tidak ada yang memakainya.', 'CONTINUE');
        foreach ($invalid as $o) {
            if ($o['drop']) {
                $s .= sprintf("DROP %s %s;\n", $o['tipe'], $o['nama']);
            } else {
                $s .= sprintf("-- TIDAK di-drop: %s %s (%s)\n", $o['tipe'], $o['nama'], $o['alasan']);
            }
        }

        return $s;
    }

    private function sqlBackupInvalid(array $invalid): string
    {
        $s = "-- Backup DDL objek INVALID sebelum di-drop (dbms_metadata.get_ddl) — untuk rollback.\n-- Jalankan tiap blok diakhiri baris '/'.\n\n";
        foreach ($invalid as $o) {
            if ($o['drop']) {
                $s .= "-- ==== {$o['tipe']} {$o['nama']} ====\n".$o['ddl']."\n/\n\n";
            }
        }

        return $s;
    }

    private function sqlDropSequence(array $sequence): string
    {
        $s = $this->kepala('TAHAP 3 / 3 — Drop sequence yang tidak dirujuk kode/PL-SQL/trigger',
            'Nilai terakhir tercatat di 99_rollback.sql untuk CREATE ulang.', 'CONTINUE');
        foreach ($sequence as $q) {
            if ($q['drop']) {
                $s .= sprintf("-- last_number %d\nDROP SEQUENCE %s;\n", $q['last'], $q['nama']);
            }
        }

        return $s;
    }

    private function sqlRollback(array $implisit, array $fkTanpaIndex, array $invalid, array $sequence): string
    {
        $s = $this->kepala('ROLLBACK TAHAP 3', 'Baris untuk objek yang belum sempat diubah memang akan error.', 'CONTINUE');
        $s .= "-- 1. FK & index\n";
        foreach ($implisit as $r) {
            if ($r['keputusan'] === 'LEWATI') {
                continue;
            }
            $s .= sprintf("ALTER TABLE %s DROP CONSTRAINT %s;\n", $r['anak'], $r['constraint']);
            if ($r['index']) {
                $s .= sprintf("DROP INDEX %s;\n", $r['index']);
            }
        }
        foreach ($fkTanpaIndex as $i) {
            $s .= sprintf("DROP INDEX %s;\n", $i['index']);
        }
        $s .= "\n-- 2. Sequence\n";
        foreach ($sequence as $q) {
            if ($q['drop']) {
                $s .= sprintf("CREATE SEQUENCE %s START WITH %d INCREMENT BY %d MINVALUE %d MAXVALUE %s %s %s;\n",
                    $q['nama'], max($q['last'], $q['min']), $q['inc'], $q['min'], $q['max'],
                    $q['cache'] > 0 ? 'CACHE '.$q['cache'] : 'NOCACHE', $q['cycle'] === 'Y' ? 'CYCLE' : 'NOCYCLE');
            }
        }
        $s .= "\n-- 3. Objek PL/SQL: jalankan _backup_objek_invalid.sql\n";

        return $s;
    }

    // ------------------------------------------------------------------ laporan

    private function laporan(array $implisit, array $fkTanpaIndex, array $tanpaPk, array $invalid, array $sequence, array $tabelTakTerpakai): string
    {
        $m = [];
        $m[] = '# Audit Skema Oracle siklik — Tahap 3';
        $m[] = '';
        $m[] = 'Dibuat otomatis `php artisan siklik:audit-skema` pada '.now()->format('d/m/Y H:i').' dari DB yang terhubung. DDL: `'.$this->option('out').'/`.';
        $m[] = '';
        $m[] = '## 1. Relasi implisit → FK';
        $m[] = '';
        $m[] = '| Anak.kolom | Induk | Tipe | Baris | Yatim | Keputusan | Constraint | Index baru |';
        $m[] = '|---|---|---|---|---|---|---|---|';
        foreach ($implisit as $r) {
            $m[] = sprintf('| `%s.%s` | `%s.%s` | %s | %d | %d | **%s** — %s | `%s` | %s |',
                $r['anak'], $r['kolom'], $r['induk'], $r['kolomInduk'], $r['tipeAnak'], $r['total'], $r['yatim'],
                $r['keputusan'], $r['alasan'], $r['keputusan'] === 'LEWATI' ? '-' : $r['constraint'], $r['index'] ? '`'.$r['index'].'`' : '-');
        }
        $m[] = '';
        $m[] = '## 2. FK terdeklarasi tanpa index → index baru';
        $m[] = '';
        $m[] = '| Tabel.kolom | Constraint | Index |';
        $m[] = '|---|---|---|';
        foreach ($fkTanpaIndex as $i) {
            $m[] = sprintf('| `%s.%s` | `%s` | `%s` |', $i['tabel'], $i['kolom'], $i['constraint'], $i['index']);
        }
        $m[] = '';
        $m[] = '## 3. Tabel tanpa primary key (laporan saja)';
        $m[] = '';
        foreach ($tanpaPk as $t) {
            $m[] = '- `'.$t.'` — '.ModulTabel::dari($t);
        }
        $m[] = '';
        $m[] = '## 4. Objek PL/SQL INVALID → drop (backup di `_backup_objek_invalid.sql`)';
        $m[] = '';
        foreach ($invalid as $o) {
            $m[] = '- '.$o['tipe'].' `'.$o['nama'].'` — '.($o['drop'] ? '**DROP**' : 'pertahankan').' ('.$o['alasan'].')';
        }
        $m[] = '';
        $m[] = '## 5. Sequence';
        $m[] = '';
        $m[] = '| Sequence | last_number | Dipakai oleh | Keputusan |';
        $m[] = '|---|---|---|---|';
        foreach ($sequence as $q) {
            $ket = $q['drop'] ? '**DROP**' : ($q['dipakai'] === [] ? 'pertahankan — pernah dipakai, tidak dirujuk lagi, tinjau manual' : 'pertahankan');
            $m[] = sprintf('| `%s` | %d | %s | %s |', $q['nama'], $q['last'], $q['dipakai'] === [] ? '-' : implode(', ', $q['dipakai']), $ket);
        }
        $m[] = '';
        $m[] = '## 6. Tabel yang tidak dirujuk kode mana pun (laporan saja, TIDAK di-drop)';
        $m[] = '';
        $m[] = '| Tabel | Modul | Baris |';
        $m[] = '|---|---|---|';
        foreach ($tabelTakTerpakai as $t) {
            $m[] = sprintf('| `%s` | %s | %d |', $t['nama'], $t['modul'], $t['baris']);
        }
        $m[] = '';
        $m[] = '## Urutan eksekusi';
        $m[] = '';
        $m[] = '1. Backup schema (`exp`). 2. `01_fk_implisit.sql`. 3. `02_drop_objek_invalid.sql` (setelah `_backup_objek_invalid.sql` disimpan). 4. `03_drop_sequence_tak_terpakai.sql`. Rollback: `99_rollback.sql`.';
        $m[] = 'Setelah itu: `php artisan siklik:dok-tabel` dan regenerasi dump `database/sql/_dev/`.';
        $m[] = '';

        return implode("\n", $m);
    }
}
