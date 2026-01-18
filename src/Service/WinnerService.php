<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\BlockedReason;
use App\Enum\WinnerSource;
use Doctrine\ORM\EntityManagerInterface;

final class WinnerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Valide un gagnant système (depuis la détection automatique).
     *
     * @throws \RuntimeException Si la partie n'est pas gelée ou si le carton est bloqué
     */
    public function validateSystemWinner(Game $game, Card $card): Winner
    {
        if (!$game->isFrozen()) {
            throw new \RuntimeException('Cannot validate winner on non-frozen game');
        }

        if ($card->isBlocked()) {
            throw new \RuntimeException('Cannot validate blocked card');
        }

        // Vérifier qu'il n'y a pas déjà de gagnant avec ce carton pour cette partie
        foreach ($game->getWinners() as $existingWinner) {
            if ($existingWinner->getCard() === $card) {
                throw new \RuntimeException('Card already validated as winner for this game');
            }
        }

        $winner = new Winner();
        $winner->setGame($game);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::SYSTEM);
        $winner->setReference($card->getReference());
        $winner->setWinningOrderIndex($game->getFreezeOrderIndex() ?? 0);

        // Bloquer le carton avec la raison appropriée
        $card->block(BlockedReason::WINNER_VALIDATED);

        $this->em->persist($winner);
        $this->em->persist($card);
        $game->addWinner($winner);
        $this->em->persist($game);
        $this->em->flush();

        return $winner;
    }

    /**
     * Valide un gagnant salle (saisie manuelle offline)
     * Gèle automatiquement la partie si ce n'est pas déjà fait.
     */
    public function validateOfflineWinner(Game $game, string $reference, ?Card $card = null): Winner
    {
        // Geler la partie si ce n'est pas déjà fait
        if (!$game->isFrozen()) {
            $maxOrderIndex = 0;
            foreach ($game->getDraws() as $draw) {
                $maxOrderIndex = max($maxOrderIndex, $draw->getOrderIndex());
            }
            $game->freeze($maxOrderIndex);
        }

        if ($card && $card->isBlocked()) {
            throw new \RuntimeException('Cannot validate blocked card');
        }

        $winner = new Winner();
        $winner->setGame($game);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::OFFLINE);
        $winner->setReference($reference);
        $winner->setWinningOrderIndex($game->getFreezeOrderIndex() ?? 0);

        // Bloquer le carton si présent avec la raison appropriée
        if ($card) {
            $card->block(BlockedReason::WINNER_OFFLINE);
            $this->em->persist($card);
        }

        $this->em->persist($winner);
        $game->addWinner($winner);
        $this->em->persist($game);
        $this->em->flush();

        return $winner;
    }

    /**
     * Remove all winners for all games of an event.
     */
    public function clearAllForEvent(Event $event): void
    {
        foreach ($event->getGames() as $game) {
            foreach ($game->getWinners()->toArray() as $winner) {
                // Débloquer le carton si présent
                if ($winner->getCard() && $winner->getCard()->isBlocked()) {
                    $winner->getCard()->unblock();
                    $this->em->persist($winner->getCard());
                }

                $game->removeWinner($winner);
                $this->em->remove($winner);
            }
            $this->em->persist($game);
        }
        $this->em->flush();
    }
}
