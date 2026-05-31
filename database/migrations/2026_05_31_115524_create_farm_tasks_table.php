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
        Schema::create('farm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('activity_type')->nullable();
            $table->text('description')->nullable();
            $table->date('planned_for')->nullable();
            $table->date('due_on');
            $table->timestamp('reminder_at')->nullable();
            $table->string('status')->default('planned');
            $table->text('status_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('investor_visible')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'due_on', 'status']);
            $table->index(['farm_id', 'production_cycle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_tasks');
    }
};
