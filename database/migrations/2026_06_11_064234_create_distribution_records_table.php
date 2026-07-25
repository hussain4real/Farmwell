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
        Schema::create('distribution_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investor_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('sale_gross_amount_minor');
            $table->unsignedBigInteger('sale_net_amount_minor');
            $table->unsignedBigInteger('previous_capital_recovered_minor')->default(0);
            $table->unsignedBigInteger('capital_recovered_minor')->default(0);
            $table->unsignedBigInteger('unrecovered_capital_minor')->default(0);
            $table->unsignedBigInteger('gross_profit_minor')->default(0);
            $table->unsignedBigInteger('net_profit_minor')->default(0);
            $table->unsignedSmallInteger('investor_profit_share_percentage');
            $table->unsignedSmallInteger('farm_profit_share_percentage');
            $table->unsignedBigInteger('investor_share_minor')->default(0);
            $table->unsignedBigInteger('farm_share_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status')->default('pending_acknowledgement');
            $table->string('investor_visibility_status')->default('pending_approval');
            $table->timestamp('calculated_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['investor_agreement_id', 'sale_record_id']);
            $table->index(['team_id', 'farm_id', 'production_cycle_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_records');
    }
};
