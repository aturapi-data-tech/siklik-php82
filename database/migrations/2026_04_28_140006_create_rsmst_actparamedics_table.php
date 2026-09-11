<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skmst_actparamedics', function (Blueprint $table) {
            $table->string('pact_id', 10);
            $table->primary('pact_id', 'pk_rsmst_actparamedics');
            $table->string('pact_desc', 100)->nullable();
            $table->decimal('pact_price', 9, 0)->nullable();
            $table->string('active_status', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skmst_actparamedics');
    }
};
