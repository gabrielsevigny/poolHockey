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
        Schema::create('rule_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('points_per_goal')->default(2);
            $table->integer('points_per_assist')->default(1);
            $table->integer('points_per_shutout')->default(3);
            $table->integer('points_per_victory')->default(2);
            $table->integer('points_per_defeat')->default(0);
            $table->integer('points_per_overtime')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_settings');
    }
};
