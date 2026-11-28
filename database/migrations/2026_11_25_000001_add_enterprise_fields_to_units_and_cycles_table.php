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
        Schema::table('production_units', function (Blueprint $table) {
            $table->string('water_source')->nullable()->after('gps_coordinates');
            $table->string('housing_system')->nullable()->after('water_source');
        });

        Schema::table('production_cycles', function (Blueprint $table) {
            $table->string('enterprise_subtype')->nullable()->after('farm_type');
            $table->string('breed_or_variety')->nullable()->after('enterprise_subtype');
            $table->unsignedInteger('initial_stock_count')->nullable()->after('expected_output_unit');
            $table->unsignedInteger('current_stock_count')->nullable()->after('initial_stock_count');
            $table->decimal('initial_average_weight_g', 10, 2)->nullable()->after('current_stock_count');
            $table->decimal('target_weight_g', 10, 2)->nullable()->after('initial_average_weight_g');
            $table->string('current_growth_stage')->nullable()->after('target_weight_g');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_cycles', function (Blueprint $table) {
            $table->dropColumn([
                'enterprise_subtype',
                'breed_or_variety',
                'initial_stock_count',
                'current_stock_count',
                'initial_average_weight_g',
                'target_weight_g',
                'current_growth_stage',
            ]);
        });

        Schema::table('production_units', function (Blueprint $table) {
            $table->dropColumn(['water_source', 'housing_system']);
        });
    }
};
