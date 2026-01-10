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
        Schema::create('pool_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('nhl_player_id'); // NHL API player ID
            $table->string('player_name');
            $table->string('position');
            $table->string('team_abbrev');
            $table->string('team_name');
            $table->string('headshot_url')->nullable();
            $table->integer('draft_order')->nullable(); // Order in which player was drafted
            $table->timestamps();

            // Unique constraint: same player can't be selected twice in same pool
            $table->unique(['pool_id', 'nhl_player_id']);
            // Index for quick lookups
            $table->index(['pool_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_players');
    }
};
