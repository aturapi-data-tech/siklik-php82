<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsmstJobSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['job_id' => 1,  'job_name' => 'BELUM BEKERJA'],
            ['job_id' => 2,  'job_name' => 'PNS'],
            ['job_id' => 3,  'job_name' => 'TNI/POLRI'],
            ['job_id' => 4,  'job_name' => 'KARYAWAN SWASTA'],
            ['job_id' => 5,  'job_name' => 'WIRASWASTA'],
            ['job_id' => 6,  'job_name' => 'PETANI'],
            ['job_id' => 7,  'job_name' => 'NELAYAN'],
            ['job_id' => 8,  'job_name' => 'BURUH'],
            ['job_id' => 9,  'job_name' => 'PEDAGANG'],
            ['job_id' => 10, 'job_name' => 'PELAJAR/MAHASISWA'],
            ['job_id' => 11, 'job_name' => 'IBU RUMAH TANGGA'],
            ['job_id' => 12, 'job_name' => 'PENSIUNAN'],
            ['job_id' => 13, 'job_name' => 'GURU/DOSEN'],
            ['job_id' => 14, 'job_name' => 'DOKTER'],
            ['job_id' => 99, 'job_name' => 'LAINNYA'],
        ];

        foreach ($rows as $row) {
            DB::table('skmst_jobs')->updateOrInsert(['job_id' => $row['job_id']], $row);
        }
    }
}
