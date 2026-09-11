<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_kasirs', function (Blueprint $table) {
            $table->string('kasir_id', 25);
            $table->primary('kasir_id', 'pk_tkmst_kasirs');
            $table->string('kasir_name', 100)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_kasirs');
    }
};
