<?php

namespace App\Services\Game;

use App\Models\Player;
use App\Models\PlayerLevelProgress;
use App\PlayerLevelProgressStatus;
use App\WalletTransactionReason;
use App\WalletTransactionReferenceType;

/**
 * Completes one riddle and applies its server configured completion and
 * one-time solved-count milestone rewards inside the caller's transaction.
 */
class LevelCompletionRewardService
{
    public function __construct(private readonly WalletBalanceManager $walletBalanceManager) {}

    /**
     * @return array{coins: int, milestones: array<int, int>}
     */
    public function complete(Player $player, PlayerLevelProgress $progress): array
    {
        $progress->forceFill([
            'status' => PlayerLevelProgressStatus::Completed,
            'completed_at' => now(),
        ])->save();

        $levelCompletionReward = (int) config('game.rewards.level_completion');
        $this->walletBalanceManager->credit(
            player: $player,
            amount: $levelCompletionReward,
            reason: WalletTransactionReason::LevelReward,
            referenceType: WalletTransactionReferenceType::LevelCompletion,
            referenceId: (int) $progress->level_id,
        );
        $milestones = [];
        $solvedCount = PlayerLevelProgress::query()
            ->where('player_id', $player->id)
            ->where('status', PlayerLevelProgressStatus::Completed->value)
            ->count();

        foreach (config('game.rewards.milestones', []) as $threshold => $amount) {
            if ($solvedCount !== (int) $threshold) {
                continue;
            }

            $reward = (int) $amount;
            $this->walletBalanceManager->credit(
                player: $player,
                amount: $reward,
                reason: WalletTransactionReason::MilestoneReward,
                referenceType: WalletTransactionReferenceType::LevelMilestone,
                referenceId: (int) $threshold,
            );
            $milestones[(int) $threshold] = $reward;
        }

        $coins = $levelCompletionReward + array_sum($milestones);

        return ['coins' => $coins, 'milestones' => $milestones];
    }
}
