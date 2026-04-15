<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('diamond_prices', function (Blueprint $table) {
            $table->id();
            $table->string('quality');
            $table->string('color');
            $table->decimal('min_ct', 8, 2)->default(0);
            $table->decimal('max_ct', 8, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diamond_prices');
    }
};
