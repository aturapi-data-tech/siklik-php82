<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_entrytypes', function (Blueprint $table) {
            $table->string('entry_id', 3);
            $table->primary('entry_id', 'pk_rsmst_entrytypes');
            $table->string('entry_desc', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_entrytypes');
    }
};
