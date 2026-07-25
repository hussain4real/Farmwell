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
        Schema::create('sale_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('harvest_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('sold_on');
            $table->string('buyer_name');
            $table->decimal('quantity', 12, 2);
            $table->string('quantity_unit');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('deduction_amount_minor')->default(0);
            $table->unsignedBigInteger('net_amount_minor');
            $table->char('currency', 3);
            $table->string('payment_status')->default('pending');
            $table->string('reference')->nullable();
            $table->string('investor_visibility_status')->default('private');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'farm_id', 'production_cycle_id']);
            $table->index(['team_id', 'harvest_record_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
            $table->index(['team_id', 'sold_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_records');
    }
};
