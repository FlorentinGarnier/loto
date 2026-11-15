<?php

namespace spec\App\Service;

use App\Entity\Card;
use App\Entity\Draw;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\BlockedReason;
use App\Enum\RuleType;
use App\Repository\DrawRepository;
use App\Service\DrawService;
use App\Service\WinnerDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DrawServiceSpec extends ObjectBehavior
{
    private const GRID = [
        [1, 12, 23, 34, 45],
        [6, 17, 28, 39, 50],
        [40, 51, 62, 73, 84],
    ];

    function let(EntityManagerInterface $em, DrawRepository $drawRepository)
    {
        // WinnerDetectionService est final (non doublable) et sans dépendance : on utilise l'instance réelle
        $this->beConstructedWith($em, $drawRepository, new WinnerDetectionService());
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(DrawService::class);
    }

    function it_rejects_numbers_below_1()
    {
        $this->shouldThrow(\InvalidArgumentException::class)->during('toggleNumber', [new Game(), 0]);
    }

    function it_rejects_numbers_above_90()
    {
        $this->shouldThrow(\InvalidArgumentException::class)->during('toggleNumber', [new Game(), 91]);
    }

    function it_accepts_the_boundary_numbers_1_and_90()
    {
        $game = new Game();

        $this->toggleNumber($game, 1);
        $this->toggleNumber($game, 90)->shouldReturn([
            'numbers' => [1, 90],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_refuses_to_toggle_a_number_on_a_frozen_game()
    {
        $game = $this->createGame([1, 2]);
        $game->freeze(2);

        $this->shouldThrow(\RuntimeException::class)->during('toggleNumber', [$game, 3]);
    }

    function it_draws_a_number_that_was_not_drawn_yet(EntityManagerInterface $em)
    {
        $game = new Game();

        $this->toggleNumber($game, 42)->shouldReturn([
            'numbers' => [42],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);

        $em->persist(Argument::type(Draw::class))->shouldHaveBeenCalledOnce();
        $em->flush()->shouldHaveBeenCalled();
    }

    function it_appends_new_draws_in_drawing_order()
    {
        $game = new Game();

        $this->toggleNumber($game, 42);
        $this->toggleNumber($game, 7);
        $this->toggleNumber($game, 90)->shouldReturn([
            'numbers' => [42, 7, 90],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);

        $this->ensure(
            [1, 2, 3] === $this->orderIndexes($game),
            'Les tirages doivent être numérotés 1, 2, 3 dans l\'ordre de sortie.'
        );
    }

    function it_removes_a_number_that_was_already_drawn(EntityManagerInterface $em)
    {
        $game = $this->createGame([42, 7]);
        $removed = $game->getDraws()->first();

        $this->toggleNumber($game, 42)->shouldReturn([
            'numbers' => [7],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);

        $em->remove($removed)->shouldHaveBeenCalledOnce();
    }

    function it_repacks_order_indexes_after_removing_a_number()
    {
        $game = $this->createGame([42, 7, 90]);

        $this->toggleNumber($game, 7)->shouldReturn([
            'numbers' => [42, 90],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);

        $this->ensure(
            [1, 2] === $this->orderIndexes($game),
            'Les index d\'ordre doivent être recompactés sans trou après un décochage.'
        );
    }

    function it_freezes_the_game_as_soon_as_a_card_wins()
    {
        $game = $this->createGame([1, 12, 23, 34]);
        $card = $this->createCard('A001');

        $this->toggleNumber($game, 45, [$card])->shouldReturn([
            'numbers' => [1, 12, 23, 34, 45],
            'frozen' => true,
            'freezeOrderIndex' => 5,
        ]);
    }

    function it_does_not_freeze_the_game_while_no_card_wins()
    {
        $game = $this->createGame([1, 12, 23]);

        $this->toggleNumber($game, 34, [$this->createCard('A001')])->shouldReturn([
            'numbers' => [1, 12, 23, 34],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_does_not_detect_winners_when_no_card_is_given()
    {
        $game = $this->createGame([1, 12, 23, 34]);

        $this->toggleNumber($game, 45)->shouldReturn([
            'numbers' => [1, 12, 23, 34, 45],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_does_not_detect_winners_on_a_hall_only_game()
    {
        $game = $this->createGame([1, 12, 23, 34]);
        $game->setHallOnly(true);

        $this->toggleNumber($game, 45, [$this->createCard('A001')])->shouldReturn([
            'numbers' => [1, 12, 23, 34, 45],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_does_not_freeze_the_game_for_a_blocked_card()
    {
        $game = $this->createGame([1, 12, 23, 34]);
        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);

        $this->toggleNumber($game, 45, [$card])->shouldReturn([
            'numbers' => [1, 12, 23, 34, 45],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_applies_the_rule_of_the_game_when_detecting_winners()
    {
        // Une seule ligne complète ne suffit pas pour une double quine
        $game = $this->createGame([1, 12, 23, 34]);
        $game->setRule(RuleType::DOUBLE_QUINE);

        $this->toggleNumber($game, 45, [$this->createCard('A001')])->shouldReturn([
            'numbers' => [1, 12, 23, 34, 45],
            'frozen' => false,
            'freezeOrderIndex' => null,
        ]);
    }

    function it_clears_all_draws_of_a_game(EntityManagerInterface $em)
    {
        $game = $this->createGame([1, 2, 3]);

        $this->clearAll($game);

        $this->ensure($game->getDraws()->isEmpty(), 'La démarque doit supprimer tous les tirages.');
        $em->remove(Argument::type(Draw::class))->shouldHaveBeenCalledTimes(3);
        $em->flush()->shouldHaveBeenCalled();
    }

    function it_unfreezes_the_game_when_clearing_it()
    {
        $game = $this->createGame([1, 2, 3]);
        $game->freeze(3);

        $this->clearAll($game);

        $this->ensure(!$game->isFrozen(), 'La démarque doit dégeler la partie.');
        $this->ensure(null === $game->getFreezeOrderIndex(), 'La démarque doit effacer l\'index de gel.');
    }

    function it_removes_winners_and_unblocks_their_cards_when_clearing_a_game(EntityManagerInterface $em)
    {
        $game = $this->createGame([1, 12, 23, 34, 45]);
        $game->freeze(5);

        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);
        $withCard = (new Winner())->setCard($card);
        $withoutCard = (new Winner())->setReference('Z999');
        $game->addWinner($withCard);
        $game->addWinner($withoutCard);

        $this->clearAll($game);

        $this->ensure($game->getWinners()->isEmpty(), 'La démarque doit supprimer les gagnants de la partie.');
        $this->ensure(!$card->isBlocked(), 'La démarque doit débloquer les cartons gagnants.');
        $em->remove($withCard)->shouldHaveBeenCalledOnce();
        $em->remove($withoutCard)->shouldHaveBeenCalledOnce();
    }

    function it_clears_the_draws_of_every_game_of_an_event(EntityManagerInterface $em)
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));
        $first = $this->createGame([1, 2]);
        $second = $this->createGame([3]);
        $event->addGame($first);
        $event->addGame($second);

        $this->clearAllForEvent($event);

        $this->ensure($first->getDraws()->isEmpty(), 'Les tirages de la première partie doivent être supprimés.');
        $this->ensure($second->getDraws()->isEmpty(), 'Les tirages de la seconde partie doivent être supprimés.');
        $em->remove(Argument::type(Draw::class))->shouldHaveBeenCalledTimes(3);
        $em->flush()->shouldHaveBeenCalled();
    }

    /**
     * @param int[] $numbers Numéros tirés, dans l'ordre du tirage
     */
    private function createGame(array $numbers): Game
    {
        $game = new Game();

        foreach ($numbers as $index => $number) {
            $game->addDraw((new Draw())->setNumber($number)->setOrderIndex($index + 1));
        }

        return $game;
    }

    private function createCard(string $reference): Card
    {
        return (new Card())->setReference($reference)->setGrid(self::GRID);
    }

    /**
     * @return int[]
     */
    private function orderIndexes(Game $game): array
    {
        $indexes = array_map(fn (Draw $draw) => $draw->getOrderIndex(), $game->getDraws()->toArray());
        sort($indexes);

        return $indexes;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new FailureException($message);
        }
    }
}
