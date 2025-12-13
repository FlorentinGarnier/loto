<?php
namespace App\Service;

use App\Entity\Card;
use App\Entity\Game;
use App\Enum\RuleType;

final class WinnerDetectionService
{
    /**
     * Returns the cards that currently meet the rule for the given game based on its draws.
     * Does NOT persist winners; used to display "gagnants potentiels".
     *
     * @return array<int, array{card: Card, matchedLines: int}>
     */
    public function findPotentialWinners(Game $game, array $cards): array
    {
        $drawn = [];
        foreach ($game->getDraws() as $d) {
            $drawn[$d->getNumber()] = true;
        }

        $result = [];
        foreach ($cards as $card) {
            $linesMatched = 0;
            $grid = $card->getGrid(); // expected 3 arrays of 5 ints
            for ($i = 0; $i < 3; $i++) {
                $line = $grid[$i] ?? [];
                if (\count($line) !== 5) { continue; }
                $ok = true;
                foreach ($line as $n) {
                    if (!isset($drawn[$n])) { $ok = false; break; }
                }
                if ($ok) { $linesMatched++; }
            }

            if ($this->meetsRule($game->getRule(), $linesMatched)) {
                $result[] = [ 'card' => $card, 'matchedLines' => $linesMatched ];
            }
        }
        return $result;
    }

    private function meetsRule(RuleType $rule, int $linesMatched): bool
    {
        return match ($rule) {
            RuleType::LINE => $linesMatched >= 1,
            RuleType::DOUBLE_LINE => $linesMatched >= 2,
            RuleType::FULL_CARD => $linesMatched >= 3,
        };
    }
}
