<?php

namespace App\Service;

use App\Entity\Event;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CardRepository $cardRepository,
    ) {
    }

    /**
     * Unassign all players from an event (removes players from event).
     */
    public function unassignAllPlayersForEvent(Event $event): void
    {
        foreach ($event->getPlayers() as $player) {
            $player->setEvent(null);
            $this->em->persist($player);
        }

        $this->em->flush();
    }
}
