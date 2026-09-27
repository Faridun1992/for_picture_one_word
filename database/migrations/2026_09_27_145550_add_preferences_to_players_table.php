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
        Schema::table('players', function (Blueprint $table): void {
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('haptics_enabled')->default(true);
            $table->string('theme', 8)->default('system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['sound_enabled', 'haptics_enabled', 'theme']);
        });
    }
};
