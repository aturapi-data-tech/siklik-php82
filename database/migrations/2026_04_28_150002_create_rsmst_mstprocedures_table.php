<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_mstprocedures', function (Blueprint $table) {
            $table->string('proc_id', 15);
            $table->primary('proc_id', 'pk_rsmst_mstprocedures');
            $table->string('proc_desc', 250)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_mstprocedures');
    }
};
