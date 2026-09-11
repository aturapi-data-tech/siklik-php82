<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_jobs', function (Blueprint $table) {
            $table->integer('job_id');
            $table->primary('job_id', 'pk_rsmst_jobs');
            $table->string('job_name', 25)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_jobs');
    }
};
