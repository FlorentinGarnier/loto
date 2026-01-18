<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;

final class EventContext implements Context
{
    private EntityManagerInterface $entityManager;
    private ?Event $currentEvent = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Given /^je suis connecté en tant qu'administrateur$/
     */
    public function queJeSuisConnecteEnTantQuAdministrateur(): void
    {
        // Pour l'instant, on simule juste la connexion
        // Cette étape pourrait être étendue pour gérer une vraie authentification
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

    private function findGameByPosition(int $position): ?Game
    {
        if (!$this->currentEvent) {
            return null;
        }

        foreach ($this->currentEvent->getGames() as $game) {
            if ($game->getPosition() === $position) {
                return $game;
            }
        }

        return null;
    }
}
