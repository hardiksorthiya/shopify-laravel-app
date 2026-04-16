<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_price_entries', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->index();
            $table->unsignedBigInteger('variant_id');

            $table->string('metal_type')->default('gold');
            $table->string('gold_karat')->default('10kt');
            $table->decimal('metal_weight', 12, 2)->default(0);

            // Stored exactly as the dropdown value (quality|color|min_ct|max_ct|price).
            $table->string('diamond_quality_value')->nullable();
            $table->decimal('diamond_weight', 12, 2)->default(0);

            $table->decimal('making_charge', 12, 2)->default(0);
            $table->decimal('computed_total', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['shop', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_price_entries');
    }
};

