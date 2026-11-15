<?php

namespace spec\App\Entity;

use App\Entity\Card;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\WinnerSource;
use PhpSpec\ObjectBehavior;

class WinnerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Winner::class);
    }

    function it_is_a_timestamped_system_winner_by_default()
    {
        $this->getId()->shouldReturn(null);
        $this->getGame()->shouldReturn(null);
        $this->getCard()->shouldReturn(null);
        $this->getReference()->shouldReturn(null);
        $this->getSource()->shouldReturn(WinnerSource::SYSTEM);
        $this->getWinningOrderIndex()->shouldReturn(0);
        $this->getCreatedAt()->shouldBeAnInstanceOf(\DateTimeImmutable::class);
    }

    function it_stores_the_details_of_the_win()
    {
        $game = new Game();
        $card = (new Card())->setReference('A001');

        $this->setGame($game)->shouldReturn($this);
        $this->setCard($card)->shouldReturn($this);
        $this->setSource(WinnerSource::OFFLINE)->shouldReturn($this);
        $this->setReference('A001')->shouldReturn($this);
        $this->setWinningOrderIndex(23)->shouldReturn($this);

        $this->getGame()->shouldReturn($game);
        $this->getCard()->shouldReturn($card);
        $this->getSource()->shouldReturn(WinnerSource::OFFLINE);
        $this->getReference()->shouldReturn('A001');
        $this->getWinningOrderIndex()->shouldReturn(23);
    }

    function it_can_be_a_hall_winner_without_card()
    {
        $this->setCard(null);
        $this->setReference('Z999');

        $this->getCard()->shouldReturn(null);
        $this->getReference()->shouldReturn('Z999');
    }
}
