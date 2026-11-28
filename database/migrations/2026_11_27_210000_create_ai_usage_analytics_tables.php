<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('model_id', 128);
            $table->string('canonical_id', 192)->nullable();
            $table->string('display_name', 192);
            $table->decimal('input_cost_per_million', 12, 6);
            $table->decimal('output_cost_per_million', 12, 6);
            $table->decimal('cache_read_cost_per_million', 12, 6)->nullable();
            $table->decimal('cache_write_cost_per_million', 12, 6)->nullable();
            $table->decimal('reasoning_cost_per_million', 12, 6)->nullable();
            $table->unsignedBigInteger('context_limit')->nullable();
            $table->unsignedBigInteger('output_limit')->nullable();
            $table->json('tiers')->nullable();
            $table->string('source', 32)->default('models.dev');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
            $table->index('model_id');
        });

        Schema::create('ai_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->string('invocation_id', 128)->unique();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature', 64);
            $table->string('agent_class', 192);
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('conversation_id', 36)->nullable();
            $table->string('status', 24)->default('completed');
            $table->boolean('is_streamed')->default(false);
            $table->unsignedInteger('steps_count')->default(1);
            $table->unsignedInteger('tool_calls_count')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('uncached_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('input_cost_microdollars')->default(0);
            $table->unsignedBigInteger('output_cost_microdollars')->default(0);
            $table->unsignedBigInteger('cache_cost_microdollars')->default(0);
            $table->unsignedBigInteger('cost_microdollars')->default(0);
            $table->string('pricing_status', 24)->default('priced');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['team_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['feature', 'occurred_at']);
            $table->index(['provider', 'model', 'occurred_at']);
            $table->index('pricing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
        Schema::dropIfExists('ai_model_prices');
    }
};
