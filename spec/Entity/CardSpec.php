<?php

namespace spec\App\Entity;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Player;
use App\Enum\BlockedReason;
use PhpSpec\ObjectBehavior;

class CardSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Card::class);
    }

    function it_is_free_and_unassigned_by_default()
    {
        $this->getId()->shouldReturn(null);
        $this->getReference()->shouldReturn('');
        $this->getGrid()->shouldReturn([]);
        $this->getPlayer()->shouldReturn(null);
        $this->getEvent()->shouldReturn(null);
        $this->isBlocked()->shouldReturn(false);
        $this->getBlockedAt()->shouldReturn(null);
        $this->getBlockedReason()->shouldReturn(null);
    }

    function it_stores_its_reference_and_grid()
    {
        $grid = [[1, 12, 23, 34, 45], [6, 17, 28, 39, 50], [40, 51, 62, 73, 84]];

        $this->setReference('A001')->shouldReturn($this);
        $this->setGrid($grid)->shouldReturn($this);

        $this->getReference()->shouldReturn('A001');
        $this->getGrid()->shouldReturn($grid);
    }

    function it_can_be_blocked_with_a_reason()
    {
        $this->block(BlockedReason::WINNER_VALIDATED)->shouldReturn($this);

        $this->isBlocked()->shouldReturn(true);
        $this->getBlockedReason()->shouldReturn(BlockedReason::WINNER_VALIDATED);
        $this->getBlockedAt()->shouldBeAnInstanceOf(\DateTimeImmutable::class);
    }

    function it_can_be_unblocked()
    {
        $this->block(BlockedReason::WINNER_OFFLINE);

        $this->unblock()->shouldReturn($this);

        $this->isBlocked()->shouldReturn(false);
        $this->getBlockedReason()->shouldReturn(null);
        $this->getBlockedAt()->shouldReturn(null);
    }

    function it_belongs_to_the_event_of_its_player()
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));
        $player = (new Player())->setEvent($event);

        $this->setPlayer($player)->shouldReturn($this);

        $this->getPlayer()->shouldReturn($player);
        $this->getEvent()->shouldReturn($event);
    }

    function it_has_no_event_when_its_player_has_none()
    {
        $this->setPlayer(new Player());

        $this->getEvent()->shouldReturn(null);
    }

    function it_formats_its_grid_by_tens_column()
    {
        // Chaque numéro est placé dans la colonne de sa dizaine (0-9 → 0, 10-19 → 1, …, 80-90 → 8)
        $this->setGrid([
            [1, 12, 23, 34, 45],
            [6, 17, 28, 39, 50],
            [40, 51, 62, 73, 84],
        ]);

        $this->getFormattedGrid()->shouldBeLike([
            [0 => 1, 1 => 12, 2 => 23, 3 => 34, 4 => 45],
            [0 => 6, 1 => 17, 2 => 28, 3 => 39, 5 => 50],
            [4 => 40, 5 => 51, 6 => 62, 7 => 73, 8 => 84],
        ]);
    }

    function it_places_90_in_the_last_column()
    {
        $this->setGrid([[5, 12, 34, 56, 90]]);

        $this->getFormattedGrid()->shouldBeLike([
            [0 => 5, 1 => 12, 3 => 34, 5 => 56, 8 => 90],
        ]);
    }

    function it_formats_an_empty_grid_as_no_line()
    {
        $this->getFormattedGrid()->shouldReturn([]);
    }

    function it_formats_an_empty_line_as_an_empty_line()
    {
        $this->setGrid([[], [6, 17, 28, 39, 50]]);

        $this->getFormattedGrid()->shouldBeLike([
            [],
            [0 => 6, 1 => 17, 2 => 28, 3 => 39, 5 => 50],
        ]);
    }
}
