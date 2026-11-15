<?php

namespace spec\App\Service;

use App\Entity\Event;
use App\Entity\Player;
use App\Repository\CardRepository;
use App\Service\CardService;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class CardServiceSpec extends ObjectBehavior
{
    function let(EntityManagerInterface $em, CardRepository $cardRepository)
    {
        $this->beConstructedWith($em, $cardRepository);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(CardService::class);
    }

    function it_unassigns_every_player_from_an_event(EntityManagerInterface $em)
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));
        $dupont = (new Player())->setName('Dupont');
        $martin = (new Player())->setName('Martin');
        $event->addPlayer($dupont);
        $event->addPlayer($martin);

        $this->unassignAllPlayersForEvent($event);

        if (null !== $dupont->getEvent() || null !== $martin->getEvent()) {
            throw new FailureException('Les joueurs ne doivent plus être rattachés à l\'événement.');
        }
        $em->persist($dupont)->shouldHaveBeenCalledOnce();
        $em->persist($martin)->shouldHaveBeenCalledOnce();
        $em->flush()->shouldHaveBeenCalledOnce();
    }

    function it_leaves_players_of_other_events_untouched()
    {
        $event = new Event('Loto', new \DateTimeImmutable('2026-01-01'));
        $other = new Event('Autre loto', new \DateTimeImmutable('2026-02-01'));
        $player = (new Player())->setName('Durand');
        $other->addPlayer($player);

        $this->unassignAllPlayersForEvent($event);

        if ($other !== $player->getEvent()) {
            throw new FailureException('Un joueur d\'un autre événement ne doit pas être désassocié.');
        }
    }

    function it_flushes_even_when_the_event_has_no_player(EntityManagerInterface $em)
    {
        $this->unassignAllPlayersForEvent(new Event('Loto vide'));

        $em->flush()->shouldHaveBeenCalledOnce();
    }
}
