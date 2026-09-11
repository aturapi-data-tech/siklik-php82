<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstEntrytypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['entry_id' => 'DS',  'entry_desc' => 'DATANG SENDIRI'],
            ['entry_id' => 'RJK', 'entry_desc' => 'RUJUKAN PUSKESMAS'],
            ['entry_id' => 'RJD', 'entry_desc' => 'RUJUKAN DOKTER'],
            ['entry_id' => 'RJR', 'entry_desc' => 'RUJUKAN RUMAH SAKIT'],
            ['entry_id' => 'EMG', 'entry_desc' => 'EMERGENCY/UGD'],
            ['entry_id' => 'AMB', 'entry_desc' => 'AMBULANCE'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_entrytypes')->updateOrInsert(['entry_id' => $row['entry_id']], $row);
        }
    }
}
