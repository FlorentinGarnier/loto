<?php

namespace spec\App\Service;

use App\Entity\Event;
use App\Entity\Game;
use App\Service\GameStatePublisher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class GameStatePublisherSpec extends ObjectBehavior
{
    function let(HubInterface $hub, LoggerInterface $logger)
    {
        $this->beConstructedWith($hub, $logger);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(GameStatePublisher::class);
    }

    function it_does_not_publish_before_the_response_is_sent(HubInterface $hub)
    {
        $hub->publish(Argument::any())->shouldNotBeCalled();

        $this->queue($this->createGame(7, 3), ['draws' => [5]]);
    }

    function it_publishes_the_state_to_both_screens_in_one_update(HubInterface $hub)
    {
        $hub->publish(Argument::that(fn (Update $u) => $u->getTopics() === ['/events/3/games/7/state', '/events/3/public']
            && '{"draws":[5]}' === $u->getData()))->shouldBeCalledOnce()->willReturn('id');

        $this->queue($this->createGame(7, 3), ['draws' => [5]]);
        $this->flush();
    }

    function it_only_publishes_the_latest_state_of_a_game(HubInterface $hub)
    {
        $hub->publish(Argument::that(fn (Update $u) => '{"draws":[5,12]}' === $u->getData()))->shouldBeCalledOnce()->willReturn('id');

        $game = $this->createGame(7, 3);
        $this->queue($game, ['draws' => [5]]);
        $this->queue($game, ['draws' => [5, 12]]);
        $this->flush();
    }

    function it_publishes_each_state_only_once(HubInterface $hub)
    {
        $hub->publish(Argument::any())->shouldBeCalledOnce()->willReturn('id');

        $this->queue($this->createGame(7, 3), ['draws' => [5]]);
        $this->flush();
        $this->flush();
    }

    function it_logs_a_hub_failure_instead_of_failing(HubInterface $hub, LoggerInterface $logger)
    {
        $hub->publish(Argument::any())->willThrow(new \RuntimeException('Failed to send an update.'));
        $logger->warning(Argument::cetera())->shouldBeCalledOnce();

        $this->queue($this->createGame(7, 3), ['draws' => [5]]);
        $this->shouldNotThrow()->during('flush');
    }

    private function createGame(int $gameId, int $eventId): Game
    {
        $event = new Event();
        $this->setId($event, $eventId);

        $game = new Game();
        $game->setEvent($event);
        $this->setId($game, $gameId);

        return $game;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
