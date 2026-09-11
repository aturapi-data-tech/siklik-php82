<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstEducationSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['edu_id' => 1, 'edu_desc' => 'TIDAK SEKOLAH'],
            ['edu_id' => 2, 'edu_desc' => 'SD'],
            ['edu_id' => 3, 'edu_desc' => 'SMP'],
            ['edu_id' => 4, 'edu_desc' => 'SMA'],
            ['edu_id' => 5, 'edu_desc' => 'D3'],
            ['edu_id' => 6, 'edu_desc' => 'S1'],
            ['edu_id' => 7, 'edu_desc' => 'S2'],
            ['edu_id' => 8, 'edu_desc' => 'S3'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_educations')->updateOrInsert(
                ['edu_id' => $row['edu_id']],
                ['edu_desc' => $row['edu_desc']]
            );
        }
    }
}
