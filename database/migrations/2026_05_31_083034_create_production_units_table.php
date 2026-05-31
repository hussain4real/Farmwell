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
        Schema::create('production_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit_type');
            $table->decimal('size', 12, 2)->nullable();
            $table->string('size_unit')->nullable();
            $table->string('capacity')->nullable();
            $table->string('status')->default('available');
            $table->string('gps_coordinates')->nullable();
            $table->text('suitability_notes')->nullable();
            $table->text('history_notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
            $table->index(['team_id', 'farm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_units');
    }
};
