<?php
namespace App\Enum;

enum GameStatus: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case FINISHED = 'FINISHED';
}
