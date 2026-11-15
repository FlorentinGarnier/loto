<?php

namespace spec\App\Enum;

use App\Enum\WinnerSource;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Contracts\Translation\TranslatorInterface;

class WinnerSourceSpec extends ObjectBehavior
{
    function it_is_translated_with_the_winner_key_for_a_system_winner(TranslatorInterface $translator)
    {
        $this->beConstructedThrough('from', ['SYSTEM']);
        $translator->trans('app.winner.SYSTEM', Argument::cetera())->willReturn('Système');

        $this->trans($translator)->shouldReturn('Système');
    }

    function it_is_translated_with_the_winner_key_for_a_hall_winner(TranslatorInterface $translator)
    {
        $this->beConstructedThrough('from', ['OFFLINE']);
        $translator->trans('app.winner.OFFLINE', Argument::cetera())->willReturn('Salle');

        $this->trans($translator)->shouldReturn('Salle');
    }
}
