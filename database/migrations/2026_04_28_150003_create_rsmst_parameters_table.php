<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_parameters', function (Blueprint $table) {
            $table->integer('par_id');
            $table->primary('par_id', 'pk_rsmst_parameters');
            $table->string('par_desc', 100)->nullable();
            $table->decimal('par_value', 9, 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_parameters');
    }
};
