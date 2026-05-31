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
        Schema::create('commodity_production_cycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->decimal('expected_output_quantity', 12, 2)->nullable();
            $table->string('expected_output_unit')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['production_cycle_id', 'commodity_id'], 'cycle_commodity_unique');
            $table->index(['commodity_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commodity_production_cycle');
    }
};
