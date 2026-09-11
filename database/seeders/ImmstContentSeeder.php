<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImmstContentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['cont_id' => 'TAB',  'cont_desc' => 'TABLET'],
            ['cont_id' => 'CAP',  'cont_desc' => 'KAPSUL'],
            ['cont_id' => 'BTL',  'cont_desc' => 'BOTOL'],
            ['cont_id' => 'BOX',  'cont_desc' => 'BOX'],
            ['cont_id' => 'TUBE', 'cont_desc' => 'TUBE'],
            ['cont_id' => 'AMP',  'cont_desc' => 'AMPUL'],
            ['cont_id' => 'VIAL', 'cont_desc' => 'VIAL'],
            ['cont_id' => 'SCHT', 'cont_desc' => 'SACHET'],
            ['cont_id' => 'STRP', 'cont_desc' => 'STRIP'],
            ['cont_id' => 'BLST', 'cont_desc' => 'BLISTER'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_contents')->updateOrInsert(['cont_id' => $row['cont_id']], $row);
        }
    }
}
