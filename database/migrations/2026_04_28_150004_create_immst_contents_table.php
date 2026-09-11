<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_contents', function (Blueprint $table) {
            $table->string('cont_id', 5);
            $table->primary('cont_id', 'pk_immst_contents');
            $table->string('cont_desc', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_contents');
    }
};
