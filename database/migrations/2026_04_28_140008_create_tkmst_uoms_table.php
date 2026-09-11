<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_uoms', function (Blueprint $table) {
            $table->string('uom_id', 25);
            $table->primary('uom_id', 'pk_tkmst_uoms');
            $table->string('uom_desc', 100)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_uoms');
    }
};
