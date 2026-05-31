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
        Schema::create('whatsapp_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commodity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('imported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_activity_id')->nullable()->constrained('farm_activities')->nullOnDelete();
            $table->text('source_message');
            $table->string('source_sender')->nullable();
            $table->date('source_date')->nullable();
            $table->date('normalized_activity_date')->nullable();
            $table->string('normalized_activity_type')->nullable();
            $table->text('normalized_description')->nullable();
            $table->decimal('normalized_cost', 12, 2)->nullable();
            $table->text('normalized_next_activity')->nullable();
            $table->text('normalized_investor_safe_summary')->nullable();
            $table->string('review_status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'review_status', 'created_at']);
            $table->index(['farm_id', 'production_cycle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_intakes');
    }
};
