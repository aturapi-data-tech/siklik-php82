<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_actemps', function (Blueprint $table) {
            $table->string('acte_id', 10);
            $table->primary('acte_id', 'pk_rsmst_actemps');
            $table->string('acte_desc', 100)->nullable();
            $table->decimal('acte_price', 9, 0)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_actemps');
    }
};
