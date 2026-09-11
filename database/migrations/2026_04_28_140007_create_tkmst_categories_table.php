<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_categories', function (Blueprint $table) {
            $table->string('cat_id', 25);
            $table->primary('cat_id', 'pk_tkmst_categories');
            $table->string('cat_desc', 100)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_categories');
    }
};
