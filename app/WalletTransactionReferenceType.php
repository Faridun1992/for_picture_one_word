<?php

namespace App;

enum WalletTransactionReferenceType: string
{
    case Level = 'level';
    case CorrectAnswer = 'correct_answer';
    case LevelCompletion = 'level_completion';
    case LevelMilestone = 'level_milestone';
    case PlayerWelcome = 'player_welcome';
    case LevelHint = 'level_hint';
}
