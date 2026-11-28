<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_summaries', function (Blueprint $table): void {
            $table->foreignId('investor_agreement_id')->nullable()->change();
            $table->string('type')->default('investor_progress');
            $table->foreignId('farm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('production_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_intake_id')->nullable()->constrained('whatsapp_intakes')->nullOnDelete();
            $table->text('input_text')->nullable();
            $table->json('source_records')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('generation_context')->nullable();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('ai_summaries')->whereNull('investor_agreement_id')->exists()) {
            throw new RuntimeException('Agreement-free summaries must be removed before rollback.');
        }

        Schema::table('ai_summaries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_cycle_id');
            $table->dropConstrainedForeignId('farm_id');
            $table->dropConstrainedForeignId('whatsapp_intake_id');
            $table->dropConstrainedForeignId('published_by_id');
            $table->dropColumn(['type', 'input_text', 'source_records', 'source_snapshot', 'generation_context', 'published_at']);
            $table->foreignId('investor_agreement_id')->nullable(false)->change();
        });
    }
};
