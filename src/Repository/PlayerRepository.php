<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * Joueurs avec leurs cartons, filtrés sur le nom, le courriel ou le téléphone.
     *
     * @return Player[]
     */
    public function search(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.cards', 'c')->addSelect('c')
            ->leftJoin('p.event', 'e')->addSelect('e')
            ->orderBy('p.name', 'ASC');

        if ('' !== $search) {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.email) LIKE :q OR p.phone LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
