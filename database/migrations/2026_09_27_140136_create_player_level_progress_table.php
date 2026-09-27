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
        Schema::create('player_level_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('in_progress');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('hints_used')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'level_id']);
            $table->index(['player_id', 'status', 'level_id']);
            $table->index(['level_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_level_progress');
    }
};
