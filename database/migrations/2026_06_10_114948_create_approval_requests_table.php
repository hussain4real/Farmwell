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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('request_type');
            $table->string('trigger_type');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('threshold_amount_minor')->nullable();
            $table->unsignedBigInteger('requested_amount_minor')->default(0);
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->char('currency', 3);
            $table->text('requester_comment')->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'request_type']);
            $table->index(['team_id', 'investor_agreement_id']);
            $table->index(['decided_by_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
