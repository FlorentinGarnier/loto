<?php

namespace App\Service;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;

final class WinnerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Remove all winners for all games of an event.
     */
    public function clearAllForEvent(Event $event): void
    {
        foreach ($event->getGames() as $game) {
            foreach ($game->getWinners()->toArray() as $winner) {
                $game->removeWinner($winner);
                $this->em->remove($winner);
            }
            $this->em->persist($game);
        }
        $this->em->flush();
    }
}
