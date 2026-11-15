<?php

namespace spec\App\Service;

use App\Entity\Card;
use App\Entity\Draw;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\BlockedReason;
use App\Enum\WinnerSource;
use App\Service\WinnerService;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class WinnerServiceSpec extends ObjectBehavior
{
    function let(EntityManagerInterface $em)
    {
        $this->beConstructedWith($em);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(WinnerService::class);
    }

    // —— Gagnant système ——————————————————————————————————————————————

    function it_refuses_to_validate_a_system_winner_on_a_game_that_is_not_frozen()
    {
        $this->shouldThrow(new \RuntimeException('Cannot validate winner on non-frozen game'))
            ->during('validateSystemWinner', [$this->createGame(5), $this->createCard('A001')]);
    }

    function it_refuses_to_validate_a_blocked_card_as_system_winner()
    {
        $game = $this->createGame(5);
        $game->freeze(5);
        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);

        $this->shouldThrow(new \RuntimeException('Cannot validate blocked card'))
            ->during('validateSystemWinner', [$game, $card]);
    }

    function it_refuses_to_validate_the_same_card_twice_for_a_game()
    {
        $game = $this->createGame(5);
        $game->freeze(5);
        $card = $this->createCard('A001');
        $game->addWinner((new Winner())->setCard($card));

        $this->shouldThrow(new \RuntimeException('Card already validated as winner for this game'))
            ->during('validateSystemWinner', [$game, $card]);
    }

    function it_validates_a_system_winner(EntityManagerInterface $em)
    {
        $game = $this->createGame(5);
        $game->freeze(5);
        $card = $this->createCard('A001');

        $winner = $this->validateSystemWinner($game, $card);

        $winner->shouldBeAnInstanceOf(Winner::class);
        $winner->getSource()->shouldReturn(WinnerSource::SYSTEM);
        $winner->getCard()->shouldReturn($card);
        $winner->getGame()->shouldReturn($game);
        $winner->getReference()->shouldReturn('A001');
        $winner->getWinningOrderIndex()->shouldReturn(5);

        $this->ensure($game->getWinners()->contains($winner->getWrappedObject()), 'Le gagnant doit être rattaché à la partie.');
        $em->persist($winner->getWrappedObject())->shouldHaveBeenCalled();
        $em->flush()->shouldHaveBeenCalled();
    }

    function it_blocks_the_card_of_a_validated_system_winner()
    {
        $game = $this->createGame(5);
        $game->freeze(5);
        $card = $this->createCard('A001');

        $this->validateSystemWinner($game, $card);

        $this->ensure($card->isBlocked(), 'Le carton gagnant doit être bloqué.');
        $this->ensure(BlockedReason::WINNER_VALIDATED === $card->getBlockedReason(), 'La raison du blocage doit être WINNER_VALIDATED.');
        $this->ensure(null !== $card->getBlockedAt(), 'Le blocage doit être horodaté.');
    }

    function it_allows_several_system_winners_on_the_same_game()
    {
        // Égalité : deux cartons gagnent sur le même tirage
        $game = $this->createGame(5);
        $game->freeze(5);

        $this->validateSystemWinner($game, $this->createCard('A001'));
        $this->validateSystemWinner($game, $this->createCard('A002'))->getWinningOrderIndex()->shouldReturn(5);

        $this->ensure(2 === $game->getWinners()->count(), 'Les deux gagnants doivent être enregistrés.');
    }

    // —— Gagnant salle (offline) ——————————————————————————————————————

    function it_validates_an_offline_winner_without_card(EntityManagerInterface $em)
    {
        $game = $this->createGame(3);

        $winner = $this->validateOfflineWinner($game, 'Z999');

        $winner->shouldBeAnInstanceOf(Winner::class);
        $winner->getSource()->shouldReturn(WinnerSource::OFFLINE);
        $winner->getCard()->shouldReturn(null);
        $winner->getReference()->shouldReturn('Z999');
        $winner->getGame()->shouldReturn($game);

        $this->ensure($game->getWinners()->contains($winner->getWrappedObject()), 'Le gagnant doit être rattaché à la partie.');
        $em->persist($winner->getWrappedObject())->shouldHaveBeenCalled();
        $em->flush()->shouldHaveBeenCalled();
    }

    function it_freezes_the_game_at_the_last_draw_when_validating_an_offline_winner()
    {
        $game = $this->createGame(3);

        $this->validateOfflineWinner($game, 'Z999')->getWinningOrderIndex()->shouldReturn(3);

        $this->ensure($game->isFrozen(), 'La partie doit être gelée par un gagnant salle.');
        $this->ensure(3 === $game->getFreezeOrderIndex(), 'Le gel doit porter sur le dernier tirage.');
    }

    function it_freezes_a_game_without_draw_at_index_0()
    {
        $game = $this->createGame(0);

        $this->validateOfflineWinner($game, 'Z999')->getWinningOrderIndex()->shouldReturn(0);

        $this->ensure($game->isFrozen(), 'La partie doit être gelée même sans tirage.');
        $this->ensure(0 === $game->getFreezeOrderIndex(), 'Sans tirage, le gel porte sur l\'index 0.');
    }

    function it_keeps_the_existing_freeze_when_the_game_is_already_frozen()
    {
        $game = $this->createGame(6);
        $game->freeze(4);

        $this->validateOfflineWinner($game, 'Z999')->getWinningOrderIndex()->shouldReturn(4);

        $this->ensure(4 === $game->getFreezeOrderIndex(), 'Un gel existant ne doit pas être déplacé.');
    }

    function it_blocks_the_card_of_an_offline_winner()
    {
        $game = $this->createGame(3);
        $card = $this->createCard('A001');

        $this->validateOfflineWinner($game, 'A001', $card)->getCard()->shouldReturn($card);

        $this->ensure($card->isBlocked(), 'Le carton associé doit être bloqué.');
        $this->ensure(BlockedReason::WINNER_OFFLINE === $card->getBlockedReason(), 'La raison du blocage doit être WINNER_OFFLINE.');
    }

    function it_refuses_a_blocked_card_as_offline_winner()
    {
        $game = $this->createGame(3);
        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);

        $this->shouldThrow(new \RuntimeException('Cannot validate blocked card'))
            ->during('validateOfflineWinner', [$game, 'A001', $card]);

        $this->ensure($game->getWinners()->isEmpty(), 'Aucun gagnant ne doit être créé pour un carton bloqué.');
        $this->ensure(!$game->isFrozen(), 'Un gagnant salle refusé ne doit pas geler la partie.');
    }

    // —— Reset global ————————————————————————————————————————————————

    function it_removes_every_winner_of_an_event_and_unblocks_their_cards(EntityManagerInterface $em)
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));
        $first = $this->createGame(5);
        $second = $this->createGame(8);
        $event->addGame($first);
        $event->addGame($second);

        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);
        $systemWinner = (new Winner())->setCard($card);
        $offlineWinner = (new Winner())->setReference('Z999')->setSource(WinnerSource::OFFLINE);
        $first->addWinner($systemWinner);
        $second->addWinner($offlineWinner);

        $this->clearAllForEvent($event);

        $this->ensure($first->getWinners()->isEmpty(), 'Les gagnants de la première partie doivent être supprimés.');
        $this->ensure($second->getWinners()->isEmpty(), 'Les gagnants de la seconde partie doivent être supprimés.');
        $this->ensure(!$card->isBlocked(), 'Les cartons gagnants doivent être débloqués.');
        $em->remove($systemWinner)->shouldHaveBeenCalledOnce();
        $em->remove($offlineWinner)->shouldHaveBeenCalledOnce();
        $em->flush()->shouldHaveBeenCalled();
    }

    /**
     * Crée une partie avec $drawCount tirages (numéros 1..$drawCount).
     */
    private function createGame(int $drawCount): Game
    {
        $game = new Game();

        for ($i = 1; $i <= $drawCount; ++$i) {
            $game->addDraw((new Draw())->setNumber($i)->setOrderIndex($i));
        }

        return $game;
    }

    private function createCard(string $reference): Card
    {
        return (new Card())->setReference($reference)->setGrid([
            [1, 12, 23, 34, 45],
            [6, 17, 28, 39, 50],
            [40, 51, 62, 73, 84],
        ]);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new FailureException($message);
        }
    }
}
