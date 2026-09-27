<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base of the game API requests that work with localized content: the locale is
 * optional and defaults to the locale of the authenticated player.
 */
abstract class PlayerLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function selectedLocale(): string
    {
        $locale = $this->validated()['locale'] ?? null;
        $player = $this->user();

        return $locale ?? ($player instanceof Player ? $player->locale : 'ru');
    }

    /** @return array<string, array<int, mixed>> */
    protected function localeRules(): array
    {
        return ['locale' => ['sometimes', 'required', 'string', Rule::in(config('game.supported_locales'))]];
    }
}
