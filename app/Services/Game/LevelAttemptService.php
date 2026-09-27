<?php

namespace App\Services\Game;

use App\Models\Level;
use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use App\Support\Api\ApiException;
use App\Support\Game\LevelProgressSnapshot;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;
use Illuminate\Support\Facades\DB;

/**
 * Server side evaluation of one answer submission. Correctness is computed from
 * the level translation and never taken from the client, and the reward is
 * granted at most once per level.
 */
class LevelAttemptService
{
    public function __construct(
        private readonly AnswerNormalizer $answerNormalizer,
        private readonly PlayableLevelResolver $playableLevelResolver,
        private readonly WalletBalanceManager $walletBalanceManager,
        private readonly LevelCompletionRewardService $levelCompletionRewardService,
        private readonly CurrentLevelResolver $currentLevelResolver,
        private readonly LevelProgressRepository $levelProgressRepository,
        private readonly LevelProgressSnapshot $progressSnapshot,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     *
     * @throws ApiException
     */
    public function attempt(Player $player, Level $level, string $locale, string $answer): array
    {
        $translation = $this->playableLevelResolver->translation($level, $locale);
        $isCorrect = $this->answerNormalizer->normalize($answer)
            === $this->answerNormalizer->normalize($translation->answer_display);

        return DB::transaction(function () use ($player, $level, $isCorrect): array {
            $progress = $this->levelProgressRepository->lockOrCreate($player, $level);

            if ($progress->status === PlayerLevelProgressStatus::Completed) {
                if (! $isCorrect) {
                    throw ApiException::conflict('level_already_completed', 'The level is already completed.');
                }

                return $this->completedResponse(
                    $player,
                    $progress,
                    $level,
                    awardedCoins: 0,
                    milestones: [],
                    alreadyCompleted: true,
                );
            }

            $progress->forceFill(['attempt_count' => $progress->attempt_count + 1])->save();

            if (! $isCorrect) {
                return [
                    'status' => 200,
                    'body' => [
                        'data' => [
                            'level_id' => (int) $level->id,
                            'correct' => false,
                            'progress' => $this->progressSnapshot->make($progress, $level),
                        ],
                    ],
                ];
            }

            $correctAnswerReward = (int) config('game.rewards.correct_answer');
            $this->walletBalanceManager->credit(
                player: $player,
                amount: $correctAnswerReward,
                reason: WalletTransactionReason::LevelReward,
                referenceType: WalletTransactionReferenceType::CorrectAnswer,
                referenceId: (int) $level->id,
            );
            $completionReward = $this->levelCompletionRewardService->complete($player, $progress);

            return $this->completedResponse(
                $player,
                $progress,
                $level,
                $correctAnswerReward + $completionReward['coins'],
                $completionReward['milestones'],
                alreadyCompleted: false,
            );
        });
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function completedResponse(
        Player $player,
        PlayerLevelProgress $progress,
        Level $level,
        int $awardedCoins,
        array $milestones,
        bool $alreadyCompleted,
    ): array {
        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'level_id' => (int) $level->id,
                    'correct' => true,
                    'already_completed' => $alreadyCompleted,
                    'completed_at' => $progress->completed_at?->toISOString(),
                    'reward' => [
                        'coins' => $awardedCoins,
                        'correct_answer' => $alreadyCompleted ? 0 : (int) config('game.rewards.correct_answer'),
                        'level_completion' => $alreadyCompleted ? 0 : (int) config('game.rewards.level_completion'),
                        'milestones' => $milestones,
                        'balance' => $this->walletBalanceManager->balance($player),
                    ],
                    'next_level_id' => $this->currentLevelResolver->forPlayer($player),
                    'progress' => $this->progressSnapshot->make($progress, $level),
                ],
            ],
        ];
    }
}
