<?php
namespace App\Service;

use App\Entity\Draw;
use App\Entity\Game;
use App\Repository\DrawRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DrawService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DrawRepository $drawRepository,
    ) {}

    /**
     * Remove all draws for a game and flush.
     */
    public function clearAll(Game $game): void
    {
        foreach ($game->getDraws()->toArray() as $d) {
            $game->removeDraw($d);
            $this->em->remove($d);
        }
        $this->em->persist($game);
        $this->em->flush();
    }

    /**
     * Toggle a number for a game.
     * - If not drawn: append as next orderIndex
     * - If already drawn: remove it and re-pack order indexes
     * Returns the updated list of draw numbers ordered.
     *
     * @return int[]
     */
    public function toggleNumber(Game $game, int $number): array
    {
        if ($number < 1 || $number > 90) {
            throw new \InvalidArgumentException('Number must be between 1 and 90');
        }

        // Find if exists
        $existing = null;
        foreach ($game->getDraws() as $d) {
            if ($d->getNumber() === $number) { $existing = $d; break; }
        }

        if ($existing) {
            $game->removeDraw($existing);
            $this->em->remove($existing);
            $this->repackOrder($game);
        } else {
            $order = 0;
            foreach ($game->getDraws() as $d) { $order = max($order, $d->getOrderIndex()); }
            $draw = (new Draw())
                ->setGame($game)
                ->setNumber($number)
                ->setOrderIndex($order + 1);
            $game->addDraw($draw);
            $this->em->persist($draw);
        }

        $this->em->persist($game);
        $this->em->flush();

        $ordered = [];
        foreach ($game->getDraws() as $d) { $ordered[$d->getOrderIndex()] = $d->getNumber(); }
        ksort($ordered);
        return array_values($ordered);
    }

    private function repackOrder(Game $game): void
    {
        $draws = $game->getDraws()->toArray();
        usort($draws, fn(Draw $a, Draw $b) => $a->getOrderIndex() <=> $b->getOrderIndex());
        $i = 1;
        foreach ($draws as $d) {
            $d->setOrderIndex($i++);
            $this->em->persist($d);
        }
    }
}
