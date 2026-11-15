<?php

namespace spec\App\Entity;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Player;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class PlayerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Player::class);
    }

    function it_has_no_contact_details_event_or_card_by_default()
    {
        $this->getId()->shouldReturn(null);
        $this->getName()->shouldReturn('');
        $this->getEmail()->shouldReturn(null);
        $this->getPhone()->shouldReturn(null);
        $this->getNotes()->shouldReturn(null);
        $this->getEvent()->shouldReturn(null);
        $this->getCards()->shouldHaveCount(0);
    }

    function it_stores_its_contact_details()
    {
        $this->setName('Dupont')->shouldReturn($this);
        $this->setEmail('dupont@example.com')->shouldReturn($this);
        $this->setPhone('0612345678')->shouldReturn($this);
        $this->setNotes('Table 4')->shouldReturn($this);

        $this->getName()->shouldReturn('Dupont');
        $this->getEmail()->shouldReturn('dupont@example.com');
        $this->getPhone()->shouldReturn('0612345678');
        $this->getNotes()->shouldReturn('Table 4');
    }

    function it_can_be_attached_to_and_detached_from_an_event()
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));

        $this->setEvent($event)->shouldReturn($this);
        $this->getEvent()->shouldReturn($event);

        $this->setEvent(null);
        $this->getEvent()->shouldReturn(null);
    }

    function it_links_a_card_to_itself_when_adding_it()
    {
        $card = (new Card())->setReference('A001');

        $this->addCard($card)->shouldReturn($this);

        $this->getCards()->shouldHaveCount(1);
        if ($card->getPlayer() !== $this->getWrappedObject()) {
            throw new FailureException('Le carton doit référencer son joueur.');
        }
    }

    function it_does_not_add_the_same_card_twice()
    {
        $card = (new Card())->setReference('A001');

        $this->addCard($card);
        $this->addCard($card);

        $this->getCards()->shouldHaveCount(1);
    }

    function it_can_hold_several_cards()
    {
        $this->addCard((new Card())->setReference('A001'));
        $this->addCard((new Card())->setReference('A002'));

        $this->getCards()->shouldHaveCount(2);
    }

    function it_unlinks_a_card_when_removing_it()
    {
        $card = (new Card())->setReference('A001');
        $this->addCard($card);

        $this->removeCard($card)->shouldReturn($this);

        $this->getCards()->shouldHaveCount(0);
        if (null !== $card->getPlayer()) {
            throw new FailureException('Le carton retiré ne doit plus référencer le joueur.');
        }
    }
}
