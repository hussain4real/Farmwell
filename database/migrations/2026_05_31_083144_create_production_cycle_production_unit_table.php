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
        Schema::create('production_cycle_production_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['production_cycle_id', 'production_unit_id'], 'cycle_unit_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_cycle_production_unit');
    }
};
