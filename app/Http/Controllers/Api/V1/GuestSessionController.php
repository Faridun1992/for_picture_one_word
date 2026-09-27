<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGuestSessionRequest;
use App\Models\Player;
use App\Services\Game\WalletBalanceManager;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GuestSessionController extends Controller
{
    public function __invoke(StoreGuestSessionRequest $request, WalletBalanceManager $walletBalanceManager): JsonResponse
    {
        $validated = $request->validated();

        [$player, $token] = DB::transaction(function () use ($validated, $walletBalanceManager): array {
            $player = Player::query()->create([
                'locale' => $validated['locale'] ?? 'ru',
            ]);

            $walletBalanceManager->credit(
                player: $player,
                amount: (int) config('game.wallet.starting_balance'),
                reason: WalletTransactionReason::WelcomeReward,
                referenceType: WalletTransactionReferenceType::PlayerWelcome,
                referenceId: (int) $player->id,
            );
            $token = $player->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

            return [$player, $token];
        });

        return response()->json([
            'data' => [
                'token' => $token,
                'player' => [
                    'id' => $player->id,
                    'locale' => $player->locale,
                ],
            ],
        ], 201);
    }
}
