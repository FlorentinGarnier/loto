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
        private readonly WinnerDetectionService $winnerDetectionService,
    ) {
    }

    /**
     * Remove all draws for a game, unfreeze it, and flush.
     * Also unblocks all cards that were blocked as winners.
     */
    public function clearAll(Game $game): void
    {
        foreach ($game->getDraws()->toArray() as $d) {
            $game->removeDraw($d);
            $this->em->remove($d);
        }

        // Dégeler la partie
        $game->unfreeze();

        // Débloquer tous les cartons gagnants de cette partie
        foreach ($game->getWinners() as $winner) {
            if ($winner->getCard() && $winner->getCard()->isBlocked()) {
                $winner->getCard()->unblock();
                $this->em->persist($winner->getCard());
            }
            $game->removeWinner($winner);
            $this->em->remove($winner);
        }

        $this->em->persist($game);
        $this->em->flush();
    }

    /**
     * Remove all draws for all games of an event.
     */
    public function clearAllForEvent(\App\Entity\Event $event): void
    {
        foreach ($event->getGames() as $game) {
            foreach ($game->getDraws()->toArray() as $d) {
                $game->removeDraw($d);
                $this->em->remove($d);
            }
            $this->em->persist($game);
        }
        $this->em->flush();
    }

    /**
     * Toggle a number for a game.
     * - If not drawn: append as next orderIndex and check for winners (auto-freeze)
     * - If already drawn: remove it and re-pack order indexes
     * Returns the updated list of draw numbers ordered.
     *
     * @param array $cards Les cartons à vérifier pour la détection des gagnants
     *
     * @return array{numbers: int[], frozen: bool, freezeOrderIndex: int|null}
     */
    public function toggleNumber(Game $game, int $number, array $cards = []): array
    {
        if ($number < 1 || $number > 90) {
            throw new \InvalidArgumentException('Number must be between 1 and 90');
        }

        // Vérifier si la partie est gelée
        if ($game->isFrozen()) {
            throw new \RuntimeException('Cannot toggle numbers on a frozen game');
        }

        // Find if exists
        $existing = null;
        foreach ($game->getDraws() as $d) {
            if ($d->getNumber() === $number) {
                $existing = $d;
                break;
            }
        }

        if ($existing) {
            $game->removeDraw($existing);
            $this->em->remove($existing);
            $this->repackOrder($game);
        } else {
            $order = 0;
            foreach ($game->getDraws() as $d) {
                $order = max($order, $d->getOrderIndex());
            }
            $draw = (new Draw())
                ->setGame($game)
                ->setNumber($number)
                ->setOrderIndex($order + 1);
            $game->addDraw($draw);
            $this->em->persist($draw);

            // Détection automatique des gagnants si on a des cartons et que la partie n'est pas "salle uniquement"
            if (!$game->hallOnly() && count($cards) > 0) {
                $freezeOrderIndex = $this->winnerDetectionService->checkForWinners($game, $cards);
                if (null !== $freezeOrderIndex) {
                    $game->freeze($freezeOrderIndex);
                }
            }
        }

        $this->em->persist($game);
        $this->em->flush();

        $ordered = [];
        foreach ($game->getDraws() as $d) {
            $ordered[$d->getOrderIndex()] = $d->getNumber();
        }
        ksort($ordered);

        return [
            'numbers' => array_values($ordered),
            'frozen' => $game->isFrozen(),
            'freezeOrderIndex' => $game->getFreezeOrderIndex(),
        ];
    }

    private function repackOrder(Game $game): void
    {
        $draws = $game->getDraws()->toArray();
        usort($draws, fn (Draw $a, Draw $b) => $a->getOrderIndex() <=> $b->getOrderIndex());
        $i = 1;
        foreach ($draws as $d) {
            $d->setOrderIndex($i++);
            $this->em->persist($d);
        }
    }
}
