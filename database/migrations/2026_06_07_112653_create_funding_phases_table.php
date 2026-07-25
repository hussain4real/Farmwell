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
        Schema::create('funding_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('milestone')->nullable();
            $table->string('status')->default('draft');
            $table->char('currency', 3);
            $table->unsignedBigInteger('planned_amount_minor')->default(0);
            $table->unsignedBigInteger('requested_amount_minor')->default(0);
            $table->unsignedBigInteger('approved_amount_minor')->default(0);
            $table->unsignedBigInteger('externally_released_amount_minor')->default(0);
            $table->date('expected_on')->nullable();
            $table->date('released_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'farm_id', 'production_cycle_id']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_phases');
    }
};
