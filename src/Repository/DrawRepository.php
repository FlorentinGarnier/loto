<?php

namespace App\Repository;

use App\Entity\Draw;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Draw>
 */
class DrawRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Draw::class);
    }

    /** @return Draw[] */
    public function findByGameOrdered(Game $game): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.game = :g')
            ->setParameter('g', $game)
            ->orderBy('d.orderIndex', 'ASC')
            ->getQuery()->getResult();
    }
}
