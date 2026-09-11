<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstOutSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['out_no' => 'SMBH', 'out_desc' => 'SEMBUH'],
            ['out_no' => 'BAIK', 'out_desc' => 'MEMBAIK'],
            ['out_no' => 'RUJK', 'out_desc' => 'RUJUK KE FASKES LAIN'],
            ['out_no' => 'PLPS', 'out_desc' => 'PULANG PAKSA'],
            ['out_no' => 'MENG', 'out_desc' => 'MENINGGAL'],
            ['out_no' => 'KBUR', 'out_desc' => 'KABUR / TIDAK KEMBALI'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_outs')->updateOrInsert(['out_no' => $row['out_no']], $row);
        }
    }
}
