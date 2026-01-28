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
     * If the game is frozen, only checks at the freezeOrderIndex.
     * Excludes blocked cards.
     *
     * @return array<int, array{card: Card, matchedLines: int}>
     */
    public function findPotentialWinners(Game $game, array $cards): array
    {
        if ($game->hallOnly()) {
            return [];
        }

        // Si la partie est gelée, on ne considère que les tirages jusqu'au gel
        $maxOrderIndex = $game->isFrozen() && null !== $game->getFreezeOrderIndex()
            ? $game->getFreezeOrderIndex()
            : PHP_INT_MAX;

        $drawn = [];
        foreach ($game->getDraws() as $d) {
            if ($d->getOrderIndex() <= $maxOrderIndex) {
                $drawn[$d->getNumber()] = true;
            }
        }

        $result = [];
        foreach ($cards as $card) {
            // Exclure les cartons bloqués
            if ($card->isBlocked()) {
                continue;
            }

            $linesMatched = 0;
            $grid = $card->getGrid(); // expected 3 arrays of 5 ints
            for ($i = 0; $i < 3; ++$i) {
                $line = $grid[$i] ?? [];
                if (5 !== \count($line)) {
                    continue;
                }
                $ok = true;
                foreach ($line as $n) {
                    if (!isset($drawn[$n])) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    ++$linesMatched;
                }
            }

            if ($this->meetsRule($game->getRule(), $linesMatched)) {
                $result[] = ['card' => $card, 'matchedLines' => $linesMatched];
            }
        }

        return $result;
    }

    /**
     * Vérifie si des gagnants existent et retourne le orderIndex du gel si nécessaire.
     *
     * @return int|null L'orderIndex auquel geler, ou null si pas de gagnant
     */
    public function checkForWinners(Game $game, array $cards): ?int
    {
        $potentialWinners = $this->findPotentialWinners($game, $cards);

        if (count($potentialWinners) > 0) {
            // Retourner le orderIndex du dernier tirage
            $maxOrderIndex = 0;
            foreach ($game->getDraws() as $draw) {
                $maxOrderIndex = max($maxOrderIndex, $draw->getOrderIndex());
            }

            return $maxOrderIndex;
        }

        return null;
    }

    private function meetsRule(RuleType $rule, int $linesMatched): bool
    {
        return match ($rule) {
            RuleType::QUINE => $linesMatched >= 1,
            RuleType::DOUBLE_QUINE => $linesMatched >= 2,
            RuleType::FULL_CARD => $linesMatched >= 3,
        };
    }
}
