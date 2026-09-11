<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_outs', function (Blueprint $table) {
            $table->string('out_no', 5);
            $table->primary('out_no', 'pk_rsmst_outs');
            $table->string('out_desc', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_outs');
    }
};
