<?php

namespace App;

enum WalletTransactionReason: string
{
    case LevelReward = 'level_reward';
    case HintCost = 'hint_cost';
    case AdminAdjustment = 'admin_adjustment';
}
