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
        Schema::create('report_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('production_cycle_id')->nullable();
            $table->string('type');
            $table->date('period_start');
            $table->date('period_end');
            $table->longText('document');
            $table->timestamps();
            $table->index(['investor_agreement_id', 'generated_by_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_artifacts');
    }
};
