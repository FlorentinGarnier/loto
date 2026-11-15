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
     *
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

    /**
     * Carton attribué à un joueur de l'événement, cherché par sa référence (sans tenir compte de la casse).
     */
    public function findOneByReferenceForEvent(string $reference, Event $event): ?Card
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.player', 'p')
            ->andWhere('p.event = :e')
            ->andWhere('LOWER(c.reference) = :ref')
            ->setParameter('e', $event)
            ->setParameter('ref', mb_strtolower(trim($reference)))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
