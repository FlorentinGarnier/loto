<?php

namespace App\Repository;

use App\Entity\Card;
use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    /**
     * Find all cards belonging to players assigned to an event.
     * @return Card[]
     */
    public function findByEvent(Event $event): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.player', 'p')
            ->andWhere('p.event = :e')
            ->setParameter('e', $event)
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getResult();
    }
}
