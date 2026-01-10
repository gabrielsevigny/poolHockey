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
            $table->json('rules')->nullable()->after('position_limits');
            $table->string('template_type')->nullable()->after('rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rule_settings', function (Blueprint $table) {
            $table->dropColumn(['rules', 'template_type']);
        });
    }
};
