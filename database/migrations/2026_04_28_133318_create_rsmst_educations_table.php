<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_educations', function (Blueprint $table) {
            $table->integer('edu_id');
            $table->primary('edu_id', 'pk_rsmst_educations');
            $table->string('edu_desc', 25)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_educations');
    }
};
