<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Dump data master siklik (pasien, tindakan, obat) ke JSON untuk
 * diisi ke template migrasi Infokes (Excel).
 *
 * Langkah lengkap:
 *   php artisan migrasi:infokes-dump
 *   python3 scripts/migrasi-infokes/fill_templates.py \
 *       --dump storage/app/migrasi-infokes/dump \
 *       --templates ~/Downloads \
 *       --out storage/app/migrasi-infokes/out
 */
class MigrasiInfokesDump extends Command
{
    protected $signature = 'migrasi:infokes-dump
        {--out=storage/app/migrasi-infokes/dump : Folder output JSON}
        {--all-status : Ikutkan tindakan/obat yang non-aktif}';

    protected $description = 'Dump master pasien, tindakan, dan obat siklik ke JSON untuk template migrasi Infokes';

    public function handle(): int
    {
        $outDir = base_path($this->option('out'));
        File::ensureDirectoryExists($outDir);

        $this->write($outDir, 'klinik.json', $this->klinik());

        $pasien = $this->pasien();
        $this->write($outDir, 'pasien.json', $pasien);
        $this->info('pasien   : ' . count($pasien));

        $tindakan = $this->tindakan();
        $this->write($outDir, 'tindakan.json', $tindakan);
        $this->info('tindakan : ' . count($tindakan));

        $obat = $this->obat();
        $this->write($outDir, 'obat.json', $obat);
        $this->info('obat     : ' . count($obat));

        $this->line("Output: {$outDir}");

        return self::SUCCESS;
    }

    private function write(string $dir, string $file, array $data): void
    {
        File::put($dir . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function klinik(): array
    {
        $row = DB::table('dimst_identitases')->select('int_name', 'int_city', 'int_address', 'int_phone1')->first();

        return [
            'nama'   => trim((string) ($row->int_name ?? '')),
            'kota'   => trim((string) ($row->int_city ?? '')),
            'alamat' => trim((string) ($row->int_address ?? '')),
            'telp'   => trim((string) ($row->int_phone1 ?? '')),
        ];
    }

    /**
     * Semua pasien, LEFT JOIN ke lookup supaya baris dengan FK kosong tetap ikut.
     * Umur tidak diekspor: template hanya butuh tgl lahir.
     */
    private function pasien(): array
    {
        return DB::table('rsmst_pasiens as p')
            ->leftJoin('rsmst_desas as d', 'd.des_id', '=', 'p.des_id')
            ->leftJoin('rsmst_kecamatans as k', 'k.kec_id', '=', 'p.kec_id')
            ->leftJoin('rsmst_kabupatens as kb', 'kb.kab_id', '=', 'p.kab_id')
            ->leftJoin('rsmst_propinsis as pr', 'pr.prop_id', '=', 'p.prop_id')
            ->leftJoin('rsmst_jobs as j', 'j.job_id', '=', 'p.job_id')
            ->leftJoin('rsmst_religions as r', 'r.rel_id', '=', 'p.rel_id')
            ->leftJoin('rsmst_educations as e', 'e.edu_id', '=', 'p.edu_id')
            ->select([
                'p.reg_no', 'p.reg_name', 'p.no_kk', 'p.nik_bpjs', 'p.nokartu_bpjs', 'p.sex',
                'p.birth_place',
                DB::raw("to_char(p.birth_date, 'yyyy-mm-dd') as birth_date"),
                'p.address', 'p.rt', 'p.rw',
                'd.des_name', 'k.kec_name', 'kb.kab_name', 'pr.prop_name',
                'p.blood', 'j.job_name', 'p.marital_status', 'p.phone',
                'r.rel_desc', 'e.edu_desc', 'p.kk', 'p.nyonya',
            ])
            ->orderBy('p.reg_no')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Tindakan siklik tersebar di 4 tabel tarif. Digabung jadi satu list
     * dengan kolom kelompok + kode asal supaya bisa ditelusuri balik.
     */
    private function tindakan(): array
    {
        $sources = [
            ['table' => 'rsmst_actparamedics', 'id' => 'pact_id',   'desc' => 'pact_desc',   'price' => 'pact_price',   'kelompok' => 'Tindakan Paramedis'],
            ['table' => 'rsmst_accdocs',       'id' => 'accdoc_id', 'desc' => 'accdoc_desc', 'price' => 'accdoc_price', 'kelompok' => 'Tindakan Dokter'],
            ['table' => 'rsmst_actemps',       'id' => 'acte_id',   'desc' => 'acte_desc',   'price' => 'acte_price',   'kelompok' => 'Jasa Karyawan'],
            ['table' => 'rsmst_others',        'id' => 'other_id',  'desc' => 'other_desc',  'price' => 'other_price',  'kelompok' => 'Administrasi / Lain-lain'],
        ];

        $rows = [];
        foreach ($sources as $s) {
            $q = DB::table($s['table'])
                ->select([
                    DB::raw("{$s['id']} as kode"),
                    DB::raw("{$s['desc']} as nama"),
                    DB::raw("{$s['price']} as tarif"),
                    'active_status',
                ])
                ->orderBy($s['desc']);

            if (! $this->option('all-status')) {
                $q->where('active_status', '1');
            }

            foreach ($q->get() as $r) {
                $rows[] = [
                    'kelompok'      => $s['kelompok'],
                    'tabel'         => $s['table'],
                    'kode'          => (string) $r->kode,
                    'nama'          => trim((string) $r->nama),
                    'tarif'         => $r->tarif === null ? null : (float) $r->tarif,
                    'active_status' => (string) $r->active_status,
                ];
            }
        }

        return $rows;
    }

    private function obat(): array
    {
        $q = DB::table('tkmst_products as p')
            ->leftJoin('tkmst_categories as c', 'c.cat_id', '=', 'p.cat_id')
            ->leftJoin('tkmst_uoms as u', 'u.uom_id', '=', 'p.uom_id')
            ->select([
                'p.product_id', 'p.product_name', 'p.product_type',
                'p.uom_id', 'u.uom_desc', 'p.cat_id', 'c.cat_desc',
                'p.sales_price', 'p.active_status',
            ])
            ->orderBy('p.product_name');

        if (! $this->option('all-status')) {
            $q->where('p.active_status', '1');
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }
}
