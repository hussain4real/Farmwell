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
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('requires_investor_approval')->default(false)->after('is_active');
        });

        Schema::table('funding_phases', function (Blueprint $table) {
            $table->foreignId('investor_agreement_id')->nullable()->after('budget_id')->constrained()->nullOnDelete();
            $table->string('investor_visibility_status')->default('private')->after('status');
            $table->index(['team_id', 'investor_agreement_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('investor_agreement_id')->nullable()->after('funding_phase_id')->constrained()->nullOnDelete();
            $table->string('investor_visibility_status')->default('private')->after('status');
            $table->index(['team_id', 'investor_agreement_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
        });

        Schema::table('external_transfers', function (Blueprint $table) {
            $table->foreignId('investor_agreement_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
            $table->string('investor_visibility_status')->default('private')->after('status');
            $table->index(['team_id', 'investor_agreement_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
        });

        Schema::table('production_plan_changes', function (Blueprint $table) {
            $table->foreignId('investor_agreement_id')->nullable()->after('production_cycle_id')->constrained()->nullOnDelete();
            $table->string('investor_visibility_status')->default('private')->after('investor_safe_summary');
            $table->index(['team_id', 'investor_agreement_id']);
            $table->index(['investor_agreement_id', 'investor_visibility_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_plan_changes', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'investor_agreement_id']);
            $table->dropIndex(['investor_agreement_id', 'investor_visibility_status']);
            $table->dropConstrainedForeignId('investor_agreement_id');
            $table->dropColumn('investor_visibility_status');
        });

        Schema::table('external_transfers', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'investor_agreement_id']);
            $table->dropIndex(['investor_agreement_id', 'investor_visibility_status']);
            $table->dropConstrainedForeignId('investor_agreement_id');
            $table->dropColumn('investor_visibility_status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'investor_agreement_id']);
            $table->dropIndex(['investor_agreement_id', 'investor_visibility_status']);
            $table->dropConstrainedForeignId('investor_agreement_id');
            $table->dropColumn('investor_visibility_status');
        });

        Schema::table('funding_phases', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'investor_agreement_id']);
            $table->dropIndex(['investor_agreement_id', 'investor_visibility_status']);
            $table->dropConstrainedForeignId('investor_agreement_id');
            $table->dropColumn('investor_visibility_status');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('requires_investor_approval');
        });
    }
};
