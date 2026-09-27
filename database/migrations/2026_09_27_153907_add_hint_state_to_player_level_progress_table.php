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
        Schema::table('player_level_progress', function (Blueprint $table) {
            $table->json('hint_state')->nullable()->after('hints_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_level_progress', function (Blueprint $table) {
            $table->dropColumn('hint_state');
        });
    }
};
