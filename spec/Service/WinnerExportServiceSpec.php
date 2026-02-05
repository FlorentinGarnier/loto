<?php

namespace spec\App\Service;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Winner;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use App\Enum\WinnerSource;
use App\Service\WinnerExportService;
use PhpSpec\ObjectBehavior;
use Symfony\Contracts\Translation\TranslatorInterface;

class WinnerExportServiceSpec extends ObjectBehavior
{
    function let(TranslatorInterface $translator)
    {
        $this->beConstructedWith($translator);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(WinnerExportService::class);
    }

    function it_generates_csv_content_for_event_with_winners(TranslatorInterface $translator)
    {
        // Créer un événement
        $event = new Event('Test Event', new \DateTimeImmutable('2026-01-01'));

        // Créer un joueur
        $player = new Player();
        $player->setName('Dupont');
        $player->setPhone('0612345678');
        $player->setEmail('dupont@example.com');
        $player->setEvent($event);

        // Créer un carton
        $card = new Card();
        $card->setReference('A001');
        $card->setPlayer($player);
        $card->setGrid([[1, 2, 3, 4, 5]]);

        // Créer une partie
        $game = new Game();
        $game->setEvent($event);
        $game->setPosition(1);
        $game->setRule(RuleType::QUINE);
        $game->setPrize('Machine à café');
        $game->setStatus(GameStatus::FINISHED);

        // Créer un gagnant
        $winner = new Winner();
        $winner->setGame($game);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::SYSTEM);
        $winner->setWinningOrderIndex(0);

        // Associer le gagnant à la partie
        $game->addWinner($winner);

        // Associer la partie à l'événement
        $event->addGame($game);

        // Configurer le mock du translator - ne pas appeler trans() car il lève une exception
        // Le service gère l'exception en interne

        // Générer le CSV
        $csvContent = $this->generateCsvContent($event);

        // Vérifications
        $csvContent->shouldBeString();
        $csvContent->shouldContain('Partie');
        $csvContent->shouldContain('A001');
        $csvContent->shouldContain('Dupont');
        $csvContent->shouldContain('Machine à café');
    }

    function it_generates_csv_content_for_event_without_winners()
    {
        $event = new Event('Empty Event', new \DateTimeImmutable('2026-01-01'));

        $csvContent = $this->generateCsvContent($event);

        // Doit contenir uniquement les en-têtes
        $csvContent->shouldBeString();
        $csvContent->shouldContain('Partie');

        // Compter les lignes (1 ligne d'en-tête uniquement)
        $lines = explode("\n", $csvContent->getWrappedObject());
        $nonEmptyLines = array_filter($lines, fn($line) => !empty(trim($line)));
        if (count($nonEmptyLines) !== 1) {
            throw new \Exception("Expected 1 line (header only), got " . count($nonEmptyLines));
        }
    }

    function it_generates_csv_with_multiple_winners()
    {
        $event = new Event('Multi Winners Event', new \DateTimeImmutable('2026-01-01'));

        // Premier gagnant
        $player1 = new Player();
        $player1->setName('Dupont');
        $player1->setEvent($event);

        $card1 = new Card();
        $card1->setReference('A001');
        $card1->setPlayer($player1);

        $game1 = new Game();
        $game1->setEvent($event);
        $game1->setPosition(1);
        $game1->setRule(RuleType::QUINE);
        $game1->setPrize('Lot 1');
        $game1->setStatus(GameStatus::FINISHED);

        $winner1 = new Winner();
        $winner1->setGame($game1);
        $winner1->setCard($card1);
        $winner1->setSource(WinnerSource::SYSTEM);
        $winner1->setWinningOrderIndex(0);

        $game1->addWinner($winner1);
        $event->addGame($game1);

        // Deuxième gagnant
        $player2 = new Player();
        $player2->setName('Martin');
        $player2->setEvent($event);

        $card2 = new Card();
        $card2->setReference('B002');
        $card2->setPlayer($player2);

        $game2 = new Game();
        $game2->setEvent($event);
        $game2->setPosition(2);
        $game2->setRule(RuleType::DOUBLE_QUINE);
        $game2->setPrize('Lot 2');
        $game2->setStatus(GameStatus::FINISHED);

        $winner2 = new Winner();
        $winner2->setGame($game2);
        $winner2->setCard($card2);
        $winner2->setSource(WinnerSource::OFFLINE);
        $winner2->setWinningOrderIndex(0);

        $game2->addWinner($winner2);
        $event->addGame($game2);

        $csvContent = $this->generateCsvContent($event);

        $csvContent->shouldContain('A001');
        $csvContent->shouldContain('Dupont');
        $csvContent->shouldContain('B002');
        $csvContent->shouldContain('Martin');

        // Vérifier qu'il y a 3 lignes (1 en-tête + 2 gagnants)
        $lines = explode("\n", $csvContent->getWrappedObject());
        $nonEmptyLines = array_filter($lines, fn($line) => !empty(trim($line)));
        if (count($nonEmptyLines) !== 3) {
            throw new \Exception("Expected 3 lines, got " . count($nonEmptyLines));
        }
    }

    function it_generates_correct_filename_for_event()
    {
        $event = new Event('Test Event', new \DateTimeImmutable('2026-01-01'));
        // Simuler un ID (normalement défini par Doctrine)
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('id');
        $property->setValue($event, 42);

        $filename = $this->generateFilename($event);

        $filename->shouldBeString();
        $filename->shouldContain('gagnants_42');
        $filename->shouldContain(date('Y-m-d'));
        $filename->shouldEndWith('.csv');
    }

    function it_handles_winner_without_card()
    {
        $event = new Event('Test Event', new \DateTimeImmutable('2026-01-01'));

        $game = new Game();
        $game->setEvent($event);
        $game->setPosition(1);
        $game->setRule(RuleType::QUINE);
        $game->setPrize('Lot');
        $game->setStatus(GameStatus::FINISHED);

        // Gagnant sans carton (juste une référence)
        $winner = new Winner();
        $winner->setGame($game);
        $winner->setCard(null);
        $winner->setReference('Z999');
        $winner->setSource(WinnerSource::OFFLINE);
        $winner->setWinningOrderIndex(0);

        $game->addWinner($winner);
        $event->addGame($game);

        $csvContent = $this->generateCsvContent($event);

        $csvContent->shouldContain('Z999');
    }
}
