<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstKlaimtypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['klaim_id' => 'BPJS',  'klaim_desc' => 'BPJS KESEHATAN',          'klaim_status' => 'AKTIF'],
            ['klaim_id' => 'UMUM',  'klaim_desc' => 'PASIEN UMUM',             'klaim_status' => 'AKTIF'],
            ['klaim_id' => 'JR',    'klaim_desc' => 'JASA RAHARJA',            'klaim_status' => 'AKTIF'],
            ['klaim_id' => 'INHEL', 'klaim_desc' => 'IN HEALTH',               'klaim_status' => 'AKTIF'],
            ['klaim_id' => 'BPJTK', 'klaim_desc' => 'BPJS KETENAGAKERJAAN',    'klaim_status' => 'AKTIF'],
            ['klaim_id' => 'ASR',   'klaim_desc' => 'ASURANSI SWASTA',         'klaim_status' => 'AKTIF'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_klaimtypes')->updateOrInsert(['klaim_id' => $row['klaim_id']], $row);
        }
    }
}
