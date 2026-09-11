<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstActparamedicSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['pact_id' => 'PR-PERAWAT', 'pact_desc' => 'JASA PERAWAT',                'pact_price' => 15000, 'active_status' => '1'],
            ['pact_id' => 'PR-BIDAN',   'pact_desc' => 'JASA BIDAN',                  'pact_price' => 20000, 'active_status' => '1'],
            ['pact_id' => 'PR-INJ',     'pact_desc' => 'JASA INJEKSI / SUNTIK',       'pact_price' => 10000, 'active_status' => '1'],
            ['pact_id' => 'PR-INFUS',   'pact_desc' => 'JASA PASANG INFUS',           'pact_price' => 25000, 'active_status' => '1'],
            ['pact_id' => 'PR-NEBU',    'pact_desc' => 'JASA NEBULIZER',              'pact_price' => 30000, 'active_status' => '1'],
            ['pact_id' => 'PR-EKG',    'pact_desc' => 'JASA EKG',                    'pact_price' => 50000, 'active_status' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_actparamedics')->updateOrInsert(['pact_id' => $row['pact_id']], $row);
        }
    }
}
