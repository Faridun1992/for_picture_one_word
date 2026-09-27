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
        Schema::create('player_wallets', function (Blueprint $table) {
            $table->foreignId('player_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason', 32);
            $table->string('reference_type', 32);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('created_at');

            $table->unique(
                ['player_id', 'reason', 'reference_type', 'reference_id'],
                'wallet_transactions_player_reason_reference_unique',
            );
            $table->unique(['player_id', 'idempotency_key']);
            $table->index(['player_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('player_wallets');
    }
};
