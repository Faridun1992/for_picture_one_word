<?php

namespace App;

enum WalletTransactionReason: string
{
    case LevelReward = 'level_reward';
    case WelcomeReward = 'welcome_reward';
    case MilestoneReward = 'milestone_reward';
    case HintCost = 'hint_cost';
    case AdminAdjustment = 'admin_adjustment';
}
