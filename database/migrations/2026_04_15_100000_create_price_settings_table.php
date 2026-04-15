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
        Schema::create('price_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('gold_10kt', 12, 2)->default(0);
            $table->decimal('gold_14kt', 12, 2)->default(0);
            $table->decimal('gold_18kt', 12, 2)->default(0);
            $table->decimal('gold_22kt', 12, 2)->default(0);
            $table->decimal('silver_price', 12, 2)->default(0);
            $table->decimal('platinum_price', 12, 2)->default(0);
            $table->decimal('tax_percent', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_settings');
    }
};
