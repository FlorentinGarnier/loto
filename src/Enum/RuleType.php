<?php
namespace App\Enum;

enum RuleType: string
{
    case LINE = 'LINE';
    case DOUBLE_LINE = 'DOUBLE_LINE';
    case FULL_CARD = 'FULL_CARD';
}
