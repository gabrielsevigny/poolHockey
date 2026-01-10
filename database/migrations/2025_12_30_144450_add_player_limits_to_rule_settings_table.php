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
        Schema::table('rule_settings', function (Blueprint $table) {
            $table->integer('max_players_per_user')->nullable()->after('points_per_victory');
            $table->json('position_limits')->nullable()->after('max_players_per_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rule_settings', function (Blueprint $table) {
            $table->dropColumn(['max_players_per_user', 'position_limits']);
        });
    }
};
