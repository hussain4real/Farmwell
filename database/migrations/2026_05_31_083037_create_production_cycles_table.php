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
        Schema::create('production_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('season')->nullable();
            $table->string('farm_type');
            $table->string('production_method')->nullable();
            $table->date('planned_start_on');
            $table->date('planned_end_on')->nullable();
            $table->date('actual_start_on')->nullable();
            $table->date('actual_end_on')->nullable();
            $table->decimal('expected_output_quantity', 12, 2)->nullable();
            $table->string('expected_output_unit')->nullable();
            $table->string('status')->default('planned');
            $table->unsignedInteger('plan_version')->default(1);
            $table->text('plan_summary')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
            $table->index(['team_id', 'farm_id', 'status']);
            $table->index(['planned_start_on', 'planned_end_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_cycles');
    }
};
