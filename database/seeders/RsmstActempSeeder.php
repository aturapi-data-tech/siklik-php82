<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstActempSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['acte_id' => 'JK-ADM',   'acte_desc' => 'JASA ADMINISTRASI',       'acte_price' => 10000, 'active_status' => '1'],
            ['acte_id' => 'JK-RM',    'acte_desc' => 'JASA REKAM MEDIS',        'acte_price' => 5000,  'active_status' => '1'],
            ['acte_id' => 'JK-KASIR', 'acte_desc' => 'JASA KASIR',              'acte_price' => 3000,  'active_status' => '1'],
            ['acte_id' => 'JK-IT',    'acte_desc' => 'JASA SISTEM INFORMASI',   'acte_price' => 5000,  'active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_actemps')->updateOrInsert(['acte_id' => $row['acte_id']], $row);
        }
    }
}
