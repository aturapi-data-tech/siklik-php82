<?php

namespace App\Support\Skema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Pembaca data dictionary Oracle (user_tables, user_views, user_tab_columns,
 * user_constraints) untuk panduan struktur tabel. Selalu membaca kondisi DB
 * yang sedang terhubung, jadi tidak pernah basi. Hasil di-cache per request.
 *
 * Oracle 10g: tanpa LISTAGG, tanpa JSON — semua agregasi dikerjakan di PHP.
 */
class KamusData
{
    private static ?array $tabel = null;

    private static ?array $relasi = null;

    /** Semua tabel & view + modul + jumlah kolom. @return list<array{nama:string,jenis:string,modul:string,kolom:int,baris:?int}> */
    public static function tabel(): array
    {
        if (self::$tabel !== null) {
            return self::$tabel;
        }

        $jumlahKolom = [];
        foreach (DB::select('select table_name, count(*) c from user_tab_columns group by table_name') as $r) {
            $jumlahKolom[$r->table_name] = (int) $r->c;
        }

        $hasil = [];
        foreach (DB::select('select table_name, num_rows from user_tables') as $r) {
            $hasil[] = self::baris($r->table_name, 'TABLE', $jumlahKolom, $r->num_rows === null ? null : (int) $r->num_rows);
        }
        foreach (DB::select('select view_name from user_views') as $r) {
            $hasil[] = self::baris($r->view_name, 'VIEW', $jumlahKolom, null);
        }
        usort($hasil, fn ($a, $b) => [$a['modul'], $a['jenis'], $a['nama']] <=> [$b['modul'], $b['jenis'], $b['nama']]);

        return self::$tabel = $hasil;
    }

    private static function baris(string $nama, string $jenis, array $jumlahKolom, ?int $baris): array
    {
        return [
            'nama' => $nama,
            'jenis' => $jenis,
            'modul' => ModulTabel::dari($nama),
            'kolom' => $jumlahKolom[$nama] ?? 0,
            'baris' => $baris,
        ];
    }

    /** Tabel dikelompokkan per modul, urutan mengikuti ModulTabel::daftar(). @return array<string,list<array>> */
    public static function perModul(): array
    {
        $kelompok = array_fill_keys(array_keys(ModulTabel::daftar()), []);
        foreach (self::tabel() as $t) {
            $kelompok[$t['modul']][] = $t;
        }

        return array_filter($kelompok);
    }

    /**
     * Semua relasi FK di schema.
     * @return list<array{anak:string,kolomAnak:string,constraint:string,induk:string,kolomInduk:string,modul:string}>
     */
    public static function relasi(): array
    {
        if (self::$relasi !== null) {
            return self::$relasi;
        }

        $kolomPer = [];
        foreach (DB::select('select constraint_name, column_name, position from user_cons_columns order by constraint_name, position') as $r) {
            $kolomPer[$r->constraint_name][] = $r->column_name;
        }

        $hasil = [];
        $rows = DB::select("
            select c.table_name anak, c.constraint_name, p.table_name induk, c.r_constraint_name
              from user_constraints c
              join user_constraints p on p.constraint_name = c.r_constraint_name
             where c.constraint_type = 'R'
             order by c.table_name, c.constraint_name
        ");
        foreach ($rows as $r) {
            $hasil[] = [
                'anak' => $r->anak,
                'kolomAnak' => implode(', ', $kolomPer[$r->constraint_name] ?? []),
                'constraint' => $r->constraint_name,
                'induk' => $r->induk,
                'kolomInduk' => implode(', ', $kolomPer[$r->r_constraint_name] ?? []),
                'modul' => ModulTabel::dari($r->anak),
            ];
        }

        return self::$relasi = $hasil;
    }

    /** Relasi yang melibatkan tabel tertentu, dipisah keluar (anak) & masuk (induk). */
    public static function relasiTabel(string $nama): array
    {
        $nama = strtoupper($nama);
        $keluar = array_values(array_filter(self::relasi(), fn ($r) => $r['anak'] === $nama));
        $masuk = array_values(array_filter(self::relasi(), fn ($r) => $r['induk'] === $nama));

        return ['keluar' => $keluar, 'masuk' => $masuk];
    }

    /**
     * Relasi implisit: kolom yang namanya sama dengan PK satu-kolom tabel lain tetapi TIDAK
     * punya FK terdeklarasi (mis. SKTXN_RJHDRS.REG_NO → SKMST_PASIENS). Join tetap sah secara
     * semantik, hanya Oracle tidak menjaganya — programmer harus tahu ini.
     * @return list<array{anak:string,kolom:string,induk:string,modul:string}>
     */
    public static function relasiImplisit(): array
    {
        $pkTunggal = [];
        $jumlah = [];
        foreach (DB::select("
            select c.table_name, cc.column_name
              from user_constraints c
              join user_cons_columns cc on cc.constraint_name = c.constraint_name
             where c.constraint_type = 'P'
        ") as $r) {
            $jumlah[$r->table_name] = ($jumlah[$r->table_name] ?? 0) + 1;
            $pkTunggal[$r->table_name] = $r->column_name;
        }
        $pkTunggal = array_filter($pkTunggal, fn ($k, $t) => ($jumlah[$t] ?? 0) === 1 && ! str_starts_with($t, 'SKVIEW_'), ARRAY_FILTER_USE_BOTH);
        $kolomKeInduk = [];
        foreach ($pkTunggal as $tabel => $kolom) {
            if (in_array($kolom, ['ID', 'CODE', 'KEY', 'NO'], true)) {
                continue;
            }
            $kolomKeInduk[$kolom][] = $tabel;
        }

        $sudahFk = [];
        foreach (self::relasi() as $r) {
            $sudahFk[$r['anak'].'.'.$r['kolomAnak']] = true;
        }

        $hasil = [];
        foreach (DB::select('select c.table_name, c.column_name from user_tab_columns c join user_tables t on t.table_name = c.table_name order by 1, 2') as $r) {
            $induks = $kolomKeInduk[$r->column_name] ?? [];
            if (count($induks) !== 1 || $induks[0] === $r->table_name || isset($sudahFk[$r->table_name.'.'.$r->column_name])) {
                continue;
            }
            if (ModulTabel::dari($r->table_name) === 'Sistem Laravel') {
                continue;
            }
            $hasil[] = ['anak' => $r->table_name, 'kolom' => $r->column_name, 'induk' => $induks[0], 'modul' => ModulTabel::dari($r->table_name)];
        }

        return $hasil;
    }

    /** Kolom satu tabel + tanda PK/FK. @return list<array{nama:string,tipe:string,nullable:bool,pk:bool,fk:?string,default:?string}> */
    public static function kolom(string $nama): array
    {
        $nama = strtoupper($nama);
        $pk = [];
        $fk = [];
        foreach (DB::select("
            select c.constraint_type, cc.column_name, c.r_constraint_name
              from user_constraints c
              join user_cons_columns cc on cc.constraint_name = c.constraint_name
             where c.table_name = ? and c.constraint_type in ('P','R')
        ", [$nama]) as $r) {
            if ($r->constraint_type === 'P') {
                $pk[$r->column_name] = true;
            } else {
                $induk = DB::selectOne('select table_name from user_constraints where constraint_name = ?', [$r->r_constraint_name]);
                $fk[$r->column_name] = $induk->table_name ?? '?';
            }
        }

        $hasil = [];
        foreach (DB::select('select column_name, data_type, data_length, data_precision, data_scale, nullable, data_default from user_tab_columns where table_name = ? order by column_id', [$nama]) as $r) {
            $hasil[] = [
                'nama' => $r->column_name,
                'tipe' => self::tipe($r),
                'nullable' => $r->nullable === 'Y',
                'pk' => isset($pk[$r->column_name]),
                'fk' => $fk[$r->column_name] ?? null,
                'default' => $r->data_default === null ? null : trim((string) $r->data_default),
            ];
        }

        return $hasil;
    }

    private static function tipe(object $r): string
    {
        return match ($r->data_type) {
            'NUMBER' => $r->data_precision === null
                ? 'NUMBER'
                : sprintf('NUMBER(%d%s)', $r->data_precision, $r->data_scale ? ','.$r->data_scale : ''),
            'VARCHAR2', 'CHAR', 'NVARCHAR2' => sprintf('%s(%d)', $r->data_type, $r->data_length),
            default => $r->data_type,
        };
    }

    /** Peta nama lama → baru dari berkas generator rename. @return list<array{jenis:string,lama:string,baru:string,modul:string}> */
    public static function petaRename(): array
    {
        $berkas = base_path('database/sql/rename-siklik/peta_nama.csv');
        if (! File::exists($berkas)) {
            return [];
        }
        $hasil = [];
        foreach (array_slice(File::lines($berkas)->all(), 1) as $baris) {
            if (trim($baris) === '') {
                continue;
            }
            [$jenis, $lama, $baru] = array_pad(explode(',', trim($baris)), 3, '');
            $hasil[] = ['jenis' => $jenis, 'lama' => $lama, 'baru' => $baru, 'modul' => ModulTabel::dari($baru)];
        }

        return $hasil;
    }

    /** Teks diagram Mermaid erDiagram untuk satu modul (relasi keluar dari tabel modul itu). */
    public static function mermaid(string $modul): string
    {
        $baris = ['erDiagram'];
        foreach (self::relasi() as $r) {
            if ($r['modul'] !== $modul) {
                continue;
            }
            $baris[] = sprintf('    %s ||--o{ %s : "%s"', $r['induk'], $r['anak'], $r['kolomAnak']);
        }

        return implode("\n", $baris);
    }
}
