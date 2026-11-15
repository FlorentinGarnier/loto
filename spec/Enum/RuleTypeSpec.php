<?php

namespace spec\App\Enum;

use App\Enum\RuleType;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Contracts\Translation\TranslatorInterface;

class RuleTypeSpec extends ObjectBehavior
{
    function it_requires_one_line_for_a_quine()
    {
        $this->beConstructedThrough('from', ['QUINE']);

        $this->getRequiredLines()->shouldReturn(1);
    }

    function it_requires_two_lines_for_a_double_quine()
    {
        $this->beConstructedThrough('from', ['DOUBLE_QUINE']);

        $this->getRequiredLines()->shouldReturn(2);
    }

    function it_requires_three_lines_for_a_full_card()
    {
        $this->beConstructedThrough('from', ['FULL_CARD']);

        $this->getRequiredLines()->shouldReturn(3);
    }

    function it_is_translated_with_the_rules_key(TranslatorInterface $translator)
    {
        $this->beConstructedThrough('from', ['DOUBLE_QUINE']);
        $translator->trans('app.rules.DOUBLE_QUINE', Argument::cetera())->willReturn('Double quine');

        $this->trans($translator)->shouldReturn('Double quine');
    }
}
