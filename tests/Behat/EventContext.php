<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Controller\AdminController;
use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\When;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class EventContext extends BaseContext
{
    private ?Event $currentEvent = null;
    private AdminController $adminController;

    public function __construct(
        EntityManagerInterface $entityManager,
        KernelBrowser $client,
        \App\Repository\EventRepository $eventRepo,
        \App\Repository\GameRepository $gameRepo,
        \App\Repository\CardRepository $cardRepo,
        \App\Repository\PlayerRepository $playerRepo,
        AdminController $adminController,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo, $client);
        $this->adminController = $adminController;
    }

    /**
     * @Given /^je suis connecté en tant qu'administrateur$/
     */
    public function queJeSuisConnecteEnTantQuAdministrateur(): void
    {
        $this->loginAsAdmin();
    }

    /**
     * @When /^je crée un événement de loto nommé "([^"]*)" pour le "([^"]*)"$/
     */
    public function jeCreerUnEvenementDeLotoNommePourLe(string $name, string $date): void
    {
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateTime) {
            throw new \RuntimeException("Invalid date format: {$date}");
        }

        $this->currentEvent = new Event($name, $dateTime);
        $this->entityManager->persist($this->currentEvent);
        $this->entityManager->flush();
    }

    /**
     * @Then /^l'événement "([^"]*)" doit exister$/
     */
    public function lEvenementDoitExister(string $name): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $name]);
        Assert::assertNotNull($event, "L'événement '{$name}' n'existe pas");
        $this->currentEvent = $event;
    }

    /**
     * @Then /^la date de l'événement "([^"]*)" doit être "([^"]*)"$/
     */
    public function laDateDeLEvenementDoitEtre(string $name, string $expectedDate): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $name]);
        Assert::assertNotNull($event, "L'événement '{$name}' n'existe pas");

        $actualDate = $event->getDate()->format('Y-m-d');
        Assert::assertEquals($expectedDate, $actualDate, "La date de l'événement ne correspond pas");
    }

    /**
     * @Given /^un événement "([^"]*)" existe$/
     */
    public function quUnEvenementExiste(string $name): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $name]);

        if (!$event) {
            $event = new Event($name, new \DateTimeImmutable());
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        }

        $this->currentEvent = $event;
    }

    /**
     * @When /^je définis les parties suivantes pour l'événement "([^"]*)":$/
     */
    public function jeDefinisLesPartiesSuivantesPourLEvenement(string $eventName, TableNode $table): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        foreach ($table->getHash() as $row) {
            $game = new Game();
            $game->setEvent($event);
            $game->setPosition((int) $row['ordre']);
            $game->setRule(RuleType::from($row['règle']));
            $game->setPrize($row['lot']);
            $game->setStatus(GameStatus::PENDING);

            $event->addGame($game);
            $this->entityManager->persist($game);
        }

        $this->entityManager->flush();
        $this->currentEvent = $event;
    }

    /**
     * @Given /^les parties suivantes sont définies pour l'événement "([^"]*)":$/
     */
    public function queLesPartiesSuivantesSontDefiniesPourLEvenement(string $eventName, TableNode $table): void
    {
        $this->jeDefinisLesPartiesSuivantesPourLEvenement($eventName, $table);
    }

    /**
     * @Then /^l'événement "([^"]*)" doit avoir (\d+) parties$/
     */
    public function lEvenementDoitAvoirParties(string $eventName, int $expectedCount): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $actualCount = $event->getGames()->count();
        Assert::assertEquals($expectedCount, $actualCount, 'Le nombre de parties ne correspond pas');
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit avoir la règle "([^"]*)" et le lot "([^"]*)"$/
     */
    public function laPartieDOrdreDoitAvoirLaRegleEtLeLot(int $position, string $rule, string $prize): void
    {
        Assert::assertNotNull($this->currentEvent, 'Aucun événement courant défini');

        $games = $this->currentEvent->getGames();
        $game = null;

        foreach ($games as $g) {
            if ($g->getPosition() === $position) {
                $game = $g;
                break;
            }
        }

        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");
        Assert::assertEquals($rule, $game->getRule()->value, 'La règle de la partie ne correspond pas');
        Assert::assertEquals($prize, $game->getPrize(), 'Le lot de la partie ne correspond pas');
    }

    /**
     * @When /^je démarre la première partie de l'événement "([^"]*)"$/
     */
    public function jeDemarrelaPremièrePartieDeLEvenement(string $eventName): void
    {
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $games = $event->getGames();
        Assert::assertGreaterThan(0, $games->count(), "L'événement n'a aucune partie");

        // Trouver la partie avec position = 1
        foreach ($games as $game) {
            if (1 === $game->getPosition()) {
                $game->setStatus(GameStatus::RUNNING);
                $this->entityManager->flush();
                break;
            }
        }

        $this->currentEvent = $event;
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit être en statut "([^"]*)"$/
     */
    public function laPartieDOrdreDoitEtreEnStatut(int $position, string $expectedStatus): void
    {
        Assert::assertNotNull($this->currentEvent, 'Aucun événement courant défini');

        $games = $this->currentEvent->getGames();
        $game = null;

        foreach ($games as $g) {
            if ($g->getPosition() === $position) {
                $game = $g;
                break;
            }
        }

        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        // Rafraîchir l'entité pour obtenir le dernier état
        $this->entityManager->refresh($game);

        Assert::assertEquals($expectedStatus, $game->getStatus()->value, 'Le statut de la partie ne correspond pas');
    }

    /**
     * @Given /^la partie d'ordre (\d+) est en statut "([^"]*)"$/
     */
    public function queLaPartieDOrdreEstEnStatut(int $position, string $status): void
    {
        Assert::assertNotNull($this->currentEvent, 'Aucun événement courant défini');

        $game = $this->findGameByPosition($position);
        $game->setStatus(GameStatus::from($status));
        $this->entityManager->flush();
    }

    /**
     * @When /^je termine la partie d'ordre (\d+) et passe à la suivante$/
     */
    public function jeTermineLaPartieDOrdreEtPasseALaSuivante(int $position): void
    {
        Assert::assertNotNull($this->currentEvent, 'Aucun événement courant défini');

        $currentGame = $this->findGameByPosition($position);
        $currentGame->setStatus(GameStatus::FINISHED);

        // Trouver la partie suivante
        $nextGame = $this->findGameByPosition($position + 1);
        if ($nextGame) {
            $nextGame->setStatus(GameStatus::RUNNING);
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^je termine la partie d'ordre (\d+)$/
     */
    public function jeTermineLaPartieDOrdre(int $position): void
    {
        Assert::assertNotNull($this->currentEvent, 'Aucun événement courant défini');

        $game = $this->findGameByPosition($position);
        $game->setStatus(GameStatus::FINISHED);
        $this->entityManager->flush();
    }

    #[When('je dégèle la partie')]
    public function jeDégèleLaPartie(): void
    {
        $game = $this->findGameByPosition(1);
        Assert::assertNotNull($game, 'Aucune partie trouvée à la position 1');

        // Dégeler directement sans passer par le contrôleur (logique métier simple)
        $game->setIsFrozen(false);
        $this->entityManager->flush();

        // Rafraîchir l'entité pour voir les changements
        $this->entityManager->refresh($game);
    }

    /**
     * @When /^j'exporte les gagnants de l'événement "([^"]*)"$/
     */
    public function jExporteLesGagnantsDeLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $this->client->request('GET', "/admin/events/{$event->getId()}/winners/export");
    }

    /**
     * @Then /^le fichier CSV doit contenir (\d+) lignes? de données$/
     */
    public function leFichierCsvDoitContenirLignesDeData(int $expectedCount): void
    {
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            // Debug: afficher l'URL de redirection si c'est une 302
            $redirectUrl = $response->headers->get('Location');
            throw new \RuntimeException("La requête n'a pas réussi. Status: {$statusCode}, Redirect: {$redirectUrl}");
        }

        Assert::assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'), 'Le type de contenu n\'est pas CSV');

        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Enlever la ligne d'en-tête
        array_shift($lines);

        // Compter les lignes de données non vides
        $dataLines = array_filter($lines, fn($line) => !empty(trim($line)));
        Assert::assertCount($expectedCount, $dataLines, 'Le nombre de lignes de données ne correspond pas');
    }

    /**
     * @Then /^la première ligne doit contenir "([^"]*)"$/
     */
    public function laPremiereDoitContenir(string $expectedContent): void
    {
        $response = $this->client->getResponse();
        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Enlever la ligne d'en-tête
        array_shift($lines);

        if (empty($lines)) {
            throw new \RuntimeException('Aucune ligne de données trouvée');
        }

        $firstLine = $lines[0];
        if (strpos($firstLine, $expectedContent) === false) {
            throw new \RuntimeException("La première ligne ne contient pas '{$expectedContent}'. Ligne trouvée: {$firstLine}");
        }
    }

    /**
     * @Then /^la deuxième ligne doit contenir "([^"]*)"$/
     */
    public function laDeuxiemeDoitContenir(string $expectedContent): void
    {
        $response = $this->client->getResponse();
        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Enlever la ligne d'en-tête
        array_shift($lines);

        if (count($lines) < 2) {
            throw new \RuntimeException('Pas assez de lignes de données (attendu: au moins 2, trouvé: ' . count($lines) . ')');
        }

        $secondLine = $lines[1];
        if (strpos($secondLine, $expectedContent) === false) {
            throw new \RuntimeException("La deuxième ligne ne contient pas '{$expectedContent}'. Ligne trouvée: {$secondLine}");
        }
    }

    /**
     * @Then /^le fichier CSV ne doit contenir aucune ligne de données$/
     */
    public function leFichierCsvNeDoitContenirAucuneLigneDeData(): void
    {
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $content = $response->getContent();
            throw new \RuntimeException("La requête n'a pas réussi. Status: {$statusCode}, Content: " . substr($content, 0, 500));
        }

        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Enlever la ligne d'en-tête
        array_shift($lines);

        // Vérifier qu'il n'y a aucune ligne de données non vide
        $dataLines = array_filter($lines, fn($line) => !empty(trim($line)));
        Assert::assertEmpty($dataLines, 'Le fichier CSV contient des lignes de données alors qu\'il ne devrait pas');
    }
}
