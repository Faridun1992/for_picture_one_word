<?php

namespace App;

enum HintType: string
{
    case RevealLetter = 'reveal_letter';
    case RemoveWrongLetters = 'remove_wrong_letters';
    case RevealAnswer = 'reveal_answer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
