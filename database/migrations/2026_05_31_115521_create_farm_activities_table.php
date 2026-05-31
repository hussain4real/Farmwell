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
        Schema::create('farm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commodity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('activity_date');
            $table->string('activity_type');
            $table->text('description');
            $table->text('inputs_used')->nullable();
            $table->text('labour_used')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->text('next_activity')->nullable();
            $table->string('status')->default('planned');
            $table->text('internal_notes')->nullable();
            $table->text('investor_safe_summary')->nullable();
            $table->string('source_type')->default('manual');
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'activity_date']);
            $table->index(['farm_id', 'production_cycle_id']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_activities');
    }
};
