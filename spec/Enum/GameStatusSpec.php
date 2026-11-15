<?php

namespace spec\App\Enum;

use App\Enum\GameStatus;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Contracts\Translation\TranslatorInterface;

class GameStatusSpec extends ObjectBehavior
{
    function it_exposes_the_three_states_of_a_game()
    {
        $this->beConstructedThrough('from', ['PENDING']);

        if (['PENDING', 'RUNNING', 'FINISHED'] !== array_column(GameStatus::cases(), 'value')) {
            throw new \PhpSpec\Exception\Example\FailureException('Les statuts attendus sont PENDING, RUNNING et FINISHED.');
        }
    }

    function it_is_translated_with_the_state_key(TranslatorInterface $translator)
    {
        $this->beConstructedThrough('from', ['RUNNING']);
        $translator->trans('app.state.RUNNING', Argument::cetera())->willReturn('En cours');

        $this->trans($translator)->shouldReturn('En cours');
    }
}
