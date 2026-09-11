<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstParameterSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['par_id' => 1, 'par_desc' => 'HARI KONTROL DEFAULT',         'par_value' => 7],
            ['par_id' => 2, 'par_desc' => 'BATAS HARI ANTRIAN MOBILE',     'par_value' => 30],
            ['par_id' => 3, 'par_desc' => 'TARIF ADMIN DEFAULT',           'par_value' => 5000],
            ['par_id' => 4, 'par_desc' => 'PPN PERSEN',                    'par_value' => 11],
            ['par_id' => 5, 'par_desc' => 'TIMEOUT API BPJS (DETIK)',     'par_value' => 30],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_parameters')->updateOrInsert(['par_id' => $row['par_id']], $row);
        }
    }
}
