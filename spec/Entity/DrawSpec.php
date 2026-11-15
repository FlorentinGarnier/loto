<?php

namespace spec\App\Entity;

use App\Entity\Draw;
use App\Entity\Game;
use PhpSpec\ObjectBehavior;

class DrawSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Draw::class);
    }

    function it_is_timestamped_at_creation()
    {
        $this->getId()->shouldReturn(null);
        $this->getGame()->shouldReturn(null);
        $this->getCreatedAt()->shouldBeAnInstanceOf(\DateTimeImmutable::class);
    }

    function it_stores_its_number_order_and_game()
    {
        $game = new Game();

        $this->setGame($game)->shouldReturn($this);
        $this->setNumber(42)->shouldReturn($this);
        $this->setOrderIndex(7)->shouldReturn($this);

        $this->getGame()->shouldReturn($game);
        $this->getNumber()->shouldReturn(42);
        $this->getOrderIndex()->shouldReturn(7);
    }
}
