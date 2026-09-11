<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstMstprocedureSeeder extends Seeder
{
    public function run(): void
    {
        // ICD-9-CM contoh — minimal sample
        $rows = [
            ['proc_id' => '89.00', 'proc_desc' => 'KONSULTASI UMUM'],
            ['proc_id' => '89.03', 'proc_desc' => 'PEMERIKSAAN FISIK LENGKAP'],
            ['proc_id' => '89.07', 'proc_desc' => 'PEMERIKSAAN TEKANAN DARAH'],
            ['proc_id' => '99.04', 'proc_desc' => 'INJEKSI ATAU INFUS HORMON'],
            ['proc_id' => '99.10', 'proc_desc' => 'INJEKSI ATAU INFUS NEFROTOKSIK'],
            ['proc_id' => '99.21', 'proc_desc' => 'INJEKSI ANTIBIOTIK'],
            ['proc_id' => '99.28', 'proc_desc' => 'IMUNISASI'],
            ['proc_id' => '93.94', 'proc_desc' => 'NEBULISASI / RESPIRATORY THERAPY'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_mstprocedures')->updateOrInsert(['proc_id' => $row['proc_id']], $row);
        }
    }
}
