<?php

namespace spec\App\Service;

use App\Service\CardGridGenerator;
use PhpSpec\ObjectBehavior;
use Random\Engine\Mt19937;
use Random\Randomizer;

class CardGridGeneratorSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(CardGridGenerator::class);
    }

    function it_generates_three_lines_of_five_distinct_numbers_between_1_and_90()
    {
        $randomizer = new Randomizer(new Mt19937(1));

        for ($i = 0; $i < 200; ++$i) {
            $grid = $this->generate($randomizer)->getWrappedObject();

            if (3 !== count($grid) || [5, 5, 5] !== array_map('count', $grid)) {
                throw new \RuntimeException('La grille doit contenir 3 lignes de 5 numéros.');
            }
            $numbers = array_merge(...$grid);
            if (15 !== count(array_unique($numbers)) || min($numbers) < 1 || max($numbers) > 90) {
                throw new \RuntimeException('La grille doit contenir 15 numéros distincts entre 1 et 90.');
            }
        }
    }

    function it_puts_each_number_of_a_line_in_its_own_column_in_ascending_order()
    {
        $randomizer = new Randomizer(new Mt19937(2));

        for ($i = 0; $i < 200; ++$i) {
            foreach ($this->generate($randomizer)->getWrappedObject() as $line) {
                $columns = array_map(static fn (int $n): int => min(8, intdiv($n, 10)), $line);
                $sorted = $columns;
                sort($sorted);
                if ($columns !== $sorted || 5 !== count(array_unique($columns))) {
                    throw new \RuntimeException('Chaque numéro d\'une ligne doit occuper une colonne différente, dans l\'ordre.');
                }
            }
        }
    }

    function it_generates_the_same_grids_from_the_same_seed()
    {
        $this->generate(new Randomizer(new Mt19937(42)))
            ->shouldReturn((new CardGridGenerator())->generate(new Randomizer(new Mt19937(42))));
    }
}
