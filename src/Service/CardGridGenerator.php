<?php

namespace App\Service;

use Random\Randomizer;

final class CardGridGenerator
{
    /**
     * Génère une grille de loto traditionnelle : 3 lignes de 5 numéros entre 1 et 90, sans doublon,
     * chaque numéro d'une ligne dans une colonne différente (1-9, 10-19, …, 80-90), triés par colonne.
     * Un Randomizer à graine fixe donne toujours la même suite de grilles.
     *
     * @return int[][]
     */
    public function generate(?Randomizer $randomizer = null): array
    {
        $randomizer ??= new Randomizer();

        $used = [];
        $grid = [];
        for ($line = 0; $line < 3; ++$line) {
            $columns = array_slice($randomizer->shuffleArray(range(0, 8)), 0, 5);
            sort($columns);

            $numbers = [];
            foreach ($columns as $column) {
                $min = 0 === $column ? 1 : $column * 10;
                $max = 8 === $column ? 90 : $column * 10 + 9;
                do {
                    $number = $randomizer->getInt($min, $max);
                } while (isset($used[$number]));
                $used[$number] = true;
                $numbers[] = $number;
            }
            $grid[] = $numbers;
        }

        return $grid;
    }
}
