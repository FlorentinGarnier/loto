<?php

namespace spec\App\Entity;

use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Player;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class EventSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('Loto des écoles', new \DateTimeImmutable('2026-01-31 20:00'));
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Event::class);
    }

    function it_is_created_with_a_name_and_a_date()
    {
        $this->getId()->shouldReturn(null);
        $this->getName()->shouldReturn('Loto des écoles');
        $this->getDate()->shouldBeLike(new \DateTimeImmutable('2026-01-31 20:00'));
        $this->getGames()->shouldHaveCount(0);
        $this->getPlayers()->shouldHaveCount(0);
    }

    function it_defaults_to_an_empty_name_and_the_current_date()
    {
        $this->beConstructedWith();

        $this->getName()->shouldReturn('');
        $this->getDate()->shouldBeAnInstanceOf(\DateTimeImmutable::class);
    }

    function it_can_be_renamed_and_rescheduled()
    {
        $date = new \DateTimeImmutable('2026-02-14 19:30');

        $this->setName('Loto de la Saint-Valentin')->shouldReturn($this);
        $this->setDate($date)->shouldReturn($this);

        $this->getName()->shouldReturn('Loto de la Saint-Valentin');
        $this->getDate()->shouldReturn($date);
    }

    function it_links_a_game_to_itself_when_adding_it()
    {
        $game = new Game();

        $this->addGame($game)->shouldReturn($this);

        $this->getGames()->shouldHaveCount(1);
        if ($game->getEvent() !== $this->getWrappedObject()) {
            throw new FailureException('La partie doit référencer son événement.');
        }
    }

    function it_does_not_add_the_same_game_twice()
    {
        $game = new Game();

        $this->addGame($game);
        $this->addGame($game);

        $this->getGames()->shouldHaveCount(1);
    }

    function it_unlinks_a_game_when_removing_it()
    {
        $game = new Game();
        $this->addGame($game);

        $this->removeGame($game)->shouldReturn($this);

        $this->getGames()->shouldHaveCount(0);
        if (null !== $game->getEvent()) {
            throw new FailureException('La partie retirée ne doit plus référencer l\'événement.');
        }
    }

    function it_links_a_player_to_itself_when_adding_it()
    {
        $player = new Player();

        $this->addPlayer($player)->shouldReturn($this);

        $this->getPlayers()->shouldHaveCount(1);
        if ($player->getEvent() !== $this->getWrappedObject()) {
            throw new FailureException('Le joueur doit référencer son événement.');
        }
    }

    function it_does_not_add_the_same_player_twice()
    {
        $player = new Player();

        $this->addPlayer($player);
        $this->addPlayer($player);

        $this->getPlayers()->shouldHaveCount(1);
    }

    function it_unlinks_a_player_when_removing_it()
    {
        $player = new Player();
        $this->addPlayer($player);

        $this->removePlayer($player)->shouldReturn($this);

        $this->getPlayers()->shouldHaveCount(0);
        if (null !== $player->getEvent()) {
            throw new FailureException('Le joueur retiré ne doit plus référencer l\'événement.');
        }
    }
}
