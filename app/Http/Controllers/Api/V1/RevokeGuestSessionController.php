<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeGuestSessionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $player = $request->user();

        if (! $player instanceof Player) {
            throw new AuthorizationException;
        }

        $token = $player->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthorizationException;
        }

        $token->delete();

        return response()->noContent();
    }
}
