<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstAccdocSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['accdoc_id' => 'JD-UMUM',  'accdoc_desc' => 'JASA DOKTER UMUM',         'accdoc_price' => 50000,  'active_status' => '1'],
            ['accdoc_id' => 'JD-SP',    'accdoc_desc' => 'JASA DOKTER SPESIALIS',    'accdoc_price' => 100000, 'active_status' => '1'],
            ['accdoc_id' => 'JD-GIGI',  'accdoc_desc' => 'JASA DOKTER GIGI',         'accdoc_price' => 75000,  'active_status' => '1'],
            ['accdoc_id' => 'JD-UGD',   'accdoc_desc' => 'JASA DOKTER UGD',          'accdoc_price' => 80000,  'active_status' => '1'],
            ['accdoc_id' => 'JD-VISIT', 'accdoc_desc' => 'JASA VISITE DOKTER',       'accdoc_price' => 60000,  'active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_accdocs')->updateOrInsert(['accdoc_id' => $row['accdoc_id']], $row);
        }
    }
}
