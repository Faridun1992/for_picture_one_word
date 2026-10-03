<?php

namespace App\Services\Game;

use App\HintType;
use App\Models\Level;
use App\Models\LevelTranslation;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use App\Support\Api\ApiException;
use App\Support\Game\LevelHintState;
use App\Support\Game\LevelProgressSnapshot;
use App\Support\Game\TileSet;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Support\Facades\DB;

/** Applies server priced hints and commits their effects with wallet changes. */
class LevelHintService
{
    public function __construct(
        private readonly AnswerNormalizer $answerNormalizer,
        private readonly PlayableLevelResolver $playableLevelResolver,
        private readonly WalletBalanceManager $walletBalanceManager,
        private readonly LevelProgressRepository $levelProgressRepository,
        private readonly LevelProgressSnapshot $progressSnapshot,
        private readonly LevelCompletionRewardService $levelCompletionRewardService,
        private readonly CurrentLevelResolver $currentLevelResolver,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     *
     * @throws ApiException
     */
    public function apply(
        Player $player,
        Level $level,
        string $locale,
        HintType $type,
        int $operationId,
    ): array {
        $translation = $this->playableLevelResolver->translation($level, $locale);

        return DB::transaction(function () use ($player, $level, $translation, $type, $operationId): array {
            $progress = $this->levelProgressRepository->lockOrCreate($player, $level);

            if ($progress->status === PlayerLevelProgressStatus::Completed) {
                throw ApiException::conflict('level_already_completed', 'The level is already completed.');
            }

            $state = LevelHintState::fromArray($progress->hint_state);
            $answerGraphemes = $this->answerNormalizer->splitGraphemes($translation->answer_display);

            $outcome = match ($type) {
                HintType::RevealLetter => $this->revealLetter($player, $progress, $state, $translation, $operationId),
                HintType::RemoveWrongLetters => $this->removeWrongLetters($player, $progress, $state, $translation, $operationId),
                HintType::RevealAnswer => $this->revealAnswer($player, $progress, $state, $answerGraphemes, $translation, $operationId),
            };

            return [
                'status' => 200,
                'body' => [
                    'data' => $this->hintResponse($player, $level, $translation, $progress, $type, $outcome),
                ],
            ];
        });
    }

    /** @return array{charged: bool, cost: int, position: ?int, removed_tiles: list<string>, state: LevelHintState, reward?: array<string, mixed>} */
    private function revealLetter(
        Player $player,
        PlayerLevelProgress $progress,
        LevelHintState $state,
        LevelTranslation $translation,
        int $operationId,
    ): array {
        $answer = $this->answerNormalizer->splitGraphemes($translation->answer_display);
        $target = $this->firstUnrevealedPosition($state, $answer);

        if ($target === null) {
            return ['charged' => false, 'cost' => 0, 'position' => null, 'removed_tiles' => [], 'state' => $state];
        }

        $cost = (int) config('game.hints.reveal_letter');
        $this->charge($player, $cost, $operationId);
        $updatedState = $state->withRevealedPosition($target);
        $this->remember($progress, $updatedState);

        return ['charged' => true, 'cost' => $cost, 'position' => $target, 'removed_tiles' => [], 'state' => $updatedState];
    }

    /** @return array{charged: bool, cost: int, position: ?int, removed_tiles: list<string>, state: LevelHintState} */
    private function removeWrongLetters(
        Player $player,
        PlayerLevelProgress $progress,
        LevelHintState $state,
        LevelTranslation $translation,
        int $operationId,
    ): array {
        $tiles = array_values($translation->letter_tiles);
        $answerTiles = $this->answerTiles($translation);
        $wrongTiles = $answerTiles->takeFrom($tiles);
        $availableWrongTiles = TileSet::fromTiles($state->removedTiles())->takeFrom($wrongTiles);
        $removeCount = max(0, (int) config('game.hints.remove_wrong_letters_count'));
        $removedTiles = array_slice($availableWrongTiles, 0, $removeCount);

        if ($removedTiles === []) {
            return ['charged' => false, 'cost' => 0, 'position' => null, 'removed_tiles' => [], 'state' => $state];
        }

        $cost = (int) config('game.hints.remove_wrong_letters');
        $this->charge($player, $cost, $operationId);
        $updatedState = $state->withRemovedTiles($removedTiles);
        $this->remember($progress, $updatedState);

        return ['charged' => true, 'cost' => $cost, 'position' => null, 'removed_tiles' => $removedTiles, 'state' => $updatedState];
    }

    /** @param list<string> $answerGraphemes
     * @return array{charged: bool, cost: int, position: ?int, removed_tiles: list<string>, state: LevelHintState, reward: array<string, mixed>}
     */
    private function revealAnswer(
        Player $player,
        PlayerLevelProgress $progress,
        LevelHintState $state,
        array $answerGraphemes,
        LevelTranslation $translation,
        int $operationId,
    ): array {
        $cost = (int) config('game.hints.reveal_answer');
        $this->charge($player, $cost, $operationId);
        $revealedPositions = [];

        foreach ($answerGraphemes as $index => $grapheme) {
            if (! $this->isSeparator($grapheme)) {
                $revealedPositions[] = $index + 1;
            }
        }

        $updatedState = LevelHintState::fromArray([
            'revealed_positions' => $revealedPositions,
            'removed_tiles' => $state->removedTiles(),
        ]);
        $progress->forceFill([
            'hints_used' => $progress->hints_used + 1,
            'hint_state' => $updatedState->toArray(),
        ])->save();
        $reward = $this->levelCompletionRewardService->complete($player, $progress);

        return [
            'charged' => true,
            'cost' => $cost,
            'position' => null,
            'removed_tiles' => [],
            'state' => $updatedState,
            'reward' => $reward,
            'answer' => $translation->answer_display,
        ];
    }

    private function charge(Player $player, int $cost, int $operationId): void
    {
        $this->walletBalanceManager->debit(
            player: $player,
            amount: $cost,
            reason: WalletTransactionReason::HintCost,
            referenceType: WalletTransactionReferenceType::LevelHint,
            referenceId: $operationId,
        );
    }

    /** @param list<string> $answer */
    private function firstUnrevealedPosition(LevelHintState $state, array $answer): ?int
    {
        foreach ($answer as $index => $grapheme) {
            $position = $index + 1;

            if (! $state->isPositionRevealed($position) && ! $this->isSeparator($grapheme)) {
                return $position;
            }
        }

        return null;
    }

    private function isSeparator(string $grapheme): bool
    {
        return preg_match('/^[\p{Z}\p{C}]+$/u', $grapheme) === 1;
    }

    private function answerTiles(LevelTranslation $translation): TileSet
    {
        return TileSet::fromTiles($this->answerNormalizer->splitGraphemes($translation->answer_display));
    }

    private function remember(PlayerLevelProgress $progress, LevelHintState $state): void
    {
        $progress->forceFill([
            'hints_used' => $progress->hints_used + 1,
            'hint_state' => $state->toArray(),
        ])->save();
    }

    /**
     * @param  array{charged: bool, cost: int, position: ?int, removed_tiles: list<string>, state: LevelHintState, reward?: array<string, mixed>, answer?: string}  $outcome
     * @return array<string, mixed>
     */
    private function hintResponse(
        Player $player,
        Level $level,
        LevelTranslation $translation,
        PlayerLevelProgress $progress,
        HintType $type,
        array $outcome,
    ): array {
        $state = $outcome['state'];
        $answerGraphemes = $this->answerNormalizer->splitGraphemes($translation->answer_display);
        $revealed = [];

        foreach ($state->revealedPositions() as $revealedPosition) {
            $revealed[$revealedPosition] = $answerGraphemes[$revealedPosition - 1] ?? null;
        }

        $data = [
            'level_id' => (int) $level->id,
            'type' => $type->value,
            'charged' => $outcome['charged'],
            'cost' => $outcome['cost'],
            'balance' => $this->walletBalanceManager->balance($player),
            'hints_used' => (int) $progress->hints_used,
            'answer_length' => count($answerGraphemes),
            'letter_tiles' => isset($outcome['answer'])
                ? []
                : $state->remainingTiles(array_values($translation->letter_tiles), $answerGraphemes),
            'revealed' => $revealed,
            'position' => $outcome['position'],
            'removed_tiles' => $outcome['removed_tiles'],
            'progress' => $this->progressSnapshot->make($progress, $level),
        ];

        if (isset($outcome['reward'])) {
            $data['reward'] = [
                ...$outcome['reward'],
                'balance' => $data['balance'],
            ];
            $data['next_level_id'] = $this->currentLevelResolver->forPlayer($player);
        }

        if (isset($outcome['answer'])) {
            $data['answer'] = $outcome['answer'];
        }

        return $data;
    }
}
