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
        Schema::create('investor_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_committed_minor')->default(0);
            $table->unsignedBigInteger('amount_funded_minor')->default(0);
            $table->string('capital_recovery_rule')->default('capital_first');
            $table->unsignedTinyInteger('investor_profit_share_percentage')->default(40);
            $table->unsignedTinyInteger('farm_profit_share_percentage')->default(60);
            $table->string('funding_model')->nullable();
            $table->text('role_responsibilities')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'investor_id', 'status']);
            $table->index(['team_id', 'farm_id', 'production_cycle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investor_agreements');
    }
};
