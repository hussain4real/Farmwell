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
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->date('log_date');
            $table->string('domain');
            $table->unsignedInteger('mortality_count')->default(0);
            $table->unsignedInteger('cull_count')->default(0);
            $table->unsignedInteger('graded_out_count')->default(0);
            $table->decimal('feed_used_kg', 10, 2)->nullable();
            $table->string('feed_type')->nullable();
            $table->string('feed_pellet_size_mm')->nullable();
            $table->decimal('water_used_liters', 10, 2)->nullable();
            $table->decimal('average_weight_g', 10, 2)->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->unsignedInteger('eggs_collected')->nullable();
            $table->unsignedInteger('eggs_damaged')->nullable();
            $table->decimal('water_ph', 4, 2)->nullable();
            $table->decimal('water_temp_c', 5, 2)->nullable();
            $table->decimal('dissolved_oxygen_mg_l', 5, 2)->nullable();
            $table->decimal('ammonia_mg_l', 5, 3)->nullable();
            $table->boolean('water_exchanged')->default(false);
            $table->string('growth_stage')->nullable();
            $table->decimal('rainfall_mm', 6, 2)->nullable();
            $table->boolean('irrigation_applied')->default(false);
            $table->string('pest_pressure')->nullable();
            $table->string('disease_observed')->nullable();
            $table->string('input_applied_name')->nullable();
            $table->decimal('input_applied_quantity', 10, 2)->nullable();
            $table->string('input_applied_unit')->nullable();
            $table->string('weather_notes')->nullable();
            $table->text('notes')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'farm_id', 'log_date']);
            $table->index(['production_cycle_id', 'log_date']);
            $table->index(['team_id', 'domain', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_logs');
    }
};
