<?php
namespace App\Repository;

use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /** @return Game[] */
    public function findByEventOrdered(Event $event): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.event = :e')
            ->setParameter('e', $event)
            ->orderBy('g.position', 'ASC')
            ->getQuery()->getResult();
    }

    public function findRunningByEvent(Event $event): ?Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.event = :e AND g.status = :s')
            ->setParameter('e', $event)
            ->setParameter('s', GameStatus::RUNNING)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
