<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TkmstKasirSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['kasir_id' => 'KSR-01', 'kasir_name' => 'KASIR APOTEK 1',    'active_status' => '1'],
            ['kasir_id' => 'KSR-02', 'kasir_name' => 'KASIR APOTEK 2',    'active_status' => '1'],
            ['kasir_id' => 'KSR-RJ', 'kasir_name' => 'KASIR RAWAT JALAN', 'active_status' => '1'],
            ['kasir_id' => 'KSR-LB', 'kasir_name' => 'KASIR LABORATORIUM','active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_kasirs')->updateOrInsert(['kasir_id' => $row['kasir_id']], $row);
        }
    }
}
