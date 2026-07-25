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
        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('harvested_on');
            $table->string('stage')->default('single');
            $table->unsignedSmallInteger('sequence_number')->default(1);
            $table->decimal('quantity', 12, 2);
            $table->string('quantity_unit');
            $table->text('quality_notes')->nullable();
            $table->unsignedBigInteger('labour_cost_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status')->default('recorded');
            $table->string('investor_visibility_status')->default('private');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'farm_id', 'production_cycle_id']);
            $table->index(['team_id', 'commodity_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
            $table->index(['team_id', 'harvested_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
    }
};
