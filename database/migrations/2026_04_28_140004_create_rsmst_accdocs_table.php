<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_accdocs', function (Blueprint $table) {
            $table->string('accdoc_id', 10);
            $table->primary('accdoc_id', 'pk_rsmst_accdocs');
            $table->string('accdoc_desc', 50)->nullable();
            $table->decimal('accdoc_price', 9, 0)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_accdocs');
    }
};
