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
     * Unassign all players from all cards of an event.
     */
    public function unassignAllPlayersForEvent(Event $event): void
    {
        $cards = $this->cardRepository->findByEvent($event);

        foreach ($cards as $card) {
            $card->setPlayer(null);
            $this->em->persist($card);
        }

        $this->em->flush();
    }
}
