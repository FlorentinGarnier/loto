<?php

namespace App\Service;

use App\Entity\Game;
use App\Enum\WinnerSource;
use App\Repository\CardRepository;

/**
 * État d'une partie tel qu'il est envoyé aux écrans : flux Mercure,
 * réponse au tirage et resynchronisation après une coupure.
 */
final class GameStateBuilder
{
    public function __construct(
        private readonly CardRepository $cardRepo,
        private readonly WinnerDetectionService $winnerDetectionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Game $game): array
    {
        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $potentials = $this->winnerDetectionService->findPotentialWinners($game, $cards);

        $hasSystemWinner = false;
        foreach ($game->getWinners() as $winner) {
            if (WinnerSource::SYSTEM === $winner->getSource()) {
                $hasSystemWinner = true;
                break;
            }
        }

        // Le carton s'affiche dès qu'un gagnant potentiel est détecté (partie gelée)
        $detectedCard = null;
        if ($game->isFrozen() && \count($potentials) > 0) {
            $card = $potentials[0]['card'];
            $detectedCard = [
                'reference' => $card->getReference(),
                'grid' => $card->getFormattedGrid(),
                'player' => $card->getPlayer()?->getName(),
            ];
        }

        return [
            'eventId' => $game->getEvent()?->getId(),
            'gameId' => $game->getId(),
            'position' => $game->getPosition(),
            'rule' => $game->getRule()->value,
            'prize' => $game->getPrize(),
            'status' => $game->getStatus()->value,
            'draws' => array_map(fn ($d) => $d->getNumber(), $game->getDraws()->toArray()),
            'potentialsCount' => \count($potentials),
            'isFrozen' => $game->isFrozen(),
            'freezeOrderIndex' => $game->getFreezeOrderIndex(),
            'hasSystemWinner' => $hasSystemWinner,
            'detectedCard' => $detectedCard,
        ];
    }
}
