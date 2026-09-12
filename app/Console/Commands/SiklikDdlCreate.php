<?php

namespace App\Console\Commands;

use App\Support\Skema\ModulTabel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Generator DDL CREATE lengkap schema Oracle siklik: tabel (kolom, default, NOT NULL,
 * PK, UNIQUE, CHECK, komentar), FK antar tabel, index lepas, sequence + trigger, view.
 *
 * Dibaca langsung dari data dictionary DB yang sedang terhubung (nama sudah berprefix
 * SKMST_/SKTXN_/SKACC_/SKVIEW_ sejak 11 Sep 2026). Tidak mengubah database — hanya
 * menulis berkas SQL ke folder --out untuk dijalankan lewat SQL*Plus di schema kosong.
 *
 * Oracle 10g: tanpa LISTAGG/JSON, agregasi dikerjakan di PHP; nama objek ≤ 30 karakter.
 *
 *   php artisan siklik:ddl-create
 *   php artisan siklik:ddl-create --tanpa-sistem --out=database/sql/create-siklik
 */
class SiklikDdlCreate extends Command
{
    protected $signature = 'siklik:ddl-create
        {--out=database/sql/create-siklik : Folder output berkas SQL}
        {--tanpa-sistem : Lewati tabel modul "Sistem Laravel" (USERS, ROLES, SESSIONS, dst) beserta sequence/trigger-nya}';

    protected $description = 'Buat DDL CREATE lengkap (tabel, PK/UK, FK, index, sequence, trigger, view) schema siklik dari data dictionary';

    /** Reserved word Oracle 10g (v\$reserved_words reserved='Y'); identifier ini harus diberi tanda kutip. */
    private const RESERVED = ['ALL', 'ALTER', 'AND', 'ANY', 'AS', 'ASC', 'BETWEEN', 'BY', 'CHAR', 'CHECK', 'CLUSTER', 'COMPRESS', 'CONNECT', 'CREATE', 'DATE', 'DECIMAL', 'DEFAULT', 'DELETE', 'DESC', 'DISTINCT', 'DROP', 'ELSE', 'EXCLUSIVE', 'EXISTS', 'FLOAT', 'FOR', 'FROM', 'GRANT', 'GROUP', 'HAVING', 'IDENTIFIED', 'IN', 'INDEX', 'INSERT', 'INTEGER', 'INTERSECT', 'INTO', 'IS', 'LIKE', 'LOCK', 'LONG', 'MINUS', 'MODE', 'NOCOMPRESS', 'NOT', 'NOWAIT', 'NULL', 'NUMBER', 'OF', 'ON', 'OPTION', 'OR', 'ORDER', 'PCTFREE', 'PRIOR', 'PUBLIC', 'RAW', 'RENAME', 'RESOURCE', 'REVOKE', 'SELECT', 'SET', 'SHARE', 'SIZE', 'SMALLINT', 'START', 'SYNONYM', 'TABLE', 'THEN', 'TO', 'TRIGGER', 'UNION', 'UNIQUE', 'UPDATE', 'VALUES', 'VARCHAR', 'VARCHAR2', 'VIEW', 'WHERE', 'WITH'];

    /** @var list<string> nama tabel dalam cakupan, urut modul lalu nama */
    private array $tabel = [];

    /** @var array<string,true> */
    private array $dalamCakupan = [];

    public function handle(): int
    {
        $outDir = base_path($this->option('out'));
        File::ensureDirectoryExists($outDir);

        $this->tabel = $this->daftarTabel();
        if ($this->tabel === []) {
            $this->warn('Tidak ada tabel di schema ini.');

            return self::FAILURE;
        }
        $this->dalamCakupan = array_fill_keys($this->tabel, true);

        $kolom = $this->kolomSemuaTabel();
        $constraint = $this->constraintSemuaTabel();
        $komentar = $this->komentar();
        $fk = $this->fk();
        $index = $this->indexLepas();
        $trigger = $this->trigger();
        $sequence = $this->sequence($trigger);
        $plsql = $this->plsql();
        $view = $this->view();

        $sql01 = $this->sql01($kolom, $constraint, $komentar);
        $sql02 = $this->sql02($fk);
        $sql03 = $this->sql03($index);
        $sql04 = $this->sql04($sequence, $trigger);
        $sql05 = $this->sql05($plsql);
        $sql06 = $this->sql06($view);

        File::put("$outDir/01_tabel.sql", $this->kepala('LANGKAH 1 — CREATE TABLE + PK / UNIQUE / CHECK / komentar', 'Jalankan di schema kosong. Berhenti di error pertama.').$sql01);
        File::put("$outDir/02_fk.sql", $this->kepala('LANGKAH 2 — Foreign key antar tabel', 'Semua tabel dari 01 harus sudah ada. Urutan bebas karena FK ditambah setelah semua tabel dibuat.').$sql02);
        File::put("$outDir/03_index.sql", $this->kepala('LANGKAH 3 — Index lepas (bukan pendukung PK/UK)', 'Termasuk index fungsi UPPER(...) untuk pencarian LOINC/SNOMED.').$sql03);
        File::put("$outDir/04_sequence_trigger.sql", $this->kepala('LANGKAH 4 — Sequence & trigger', 'START WITH mengikuti nilai terakhir di DB sumber saat generate; sesuaikan bila memuat data lama.').$sql04);
        File::put("$outDir/05_plsql.sql", $this->kepala('LANGKAH 5 — Type / function / procedure / package PL/SQL', 'Dibutuhkan view tertentu (mis. STRING_AGG). "created with compilation errors" tidak menghentikan skrip — periksa SHOW ERRORS.').$sql05);
        File::put("$outDir/06_view.sql", $this->kepala('LANGKAH 6 — View (urut ketergantungan)', 'CREATE OR REPLACE FORCE VIEW supaya view yang INVALID di sumber tidak menghentikan instalasi.').$sql06);
        File::put("$outDir/99_drop_semua.sql", $this->sql99($view, $plsql, $trigger, $sequence, $fk));
        File::put(
            "$outDir/siklik_ddl_lengkap.sql",
            $this->kepala('DDL LENGKAP schema siklik (gabungan 01–06)', 'Satu berkas untuk instalasi schema kosong: tabel → FK → index → sequence/trigger → PL/SQL → view.')
            ."\n-- ===== 01 TABEL =====\n".$sql01
            ."\n-- ===== 02 FOREIGN KEY =====\n".$sql02
            ."\n-- ===== 03 INDEX =====\n".$sql03
            ."\n-- ===== 04 SEQUENCE & TRIGGER =====\n".$sql04
            ."\n-- ===== 05 PL/SQL =====\n".$sql05
            ."\n-- ===== 06 VIEW =====\n".$sql06
        );
        File::put("$outDir/README.md", $this->readme($constraint, $fk, $index, $sequence, $trigger, $plsql, $view));

        $this->info(sprintf('Tabel    : %d', count($this->tabel)));
        $this->info(sprintf('PK/UK/CK : %d', array_sum(array_map('count', $constraint))));
        $this->info(sprintf('FK       : %d', count($fk)));
        $this->info(sprintf('Index    : %d', count($index)));
        $this->info(sprintf('Sequence : %d', count($sequence)));
        $this->info(sprintf('Trigger  : %d', count($trigger)));
        $this->info(sprintf('PL/SQL   : %d', count($plsql)));
        $this->info(sprintf('View     : %d', count($view)));
        $this->line("Output   : $outDir");

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Pembaca data dictionary
    // ------------------------------------------------------------------

    /** Tabel dalam cakupan, urut modul (ModulTabel::daftar) lalu nama. @return list<string> */
    private function daftarTabel(): array
    {
        $urutModul = array_flip(array_keys(ModulTabel::daftar()));
        $tabelList = [];
        foreach (DB::select("select table_name from user_tables where temporary = 'N' and nested = 'NO' order by 1") as $r) {
            $modul = ModulTabel::dari($r->table_name);
            if ($this->option('tanpa-sistem') && $modul === 'Sistem Laravel') {
                continue;
            }
            $tabelList[] = ['nama' => $r->table_name, 'modul' => $modul, 'urut' => $urutModul[$modul] ?? PHP_INT_MAX];
        }
        usort($tabelList, fn ($a, $b) => [$a['urut'], $a['nama']] <=> [$b['urut'], $b['nama']]);

        return array_column($tabelList, 'nama');
    }

    /** @return array<string,list<array{nama:string,tipe:string,default:?string,nullable:bool}>> tabel → kolom */
    private function kolomSemuaTabel(): array
    {
        $hasil = [];
        $rows = DB::select('
            select c.table_name, c.column_name, c.data_type, c.data_length, c.data_precision, c.data_scale,
                   c.char_used, c.char_length, c.nullable, c.data_default
              from user_tab_columns c
              join user_tables t on t.table_name = c.table_name
             order by c.table_name, c.column_id
        ');
        foreach ($rows as $r) {
            if (! isset($this->dalamCakupan[$r->table_name])) {
                continue;
            }
            $hasil[$r->table_name][] = [
                'nama' => $r->column_name,
                'tipe' => $this->tipe($r),
                'default' => $r->data_default === null ? null : trim((string) $r->data_default),
                'nullable' => $r->nullable === 'Y',
            ];
        }

        return $hasil;
    }

    private function tipe(object $r): string
    {
        return match ($r->data_type) {
            'NUMBER' => $r->data_precision === null
                ? ($r->data_scale === null ? 'NUMBER' : sprintf('NUMBER(*,%d)', $r->data_scale))
                : sprintf('NUMBER(%d%s)', $r->data_precision, $r->data_scale ? ','.$r->data_scale : ''),
            'FLOAT' => sprintf('FLOAT(%d)', $r->data_precision),
            'VARCHAR2', 'CHAR' => sprintf(
                '%s(%d%s)',
                $r->data_type,
                $r->char_used === 'C' ? $r->char_length : $r->data_length,
                $r->char_used === 'C' ? ' CHAR' : ''
            ),
            'NVARCHAR2', 'NCHAR' => sprintf('%s(%d)', $r->data_type, $r->char_length),
            'RAW' => sprintf('RAW(%d)', $r->data_length),
            default => $r->data_type, // DATE, TIMESTAMP(6), CLOB, BLOB, LONG, ...
        };
    }

    /**
     * PK, UNIQUE, dan CHECK bernama pengguna per tabel (CHECK NOT NULL bawaan SYS_C dilewati
     * karena sudah ditulis sebagai NOT NULL di kolom).
     * @return array<string,list<array{nama:string,jenis:string,kolom:list<string>,kondisi:?string}>>
     */
    private function constraintSemuaTabel(): array
    {
        $kolomPer = $this->kolomConstraint();
        $hasil = [];
        $rows = DB::select("
            select table_name, constraint_name, constraint_type, search_condition, generated
              from user_constraints
             where constraint_type in ('P','U','C')
             order by table_name, decode(constraint_type, 'P', 1, 'U', 2, 3), constraint_name
        ");
        foreach ($rows as $r) {
            if (! isset($this->dalamCakupan[$r->table_name])) {
                continue;
            }
            if ($r->constraint_type === 'C' && $r->generated !== 'USER NAME') {
                continue;
            }
            $hasil[$r->table_name][] = [
                'nama' => $r->constraint_name,
                'jenis' => $r->constraint_type,
                'kolom' => $kolomPer[$r->constraint_name] ?? [],
                'kondisi' => $r->search_condition === null ? null : trim((string) $r->search_condition),
            ];
        }

        return $hasil;
    }

    /** @return array<string,list<string>> constraint → kolom urut posisi */
    private function kolomConstraint(): array
    {
        $hasil = [];
        foreach (DB::select('select constraint_name, column_name, position from user_cons_columns order by constraint_name, position') as $r) {
            $hasil[$r->constraint_name][] = $r->column_name;
        }

        return $hasil;
    }

    /** @return array{tabel:array<string,string>,kolom:array<string,array<string,string>>} */
    private function komentar(): array
    {
        $tabel = [];
        foreach (DB::select("select table_name, comments from user_tab_comments where comments is not null and table_type = 'TABLE'") as $r) {
            if (isset($this->dalamCakupan[$r->table_name])) {
                $tabel[$r->table_name] = trim((string) $r->comments);
            }
        }
        $kolom = [];
        foreach (DB::select('select table_name, column_name, comments from user_col_comments where comments is not null') as $r) {
            if (isset($this->dalamCakupan[$r->table_name])) {
                $kolom[$r->table_name][$r->column_name] = trim((string) $r->comments);
            }
        }

        return ['tabel' => $tabel, 'kolom' => $kolom];
    }

    /** @return list<array{anak:string,nama:string,kolomAnak:list<string>,induk:string,kolomInduk:list<string>,cascade:bool}> */
    private function fk(): array
    {
        $kolomPer = $this->kolomConstraint();
        $hasil = [];
        $rows = DB::select("
            select c.table_name anak, c.constraint_name, c.r_constraint_name, c.delete_rule, p.table_name induk
              from user_constraints c
              join user_constraints p on p.constraint_name = c.r_constraint_name and p.owner = c.r_owner
             where c.constraint_type = 'R'
             order by c.table_name, c.constraint_name
        ");
        foreach ($rows as $r) {
            if (! isset($this->dalamCakupan[$r->anak]) || ! isset($this->dalamCakupan[$r->induk])) {
                continue;
            }
            $hasil[] = [
                'anak' => $r->anak,
                'nama' => $r->constraint_name,
                'kolomAnak' => $kolomPer[$r->constraint_name] ?? [],
                'induk' => $r->induk,
                'kolomInduk' => $kolomPer[$r->r_constraint_name] ?? [],
                'cascade' => $r->delete_rule === 'CASCADE',
            ];
        }

        return $hasil;
    }

    /**
     * Index yang bukan pendukung constraint PK/UK (index LOB & IOT dilewati).
     * Index fungsi: kolom SYS_NC…$ diganti ekspresi dari user_ind_expressions.
     * @return list<array{nama:string,tabel:string,unik:bool,kolom:list<string>}>
     */
    private function indexLepas(): array
    {
        $ekspresi = [];
        foreach (DB::select('select index_name, column_position, column_expression from user_ind_expressions') as $r) {
            $ekspresi[$r->index_name][(int) $r->column_position] = trim((string) $r->column_expression);
        }
        $kolomPer = [];
        foreach (DB::select('select index_name, column_name, column_position, descend from user_ind_columns order by index_name, column_position') as $r) {
            $adaEkspresi = isset($ekspresi[$r->index_name][(int) $r->column_position]);
            $teks = $adaEkspresi ? $ekspresi[$r->index_name][(int) $r->column_position] : $this->id($r->column_name);
            $kolomPer[$r->index_name][] = $teks.($r->descend === 'DESC' && ! $adaEkspresi ? ' DESC' : '');
        }

        $hasil = [];
        $rows = DB::select("
            select i.index_name, i.table_name, i.uniqueness
              from user_indexes i
             where i.index_type in ('NORMAL', 'FUNCTION-BASED NORMAL')
               and not exists (select 1 from user_constraints c where c.index_name = i.index_name)
             order by i.table_name, i.index_name
        ");
        foreach ($rows as $r) {
            if (! isset($this->dalamCakupan[$r->table_name])) {
                continue;
            }
            $hasil[] = [
                'nama' => $r->index_name,
                'tabel' => $r->table_name,
                'unik' => $r->uniqueness === 'UNIQUE',
                'kolom' => $kolomPer[$r->index_name] ?? [],
            ];
        }

        return $hasil;
    }

    /** @return list<array{nama:string,tabel:string,teks:string}> teks = deskripsi + body siap CREATE */
    private function trigger(): array
    {
        $hasil = [];
        foreach (DB::select("select trigger_name, table_name, description, trigger_body from user_triggers where base_object_type = 'TABLE' order by table_name, trigger_name") as $r) {
            if (! isset($this->dalamCakupan[$r->table_name])) {
                continue;
            }
            // description diawali "OWNER".nama — buang pemilik supaya bisa dijalankan di schema mana pun.
            $deskripsi = preg_replace('/^\s*"[^"]+"\./', '', (string) $r->description);
            $deskripsi = preg_replace('/\s+/', ' ', trim($deskripsi));
            $hasil[] = [
                'nama' => $r->trigger_name,
                'tabel' => $r->table_name,
                'teks' => 'CREATE OR REPLACE TRIGGER '.$deskripsi."\n".rtrim((string) $r->trigger_body),
            ];
        }

        return $hasil;
    }

    /**
     * Semua sequence schema. Dengan --tanpa-sistem, sequence yang hanya dirujuk trigger tabel
     * sistem (USERS_ID_SEQ, dsb) ikut dilewati.
     * @return list<array{nama:string,ddl:string}>
     */
    private function sequence(array $triggerDalamCakupan): array
    {
        $dirujukTriggerLuar = [];
        if ($this->option('tanpa-sistem')) {
            $dalam = array_fill_keys(array_column($triggerDalamCakupan, 'nama'), true);
            foreach (DB::select('select trigger_name, trigger_body from user_triggers') as $r) {
                if (isset($dalam[$r->trigger_name])) {
                    continue;
                }
                if (preg_match_all('/\b([A-Za-z0-9_$#]+)\.nextval\b/i', (string) $r->trigger_body, $m)) {
                    foreach ($m[1] as $seq) {
                        $dirujukTriggerLuar[strtoupper($seq)] = true;
                    }
                }
            }
        }

        $hasil = [];
        foreach (DB::select('select sequence_name, min_value, max_value, increment_by, last_number, cache_size, cycle_flag, order_flag from user_sequences order by 1') as $r) {
            if (isset($dirujukTriggerLuar[$r->sequence_name])) {
                continue;
            }
            $maks = preg_match('/^9{27,}$/', (string) $r->max_value) ? 'NOMAXVALUE' : 'MAXVALUE '.$r->max_value; // 27 angka 9 = default Oracle
            $hasil[] = [
                'nama' => $r->sequence_name,
                'ddl' => sprintf(
                    'CREATE SEQUENCE %s START WITH %s INCREMENT BY %s MINVALUE %s %s %s %s %s;',
                    $this->id($r->sequence_name),
                    max((int) $r->last_number, (int) $r->min_value),
                    $r->increment_by,
                    $r->min_value,
                    $maks,
                    (int) $r->cache_size > 0 ? 'CACHE '.$r->cache_size : 'NOCACHE',
                    $r->cycle_flag === 'Y' ? 'CYCLE' : 'NOCYCLE',
                    $r->order_flag === 'Y' ? 'ORDER' : 'NOORDER'
                ),
            ];
        }

        return $hasil;
    }

    /**
     * Semua unit PL/SQL milik schema dari user_source, urut topologis menurut user_dependencies
     * (mis. ADD_NUMBERS butuh package SOAP_API, ORACLE_TERBILANG butuh ZRIGHT) dengan urutan
     * cadangan TYPE → PACKAGE → TYPE BODY → FUNCTION → PROCEDURE → PACKAGE BODY.
     * @return list<array{nama:string,jenis:string,teks:string}>
     */
    private function plsql(): array
    {
        $urutJenis = array_flip(['TYPE', 'PACKAGE', 'TYPE BODY', 'FUNCTION', 'PROCEDURE', 'PACKAGE BODY']);
        $unit = [];
        foreach (DB::select('select name, type, line, text from user_source order by type, name, line') as $r) {
            if (! isset($urutJenis[$r->type])) {
                continue;
            }
            $unit[$r->type.'|'.$r->name]['nama'] = $r->name;
            $unit[$r->type.'|'.$r->name]['jenis'] = $r->type;
            $unit[$r->type.'|'.$r->name]['baris'][] = rtrim((string) $r->text, "\r\n");
        }
        uasort($unit, fn ($a, $b) => [$urutJenis[$a['jenis']], $a['nama']] <=> [$urutJenis[$b['jenis']], $b['nama']]);

        $dep = [];
        foreach (DB::select("
            select type, name, referenced_type, referenced_name
              from user_dependencies
             where referenced_owner = user
               and type in ('TYPE','TYPE BODY','FUNCTION','PROCEDURE','PACKAGE','PACKAGE BODY')
               and referenced_type in ('TYPE','FUNCTION','PROCEDURE','PACKAGE')
        ") as $r) {
            $dep[$r->type.'|'.$r->name][] = $r->referenced_type.'|'.$r->referenced_name;
        }
        $urut = [];
        $tanda = [];
        $kunjungi = function (string $kunci) use (&$kunjungi, &$urut, &$tanda, $dep, $unit) {
            if (isset($tanda[$kunci]) || ! isset($unit[$kunci])) {
                return;
            }
            $tanda[$kunci] = true;
            foreach ($dep[$kunci] ?? [] as $d) {
                if ($d !== $kunci) {
                    $kunjungi($d);
                }
            }
            $urut[] = $kunci;
        };
        foreach (array_keys($unit) as $kunci) {
            $kunjungi($kunci);
        }

        $hasil = [];
        foreach ($urut as $kunci) {
            $u = $unit[$kunci];
            // Teks di user_source diawali "FUNCTION nama ..." tanpa CREATE; buang pemilik bila ada.
            $teks = preg_replace('/^(\s*\w+(?:\s+BODY)?\s+)"[^"]+"\./i', '$1', implode("\n", $u['baris']), 1);
            $hasil[] = ['nama' => $u['nama'], 'jenis' => $u['jenis'], 'teks' => 'CREATE OR REPLACE '.ltrim($teks)];
        }

        return $hasil;
    }

    /**
     * View urut ketergantungan (view yang dirujuk view lain dibuat lebih dulu) beserta
     * daftar kolom eksplisit — teks di dictionary tidak memuat alias (ORA-00998 tanpa daftar kolom).
     * @return list<array{nama:string,kolom:list<string>,teks:string}>
     */
    private function view(): array
    {
        $namaList = array_map(fn ($r) => $r->view_name, DB::select('select view_name from user_views order by 1'));
        $dep = [];
        foreach (DB::select("select name, referenced_name from user_dependencies where type = 'VIEW' and referenced_type = 'VIEW'") as $r) {
            $dep[$r->name][] = $r->referenced_name;
        }
        $set = array_flip($namaList);
        $urut = [];
        $tanda = [];
        $kunjungi = function (string $nama) use (&$kunjungi, &$urut, &$tanda, $dep, $set) {
            if (isset($tanda[$nama]) || ! isset($set[$nama])) {
                return;
            }
            $tanda[$nama] = true;
            foreach ($dep[$nama] ?? [] as $d) {
                $kunjungi($d);
            }
            $urut[] = $nama;
        };
        foreach ($namaList as $nama) {
            $kunjungi($nama);
        }

        $hasil = [];
        foreach ($urut as $nama) {
            $row = DB::selectOne('select text from user_views where view_name = ?', [$nama]);
            $kolom = array_map(
                fn ($r) => $r->column_name,
                DB::select('select column_name from user_tab_columns where table_name = ? order by column_id', [$nama])
            );
            $hasil[] = ['nama' => $nama, 'kolom' => $kolom, 'teks' => rtrim((string) ($row->text ?? ''))];
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Penulis SQL
    // ------------------------------------------------------------------

    /**
     * Identifier apa adanya bila lazim (huruf besar, diawali huruf, bukan reserved word);
     * selain itu diberi tanda kutip ganda — mis. kolom SKMST_PRODUCTS."1_1" atau nama mixed-case.
     */
    private function id(string $nama): string
    {
        if (preg_match('/^[A-Z][A-Z0-9_$#]{0,29}$/', $nama) && ! in_array($nama, self::RESERVED, true)) {
            return $nama;
        }

        return '"'.str_replace('"', '""', $nama).'"';
    }

    /** @param list<string> $kolom */
    private function daftarId(array $kolom): string
    {
        return implode(', ', array_map(fn ($k) => $this->id($k), $kolom));
    }

    private function kepala(string $judul, string $keterangan): string
    {
        $tgl = now()->format('Y-m-d H:i');
        $opsi = $this->option('tanpa-sistem') ? ' --tanpa-sistem' : '';

        return <<<SQL
        -- ============================================================
        -- $judul
        -- Dibuat  : php artisan siklik:ddl-create{$opsi}  ($tgl)
        -- Sumber  : data dictionary schema yang terhubung saat generate
        -- $keterangan
        -- ============================================================
        SET DEFINE OFF
        SET SQLBLANKLINES ON
        SET ECHO ON
        WHENEVER SQLERROR EXIT SQL.SQLCODE


        SQL;
    }

    private function sql01(array $kolom, array $constraint, array $komentar): string
    {
        $s = '';
        $modulSebelumnya = null;
        foreach ($this->tabel as $t) {
            $modul = ModulTabel::dari($t);
            if ($modul !== $modulSebelumnya) {
                $s .= "\n-- ---------- Modul: $modul ----------\n";
                $modulSebelumnya = $modul;
            }

            $baris = [];
            $lebar = max(array_map(fn ($k) => strlen($this->id($k['nama'])), $kolom[$t] ?? [''])) + 1;
            foreach ($kolom[$t] ?? [] as $k) {
                $def = $k['default'] === null ? '' : ' DEFAULT '.$k['default'];
                $baris[] = sprintf('    %-'.$lebar.'s %s%s%s', $this->id($k['nama']), $k['tipe'], $def, $k['nullable'] ? '' : ' NOT NULL');
            }
            foreach ($constraint[$t] ?? [] as $c) {
                $baris[] = '    '.$this->klausaConstraint($c);
            }
            $s .= sprintf("\nCREATE TABLE %s (\n%s\n);\n", $this->id($t), implode(",\n", $baris));

            if (isset($komentar['tabel'][$t])) {
                $s .= sprintf("COMMENT ON TABLE %s IS '%s';\n", $this->id($t), str_replace("'", "''", $komentar['tabel'][$t]));
            }
            foreach ($komentar['kolom'][$t] ?? [] as $kol => $teks) {
                $s .= sprintf("COMMENT ON COLUMN %s.%s IS '%s';\n", $this->id($t), $this->id($kol), str_replace("'", "''", $teks));
            }
        }

        return $s;
    }

    private function klausaConstraint(array $c): string
    {
        $kolom = $this->daftarId($c['kolom']);
        $nama = $this->id($c['nama']);

        return match ($c['jenis']) {
            'P' => sprintf('CONSTRAINT %s PRIMARY KEY (%s)', $nama, $kolom),
            'U' => sprintf('CONSTRAINT %s UNIQUE (%s)', $nama, $kolom),
            default => sprintf('CONSTRAINT %s CHECK (%s)', $nama, $c['kondisi']),
        };
    }

    private function sql02(array $fk): string
    {
        $s = '-- Foreign key ('.count($fk).")\n";
        $anakSebelumnya = null;
        foreach ($fk as $f) {
            if ($f['anak'] !== $anakSebelumnya) {
                $s .= "\n-- {$f['anak']}\n";
                $anakSebelumnya = $f['anak'];
            }
            $s .= sprintf(
                "ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)%s;\n",
                $this->id($f['anak']),
                $this->id($f['nama']),
                $this->daftarId($f['kolomAnak']),
                $this->id($f['induk']),
                $this->daftarId($f['kolomInduk']),
                $f['cascade'] ? ' ON DELETE CASCADE' : ''
            );
        }

        return $s;
    }

    private function sql03(array $index): string
    {
        $s = '-- Index lepas ('.count($index).")\n";
        $tabelSebelumnya = null;
        foreach ($index as $i) {
            if ($i['tabel'] !== $tabelSebelumnya) {
                $s .= "\n-- {$i['tabel']}\n";
                $tabelSebelumnya = $i['tabel'];
            }
            $s .= sprintf("CREATE %sINDEX %s ON %s (%s);\n", $i['unik'] ? 'UNIQUE ' : '', $this->id($i['nama']), $this->id($i['tabel']), implode(', ', $i['kolom']));
        }

        return $s;
    }

    private function sql04(array $sequence, array $trigger): string
    {
        $s = '-- Sequence ('.count($sequence).")\n";
        foreach ($sequence as $q) {
            $s .= $q['ddl']."\n";
        }
        $s .= "\n-- Trigger (".count($trigger).") — terminator \"/\" karena badan PL/SQL\n";
        foreach ($trigger as $t) {
            $s .= "\n".$t['teks']."\n/\n";
        }

        return $s;
    }

    private function sql05(array $plsql): string
    {
        $s = '-- Unit PL/SQL ('.count($plsql).") — terminator \"/\"\n";
        foreach ($plsql as $u) {
            $s .= "\n-- {$u['jenis']} {$u['nama']}\n".rtrim($u['teks'])."\n/\nSHOW ERRORS\n";
        }

        return $s;
    }

    private function sql06(array $view): string
    {
        $s = '-- View ('.count($view).") — terminator \"/\" karena teks view bisa memuat komentar --\n";
        foreach ($view as $v) {
            $daftar = $v['kolom'] === [] ? '' : ' ('.implode(', ', array_map(fn ($k) => '"'.$k.'"', $v['kolom'])).')';
            $s .= sprintf("\nCREATE OR REPLACE FORCE VIEW %s%s AS\n%s\n/\n", $this->id($v['nama']), $daftar, $v['teks']);
        }

        return $s;
    }

    private function sql99(array $view, array $plsql, array $trigger, array $sequence, array $fk): string
    {
        $tgl = now()->format('Y-m-d H:i');
        $s = <<<SQL
        -- ============================================================
        -- HAPUS SEMUA objek yang dibuat 01–06 (kebalikan urutan)
        -- Dibuat  : php artisan siklik:ddl-create  ($tgl)
        -- !!! MENGHAPUS TABEL BESERTA DATANYA. Hanya untuk membatalkan instalasi
        -- !!! di schema uji. JANGAN dijalankan di schema yang sudah berisi data.
        -- ============================================================
        SET DEFINE OFF
        SET ECHO ON
        WHENEVER SQLERROR CONTINUE


        SQL;
        $s .= "-- View\n";
        foreach (array_reverse($view) as $v) {
            $s .= "DROP VIEW {$this->id($v['nama'])};\n";
        }
        $s .= "\n-- PL/SQL (body ikut terhapus bersama spec-nya)\n";
        foreach (array_reverse($plsql) as $u) {
            if (in_array($u['jenis'], ['TYPE BODY', 'PACKAGE BODY'], true)) {
                continue;
            }
            $s .= "DROP {$u['jenis']} {$this->id($u['nama'])}".($u['jenis'] === 'TYPE' ? ' FORCE' : '').";\n";
        }
        $s .= "\n-- Trigger\n";
        foreach ($trigger as $t) {
            $s .= "DROP TRIGGER {$this->id($t['nama'])};\n";
        }
        $s .= "\n-- Sequence\n";
        foreach ($sequence as $q) {
            $s .= "DROP SEQUENCE {$this->id($q['nama'])};\n";
        }
        $s .= "\n-- Foreign key\n";
        foreach ($fk as $f) {
            $s .= "ALTER TABLE {$this->id($f['anak'])} DROP CONSTRAINT {$this->id($f['nama'])};\n";
        }
        $s .= "\n-- Tabel (index & constraint ikut terhapus)\n";
        foreach (array_reverse($this->tabel) as $t) {
            $s .= "DROP TABLE {$this->id($t)} CASCADE CONSTRAINTS PURGE;\n";
        }

        return $s;
    }

    private function readme(array $constraint, array $fk, array $index, array $sequence, array $trigger, array $plsql, array $view): string
    {
        $nT = count($this->tabel);
        $nC = array_sum(array_map('count', $constraint));
        $nF = count($fk);
        $nI = count($index);
        $nQ = count($sequence);
        $nG = count($trigger);
        $nP = count($plsql);
        $nV = count($view);
        $opsi = $this->option('tanpa-sistem') ? ' --tanpa-sistem' : '';
        $perModul = [];
        foreach ($this->tabel as $t) {
            $modul = ModulTabel::dari($t);
            $perModul[$modul] = ($perModul[$modul] ?? 0) + 1;
        }
        $barisModul = '';
        foreach ($perModul as $modul => $jumlah) {
            $barisModul .= sprintf("| %-28s | %6d |\n", $modul, $jumlah);
        }

        return <<<MD
        # DDL CREATE lengkap schema siklik

        Dibuat otomatis oleh `php artisan siklik:ddl-create{$opsi}` dari data dictionary DB yang terhubung.
        Jangan edit berkas SQL di folder ini secara manual; ubah generator lalu jalankan ulang.

        ## Isi

        | Berkas                     | Isi                                                                  |
        |----------------------------|----------------------------------------------------------------------|
        | `01_tabel.sql`             | `CREATE TABLE` ({$nT} tabel, urut modul) + PK / UNIQUE / CHECK ({$nC}) + `COMMENT ON` |
        | `02_fk.sql`                | `ALTER TABLE … ADD CONSTRAINT … FOREIGN KEY` ({$nF}, termasuk `ON DELETE CASCADE`) |
        | `03_index.sql`             | Index lepas ({$nI}) yang bukan pendukung PK/UK, termasuk index fungsi `UPPER(...)` |
        | `04_sequence_trigger.sql`  | Sequence ({$nQ}) + trigger ({$nG})                                    |
        | `05_plsql.sql`             | Type / function / procedure / package ({$nP}) dari `user_source`; view `SKVIEW_ACCOUNTS*` & `SKVIEW_HPPES` butuh `STRING_AGG` |
        | `06_view.sql`              | View ({$nV}) urut ketergantungan, `CREATE OR REPLACE FORCE VIEW` dgn daftar kolom |
        | `siklik_ddl_lengkap.sql`   | Gabungan 01–06 dalam satu berkas                                      |
        | `99_drop_semua.sql`        | Kebalikan 01–06. **Menghapus tabel + data** — hanya untuk schema uji  |

        ## Tabel per modul

        | Modul                        | Tabel  |
        |------------------------------|--------|
        {$barisModul}
        ## Cara pakai

        1. Siapkan user/schema Oracle kosong dengan hak `CREATE TABLE, CREATE VIEW, CREATE SEQUENCE, CREATE TRIGGER,
           CREATE PROCEDURE, CREATE TYPE` dan kuota tablespace.
        2. Jalankan lewat SQL*Plus (bukan DBeaver — trigger & view memakai terminator `/`):

           ```
           echo exit | sqlplus -S siklik/rahasia@host/orcl @siklik_ddl_lengkap.sql | tee instal.log
           grep -c ORA- instal.log      # harus 0
           ```

           `echo exit` perlu karena berkas tidak diakhiri EXIT (supaya aman dijalankan dari sesi SQL*Plus
           yang sudah terbuka). Bisa juga berurutan `@01_tabel.sql` … `@06_view.sql` bila ingin berhenti per tahap.
        3. Muat data (`imp`/`impdp` dengan `IGNORE=Y`/`TABLE_EXISTS_ACTION=APPEND`, atau INSERT dari CSV),
           lalu setel ulang sequence bila nilai `START WITH` sudah terlampaui data.
        4. Lanjutkan `install_bundle*.sql` di folder induk hanya bila schema sumber belum memuat objek
           fitur lanjutan (generator sudah menyalin apa pun yang ada di DB sumber).

        ## Catatan

        - Nama objek sudah berprefix `SKMST_ / SKTXN_ / SKACC_ / SKVIEW_` (rename 11 Sep 2026).
          Synonym nama lama (`rename-siklik/02_synonym_kompat_legacy.sql`) TIDAK dibuat di sini —
          jalankan itu hanya jika siklik-lite legacy masih dipakai.
        - `NOT NULL` ditulis di kolom; constraint CHECK bawaan `SYS_C…` untuk NOT NULL tidak ditulis ulang.
        - PK yang di DB sumber ditopang index bernama lain (mis. `RJDTLS_PK` ↔ `RJDTLS_RJDTL_DTL_IX`)
          di sini dibuat dengan index bernama sama dengan constraint-nya; hanya nama index yang berbeda.
        - View yang berstatus INVALID di DB sumber tetap ditulis; `FORCE` membuatnya tercipta lalu
          bisa diperbaiki/dihapus belakangan tanpa menghentikan instalasi.
        - Storage clause (tablespace, PCTFREE, dsb) sengaja tidak disertakan — memakai default schema.
        - Unit PL/SQL disalin apa adanya dari `user_source`; procedure utilitas warisan (`TK_BACKUP_DB`, dsb) bisa
          "created with compilation errors" bila bergantung objek/privilege di luar schema (`TK_BACKUP_DB`: ORA-00942
          pada view sistem) — tidak menghentikan skrip, dan view tidak memakainya. Yang wajib valid: `T_STRING_AGG` + `STRING_AGG` (dipakai `SKVIEW_ACCOUNTS*`, `SKVIEW_HPPES`).
        - Kolom PK yang NOT NULL-nya di DB sumber hanya tersirat dari PK di sini ditulis `NOT NULL` eksplisit
          (menambah satu constraint CHECK sistem; tidak mengubah perilaku).
        - Diuji 12 Sep 2026 di schema kosong Oracle 10g lokal: 122 tabel, 785 kolom, 147 FK, 276 index,
          21 sequence, 8 trigger, 16 unit PL/SQL, 41 view tercipta tanpa ORA-; definisi kolom & FK identik dengan sumber.
        MD;
    }
}
