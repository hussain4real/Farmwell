<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 16)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 16)->nullable()->index();
        });
    }

    public function down(): void
    {
        // Restoring required emails must fail before removing any phone identities.
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
        Schema::table('team_invitations', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn('phone');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at']);
        });
    }
};
