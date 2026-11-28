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
            $table->string('status')->default('ready');
            $table->string('failure_message')->nullable();
            $table->timestamp('generated_at')->nullable();
        });
        DB::table('report_artifacts')->update(['generated_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('report_artifacts')->where('status', '!=', 'ready')->delete();
        Schema::table('report_artifacts', function (Blueprint $table) {
            $table->dropColumn(['status', 'failure_message', 'generated_at']);
        });
    }
};
