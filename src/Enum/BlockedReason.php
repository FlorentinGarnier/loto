<?php

namespace App\Enum;

enum BlockedReason: string
{
    case WINNER = 'WINNER';
    case WINNER_VALIDATED = 'WINNER_VALIDATED';
    case WINNER_OFFLINE = 'WINNER_OFFLINE';
}
