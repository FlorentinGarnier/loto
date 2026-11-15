<?php

namespace spec\App\Entity;

use App\Entity\Draw;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class GameSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Game::class);
    }

    function it_is_a_pending_quine_by_default()
    {
        $this->getId()->shouldReturn(null);
        $this->getEvent()->shouldReturn(null);
        $this->getPosition()->shouldReturn(0);
        $this->getRule()->shouldReturn(RuleType::QUINE);
        $this->getPrize()->shouldReturn('');
        $this->getStatus()->shouldReturn(GameStatus::PENDING);
        $this->hallOnly()->shouldReturn(false);
        $this->isFrozen()->shouldReturn(false);
        $this->getFreezeOrderIndex()->shouldReturn(null);
        $this->getDraws()->shouldHaveCount(0);
        $this->getWinners()->shouldHaveCount(0);
    }

    function it_stores_its_settings()
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));

        $this->setEvent($event)->shouldReturn($this);
        $this->setPosition(3)->shouldReturn($this);
        $this->setRule(RuleType::FULL_CARD)->shouldReturn($this);
        $this->setPrize('Téléviseur')->shouldReturn($this);
        $this->setStatus(GameStatus::RUNNING)->shouldReturn($this);
        $this->setHallOnly(true)->shouldReturn($this);

        $this->getEvent()->shouldReturn($event);
        $this->getPosition()->shouldReturn(3);
        $this->getRule()->shouldReturn(RuleType::FULL_CARD);
        $this->getPrize()->shouldReturn('Téléviseur');
        $this->getStatus()->shouldReturn(GameStatus::RUNNING);
        $this->hallOnly()->shouldReturn(true);
    }

    function it_can_be_frozen_at_a_draw_order_index()
    {
        $this->freeze(12)->shouldReturn($this);

        $this->isFrozen()->shouldReturn(true);
        $this->getFreezeOrderIndex()->shouldReturn(12);
    }

    function it_can_be_unfrozen()
    {
        $this->freeze(12);

        $this->unfreeze()->shouldReturn($this);

        $this->isFrozen()->shouldReturn(false);
        $this->getFreezeOrderIndex()->shouldReturn(null);
    }

    function it_links_a_draw_to_itself_when_adding_it()
    {
        $draw = (new Draw())->setNumber(42);

        $this->addDraw($draw)->shouldReturn($this);

        $this->getDraws()->shouldHaveCount(1);
        if ($draw->getGame() !== $this->getWrappedObject()) {
            throw new FailureException('Le tirage doit référencer sa partie.');
        }
    }

    function it_does_not_add_the_same_draw_twice()
    {
        $draw = (new Draw())->setNumber(42);

        $this->addDraw($draw);
        $this->addDraw($draw);

        $this->getDraws()->shouldHaveCount(1);
    }

    function it_unlinks_a_draw_when_removing_it()
    {
        $draw = (new Draw())->setNumber(42);
        $this->addDraw($draw);

        $this->removeDraw($draw)->shouldReturn($this);

        $this->getDraws()->shouldHaveCount(0);
        if (null !== $draw->getGame()) {
            throw new FailureException('Le tirage retiré ne doit plus référencer la partie.');
        }
    }

    function it_links_a_winner_to_itself_when_adding_it()
    {
        $winner = new Winner();

        $this->addWinner($winner)->shouldReturn($this);

        $this->getWinners()->shouldHaveCount(1);
        if ($winner->getGame() !== $this->getWrappedObject()) {
            throw new FailureException('Le gagnant doit référencer sa partie.');
        }
    }

    function it_does_not_add_the_same_winner_twice()
    {
        $winner = new Winner();

        $this->addWinner($winner);
        $this->addWinner($winner);

        $this->getWinners()->shouldHaveCount(1);
    }

    function it_unlinks_a_winner_when_removing_it()
    {
        $winner = new Winner();
        $this->addWinner($winner);

        $this->removeWinner($winner)->shouldReturn($this);

        $this->getWinners()->shouldHaveCount(0);
        if (null !== $winner->getGame()) {
            throw new FailureException('Le gagnant retiré ne doit plus référencer la partie.');
        }
    }
}
