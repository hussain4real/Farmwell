<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_artifacts', function (Blueprint $table) {
            $table->unsignedBigInteger('investor_agreement_id')->nullable()->change();
            $table->json('filters')->nullable();
            $table->index(['team_id', 'generated_by_id', 'type', 'id'], 'report_artifacts_generator_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('report_artifacts')->whereNull('investor_agreement_id')->delete();
        Schema::table('report_artifacts', function (Blueprint $table) {
            $table->dropIndex('report_artifacts_generator_index');
            $table->dropColumn('filters');
            $table->unsignedBigInteger('investor_agreement_id')->nullable(false)->change();
        });
    }
};
