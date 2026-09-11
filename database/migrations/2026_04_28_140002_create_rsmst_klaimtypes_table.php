<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_klaimtypes', function (Blueprint $table) {
            $table->string('klaim_id', 5);
            $table->primary('klaim_id', 'pk_rsmst_klaimtypes');
            $table->string('klaim_desc', 50)->nullable();
            $table->string('klaim_status', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_klaimtypes');
    }
};
