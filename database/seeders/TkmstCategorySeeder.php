<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TkmstCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['cat_id' => 'OBT',   'cat_desc' => 'OBAT',                  'active_status' => '1'],
            ['cat_id' => 'ALKES', 'cat_desc' => 'ALAT KESEHATAN',        'active_status' => '1'],
            ['cat_id' => 'BHP',   'cat_desc' => 'BAHAN HABIS PAKAI',     'active_status' => '1'],
            ['cat_id' => 'IMPL',  'cat_desc' => 'IMPLAN',                'active_status' => '1'],
            ['cat_id' => 'MAKMIN','cat_desc' => 'MAKANAN & MINUMAN',     'active_status' => '1'],
            ['cat_id' => 'OBT-RAC','cat_desc' => 'OBAT RACIKAN',         'active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_categories')->updateOrInsert(['cat_id' => $row['cat_id']], $row);
        }
    }
}
