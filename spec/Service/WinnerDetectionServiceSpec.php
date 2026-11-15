<?php

namespace spec\App\Service;

use App\Entity\Card;
use App\Entity\Draw;
use App\Entity\Game;
use App\Enum\BlockedReason;
use App\Enum\RuleType;
use App\Service\WinnerDetectionService;
use PhpSpec\ObjectBehavior;

class WinnerDetectionServiceSpec extends ObjectBehavior
{
    private const GRID = [
        [1, 12, 23, 34, 45],
        [6, 17, 28, 39, 50],
        [40, 51, 62, 73, 84],
    ];

    function it_is_initializable()
    {
        $this->shouldHaveType(WinnerDetectionService::class);
    }

    function it_finds_no_potential_winner_when_nothing_is_drawn()
    {
        $game = $this->createGame(RuleType::QUINE, []);

        $this->findPotentialWinners($game, [$this->createCard('A001')])->shouldReturn([]);
    }

    function it_finds_no_potential_winner_when_a_line_is_incomplete()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34]);

        $this->findPotentialWinners($game, [$this->createCard('A001')])->shouldReturn([]);
    }

    function it_detects_a_quine_when_one_line_is_complete()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);
        $card = $this->createCard('A001');

        $this->findPotentialWinners($game, [$card])->shouldReturn([
            ['card' => $card, 'matchedLines' => 1],
        ]);
    }

    function it_detects_a_quine_on_any_line_of_the_card()
    {
        $game = $this->createGame(RuleType::QUINE, [40, 51, 62, 73, 84]);
        $card = $this->createCard('A001');

        $this->findPotentialWinners($game, [$card])->shouldReturn([
            ['card' => $card, 'matchedLines' => 1],
        ]);
    }

    function it_does_not_detect_a_double_quine_with_a_single_line()
    {
        $game = $this->createGame(RuleType::DOUBLE_QUINE, [1, 12, 23, 34, 45]);

        $this->findPotentialWinners($game, [$this->createCard('A001')])->shouldReturn([]);
    }

    function it_detects_a_double_quine_when_two_lines_are_complete()
    {
        $game = $this->createGame(RuleType::DOUBLE_QUINE, [1, 12, 23, 34, 45, 6, 17, 28, 39, 50]);
        $card = $this->createCard('A001');

        $this->findPotentialWinners($game, [$card])->shouldReturn([
            ['card' => $card, 'matchedLines' => 2],
        ]);
    }

    function it_does_not_detect_a_full_card_with_two_lines()
    {
        $game = $this->createGame(RuleType::FULL_CARD, [1, 12, 23, 34, 45, 6, 17, 28, 39, 50]);

        $this->findPotentialWinners($game, [$this->createCard('A001')])->shouldReturn([]);
    }

    function it_detects_a_full_card_when_three_lines_are_complete()
    {
        $game = $this->createGame(RuleType::FULL_CARD, array_merge(...self::GRID));
        $card = $this->createCard('A001');

        $this->findPotentialWinners($game, [$card])->shouldReturn([
            ['card' => $card, 'matchedLines' => 3],
        ]);
    }

    function it_still_reports_a_quine_when_more_lines_than_required_are_complete()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45, 6, 17, 28, 39, 50]);
        $card = $this->createCard('A001');

        $this->findPotentialWinners($game, [$card])->shouldReturn([
            ['card' => $card, 'matchedLines' => 2],
        ]);
    }

    function it_returns_every_card_winning_on_the_same_draw()
    {
        // Égalité : deux cartons complètent leur ligne sur le même tirage
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);
        $first = $this->createCard('A001');
        $second = $this->createCard('A002', [[1, 12, 23, 34, 45], [2, 13, 24, 35, 46], [3, 14, 25, 36, 47]]);
        $loser = $this->createCard('A003', [[7, 18, 29, 30, 41], [8, 19, 20, 31, 42], [9, 10, 21, 32, 43]]);

        $this->findPotentialWinners($game, [$first, $loser, $second])->shouldReturn([
            ['card' => $first, 'matchedLines' => 1],
            ['card' => $second, 'matchedLines' => 1],
        ]);
    }

    function it_excludes_blocked_cards()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);
        $card = $this->createCard('A001');
        $card->block(BlockedReason::WINNER_VALIDATED);

        $this->findPotentialWinners($game, [$card])->shouldReturn([]);
    }

    function it_finds_no_potential_winner_on_a_hall_only_game()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);
        $game->setHallOnly(true);

        $this->findPotentialWinners($game, [$this->createCard('A001')])->shouldReturn([]);
    }

    function it_ignores_lines_that_do_not_hold_exactly_five_numbers()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34]);
        $card = $this->createCard('A001', [[1, 12, 23, 34], [6, 17, 28, 39, 50], [40, 51, 62, 73, 84]]);

        $this->findPotentialWinners($game, [$card])->shouldReturn([]);
    }

    function it_ignores_cards_without_grid()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);

        $this->findPotentialWinners($game, [$this->createCard('A001', [])])->shouldReturn([]);
    }

    function it_only_considers_draws_up_to_the_freeze_when_the_game_is_frozen()
    {
        // Le carton A001 gagne au 5e tirage : la partie est gelée à cet instant.
        // Le carton A002 ne complète sa ligne qu'avec le 6e tirage : il ne doit pas apparaître.
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45, 46]);
        $game->freeze(5);
        $winner = $this->createCard('A001');
        $late = $this->createCard('A002', [[1, 12, 23, 34, 46], [2, 13, 24, 35, 47], [3, 14, 25, 36, 48]]);

        $this->findPotentialWinners($game, [$winner, $late])->shouldReturn([
            ['card' => $winner, 'matchedLines' => 1],
        ]);
    }

    function it_considers_every_draw_when_the_game_is_not_frozen()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45, 46]);
        $first = $this->createCard('A001');
        $second = $this->createCard('A002', [[1, 12, 23, 34, 46], [2, 13, 24, 35, 47], [3, 14, 25, 36, 48]]);

        $this->findPotentialWinners($game, [$first, $second])->shouldHaveCount(2);
    }

    function it_returns_null_when_checking_for_winners_and_there_is_none()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23]);

        $this->checkForWinners($game, [$this->createCard('A001')])->shouldReturn(null);
    }

    function it_returns_the_last_draw_order_index_when_a_winner_is_found()
    {
        $game = $this->createGame(RuleType::QUINE, [90, 2, 1, 12, 23, 34, 45]);

        $this->checkForWinners($game, [$this->createCard('A001')])->shouldReturn(7);
    }

    function it_returns_null_when_checking_for_winners_without_cards()
    {
        $game = $this->createGame(RuleType::QUINE, [1, 12, 23, 34, 45]);

        $this->checkForWinners($game, [])->shouldReturn(null);
    }

    /**
     * @param int[] $numbers Numéros tirés, dans l'ordre du tirage
     */
    private function createGame(RuleType $rule, array $numbers): Game
    {
        $game = new Game();
        $game->setRule($rule);

        foreach ($numbers as $index => $number) {
            $game->addDraw((new Draw())->setNumber($number)->setOrderIndex($index + 1));
        }

        return $game;
    }

    private function createCard(string $reference, array $grid = self::GRID): Card
    {
        return (new Card())->setReference($reference)->setGrid($grid);
    }
}
