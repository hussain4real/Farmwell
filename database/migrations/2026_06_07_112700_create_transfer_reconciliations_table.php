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
        Schema::create('transfer_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reconciled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->unsignedBigInteger('reconciled_amount_minor');
            $table->char('currency', 3);
            $table->timestamp('reconciled_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['external_transfer_id', 'reconciled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_reconciliations');
    }
};
