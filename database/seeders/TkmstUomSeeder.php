<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TkmstUomSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['uom_id' => 'PCS',   'uom_desc' => 'PIECES',     'active_status' => '1'],
            ['uom_id' => 'BOX',   'uom_desc' => 'BOX',        'active_status' => '1'],
            ['uom_id' => 'BOTOL', 'uom_desc' => 'BOTOL',      'active_status' => '1'],
            ['uom_id' => 'TUBE',  'uom_desc' => 'TUBE',       'active_status' => '1'],
            ['uom_id' => 'STRIP', 'uom_desc' => 'STRIP',      'active_status' => '1'],
            ['uom_id' => 'TAB',   'uom_desc' => 'TABLET',     'active_status' => '1'],
            ['uom_id' => 'CAP',   'uom_desc' => 'KAPSUL',     'active_status' => '1'],
            ['uom_id' => 'AMP',   'uom_desc' => 'AMPUL',      'active_status' => '1'],
            ['uom_id' => 'VIAL',  'uom_desc' => 'VIAL',       'active_status' => '1'],
            ['uom_id' => 'ML',    'uom_desc' => 'MILILITER',  'active_status' => '1'],
            ['uom_id' => 'GR',    'uom_desc' => 'GRAM',       'active_status' => '1'],
            ['uom_id' => 'MG',    'uom_desc' => 'MILIGRAM',   'active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_uoms')->updateOrInsert(['uom_id' => $row['uom_id']], $row);
        }
    }
}
