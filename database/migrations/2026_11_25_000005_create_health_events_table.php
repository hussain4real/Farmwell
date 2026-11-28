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
        Schema::create('health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->string('product_name')->nullable();
            $table->string('dosage')->nullable();
            $table->string('administration_route')->nullable();
            $table->date('scheduled_on');
            $table->date('completed_on')->nullable();
            $table->unsignedInteger('withdrawal_days')->nullable();
            $table->string('status')->default('scheduled');
            $table->unsignedInteger('affected_count')->nullable();
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'farm_id', 'status', 'scheduled_on']);
            $table->index(['production_cycle_id', 'scheduled_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_events');
    }
};
