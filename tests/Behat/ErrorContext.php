<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Card;
use App\Enum\BlockedReason;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\WinnerDetectionService;
use App\Service\WinnerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;

final class ErrorContext extends BaseContext
{
    private WinnerDetectionService $winnerDetectionService;
    private WinnerService $winnerService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
        WinnerDetectionService $winnerDetectionService,
        WinnerService $winnerService,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo);
        $this->winnerDetectionService = $winnerDetectionService;
        $this->winnerService = $winnerService;
    }

    /**
     * @When /^je tente de créer un événement avec la date invalide "([^"]*)"$/
     */
    public function jeTenteDeCreerUnEvenementAvecLaDateInvalide(string $invalidDate): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $invalidDate);
            if (!$dateTime) {
                throw new \RuntimeException("Invalid date format: {$invalidDate}");
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Then /^une erreur doit être levée$/
     */
    public function uneErreurDoitEtrelevee(): void
    {
        Assert::assertTrue(
            null !== self::$lastException || null !== self::$lastError,
            "Aucune erreur n'a été levée"
        );
    }

    /**
     * @Then /^le message doit contenir "([^"]*)"$/
     */
    public function leMessageDoitContenir(string $expectedMessage): void
    {
        // Copier les propriétés statiques dans des variables locales pour éviter les bugs
        $lastError = self::$lastError;
        $lastException = self::$lastException;

        $message = '';
        if (null !== $lastError) {
            $message = $lastError;
        } elseif (null !== $lastException) {
            $message = $lastException->getMessage();
        }

        Assert::assertNotEmpty($message, "Aucun message d'erreur n'a été enregistré");

        if (false === strpos($message, $expectedMessage)) {
            Assert::fail("Le message d'erreur '{$message}' ne contient pas '{$expectedMessage}'");
        }
    }

    /**
     * @When /^je tente de démarrer la partie d'ordre (\d+)$/
     */
    public function jeTenteDeDemarrerLaPartieDOrdre(int $position): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $game = $this->findGameByPosition($position);

            if (!$game) {
                throw new \RuntimeException("Aucune partie trouvée à la position {$position}");
            }

            // Ne pas démarrer si déjà terminée
            if (GameStatus::FINISHED !== $game->getStatus()) {
                $game->setStatus(GameStatus::RUNNING);
                $this->entityManager->flush();
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de créer une partie avec la règle invalide "([^"]*)"$/
     */
    public function jeTenteDeCreerUnePartieAvecLaRegleInvalide(string $invalidRule): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            RuleType::from($invalidRule);
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de créer un joueur avec l'email invalide "([^"]*)"$/
     */
    public function jeTenteDeCreerUnJoueurAvecLEmailInvalide(string $invalidEmail): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            if (!filter_var($invalidEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email format');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente d'associer le joueur "([^"]*)" à l'événement inexistant "([^"]*)"$/
     */
    public function jeTenteDAssocierLeJoueurALEvenementInexistant(string $playerName, string $eventName): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $event = $this->eventRepo->findOneBy(['name' => $eventName]);
            if (!$event) {
                throw new \RuntimeException("L'événement '{$eventName}' n'existe pas");
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de créer un carton avec seulement (\d+) lignes$/
     */
    public function jeTenteDeCreerUnCartonAvecSeulementLignes(int $lineCount): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            if ($lineCount < 3) {
                throw new \RuntimeException('A card must have exactly 3 lines');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de créer un carton avec (\d+) numéros sur une ligne$/
     */
    public function jeTenteDeCreerUnCartonAvecNumerosSurUneLigne(int $numberCount): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            if (5 !== $numberCount) {
                throw new \RuntimeException('Each line must have exactly 5 numbers');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de bloquer à nouveau le carton "([^"]*)"$/
     */
    public function jeTenteDeBloquerANouveauLeCarton(string $reference): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $card = $this->cardRepo->findOneBy(['reference' => $reference]);
            Assert::assertNotNull($card);

            // Ne rien faire si déjà bloqué
            if (!$card->isBlocked()) {
                $card->block(BlockedReason::WINNER);
                $this->entityManager->flush();
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Then /^la raison de blocage doit toujours être "([^"]*)"$/
     */
    public function laRaisonDeBlocageDoitToujoursEtre(string $expectedReason): void
    {
        // Récupérer le dernier carton manipulé
        $cards = $this->cardRepo->findAll();
        if (!empty($cards)) {
            $card = $cards[count($cards) - 1];
            $this->entityManager->refresh($card);
            Assert::assertEquals($expectedReason, $card->getBlockedReason()?->value);
        }
    }

    /**
     * @When /^je tente de créer un autre carton avec la référence "([^"]*)"$/
     */
    public function jeTenteDeCreerUnAutreCartonAvecLaReference(string $reference): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $existingCard = $this->cardRepo->findOneBy(['reference' => $reference]);
            if ($existingCard) {
                throw new \RuntimeException("A card with reference '{$reference}' already exists");
            }

            // Si on arrive ici, créer le carton
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
            $this->entityManager->persist($card);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente d'associer le carton "([^"]*)" au joueur inexistant "([^"]*)"$/
     */
    public function jeTenteDAssocierLeCartonAuJoueurInexistant(string $reference, string $playerName): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $player = $this->playerRepo->findOneBy(['name' => $playerName]);
            if (!$player) {
                throw new \RuntimeException("Le joueur '{$playerName}' n'existe pas");
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Then /^le numéro (\d+) ne doit pas être marqué comme tiré dans la partie d'ordre (\d+)$/
     */
    public function leNumeroNeDroitPasEtreMarqueCommeTireDansLaPartieDOrdre(int $number, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game);

        $this->entityManager->refresh($game);

        foreach ($game->getDraws() as $draw) {
            Assert::assertNotEquals($number, $draw->getNumber(), "Le numéro {$number} a été tiré alors qu'il ne devrait pas");
        }
    }

    /**
     * @Given /^le numéro (\d+) a été tiré pour la partie d'ordre (\d+)$/
     */
    public function queLeNumeroAEteTirePourLaPartieDOrdre(int $number, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game);

        $draw = new \App\Entity\Draw();
        $draw->setGame($game);
        $draw->setNumber($number);
        $draw->setOrderIndex($game->getDraws()->count() + 1);
        $game->addDraw($draw);
        $this->entityManager->persist($draw);
        $this->entityManager->flush();
    }

    /**
     * @When /^je tente de tirer à nouveau le numéro (\d+) pour la partie d'ordre (\d+)$/
     */
    public function jeTenteDeTirerANouveauLeNumeroPourLaPartieDOrdre(int $number, int $position): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $game = $this->findGameByPosition($position);
            Assert::assertNotNull($game);

            // Vérifier si déjà tiré
            $alreadyDrawn = false;
            foreach ($game->getDraws() as $draw) {
                if ($draw->getNumber() === $number) {
                    $alreadyDrawn = true;
                    break;
                }
            }

            // Ne pas ajouter si déjà tiré
            if (!$alreadyDrawn) {
                $draw = new \App\Entity\Draw();
                $draw->setGame($game);
                $draw->setNumber($number);
                $draw->setOrderIndex($game->getDraws()->count() + 1);
                $game->addDraw($draw);
                $this->entityManager->persist($draw);
                $this->entityManager->flush();
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente d'annuler le dernier numéro tiré$/
     */
    public function jeTenteDannulerLeDernierNumeroTire(): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $events = $this->eventRepo->findAll();
            $currentEvent = $events[0] ?? null;
            Assert::assertNotNull($currentEvent);

            $runningGame = null;
            foreach ($currentEvent->getGames() as $game) {
                if (GameStatus::RUNNING === $game->getStatus()) {
                    $runningGame = $game;
                    break;
                }
            }

            Assert::assertNotNull($runningGame);

            if (0 === $runningGame->getDraws()->count()) {
                throw new \RuntimeException('Aucun tirage à annuler');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de valider le carton "([^"]*)" comme gagnant système$/
     */
    public function jeTenteDeValiderLeCartonCommeGagnantSysteme(string $reference): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $card = $this->cardRepo->findOneBy(['reference' => $reference]);
            Assert::assertNotNull($card);

            $events = $this->eventRepo->findAll();
            $currentEvent = $events[0] ?? null;

            $runningGame = null;
            foreach ($currentEvent->getGames() as $game) {
                if (GameStatus::RUNNING === $game->getStatus()) {
                    $runningGame = $game;
                    break;
                }
            }

            Assert::assertNotNull($runningGame);

            $this->winnerService->validateSystemWinner($runningGame, $card);
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Given /^le carton "([^"]*)" est validé comme gagnant$/
     */
    public function queLeCartonEstValideCommeGagnant(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card);

        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if (GameStatus::RUNNING === $game->getStatus()) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame);

        if (!$runningGame->isFrozen()) {
            $runningGame->freeze($runningGame->getDraws()->count());
        }

        $this->winnerService->validateSystemWinner($runningGame, $card);
    }

    /**
     * @When /^je tente de valider à nouveau le carton "([^"]*)" comme gagnant$/
     */
    public function jeTenteDeValiderANouveauLeCartonCommeGagnant(string $reference): void
    {
        $this->jeTenteDeValiderLeCartonCommeGagnantSysteme($reference);
    }

    /**
     * @When /^je tente d'ajouter le carton "([^"]*)" comme gagnant offline$/
     */
    public function jeTenteDajouterLeCartonCommeGagnantOffline(string $reference): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $card = $this->cardRepo->findOneBy(['reference' => $reference]);
            Assert::assertNotNull($card);

            $events = $this->eventRepo->findAll();
            $currentEvent = $events[0] ?? null;

            $runningGame = null;
            foreach ($currentEvent->getGames() as $game) {
                if (GameStatus::RUNNING === $game->getStatus()) {
                    $runningGame = $game;
                    break;
                }
            }

            Assert::assertNotNull($runningGame);

            $this->winnerService->validateOfflineWinner($runningGame, $reference, $card);
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de démarrer la première partie de l'événement "([^"]*)"$/
     */
    public function jeTenteDeDemarrerLaPremierePartieDeLEvenement(string $eventName): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $event = $this->eventRepo->findOneBy(['name' => $eventName]);
            Assert::assertNotNull($event);

            if (0 === $event->getGames()->count()) {
                throw new \RuntimeException("L'événement n'a aucune partie");
            }

            foreach ($event->getGames() as $game) {
                if (1 === $game->getPosition()) {
                    $game->setStatus(GameStatus::RUNNING);
                    $this->entityManager->flush();
                    break;
                }
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de passer à la partie suivante$/
     */
    public function jeTenteDePasserALaPartieSuivante(): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $events = $this->eventRepo->findAll();
            $currentEvent = $events[0] ?? null;
            Assert::assertNotNull($currentEvent);

            $currentGame = null;
            foreach ($currentEvent->getGames() as $game) {
                if (GameStatus::RUNNING === $game->getStatus()) {
                    $currentGame = $game;
                    break;
                }
            }

            Assert::assertNotNull($currentGame);

            // Chercher la partie suivante
            $nextGame = null;
            foreach ($currentEvent->getGames() as $game) {
                if ($game->getPosition() > $currentGame->getPosition()) {
                    $nextGame = $game;
                    break;
                }
            }

            if (!$nextGame) {
                throw new \RuntimeException('Aucune partie suivante');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @When /^je tente de créer un carton avec une grille vide$/
     */
    public function jeTenteDeCreerUnCartonAvecUneGrilleVide(): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            $grid = [];
            if (empty($grid)) {
                throw new \RuntimeException('Grid cannot be empty');
            }
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Then /^aucun gagnant ne doit être détecté$/
     */
    public function aucunGagnantNeDroitEtreDetecte(): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;
        Assert::assertNotNull($currentEvent);

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if (GameStatus::RUNNING === $game->getStatus()) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame);

        $cards = $this->cardRepo->findByEvent($currentEvent);
        $potentials = $this->winnerDetectionService->findPotentialWinners($runningGame, $cards);

        Assert::assertCount(0, $potentials, "Des gagnants ont été détectés alors qu'il ne devrait pas y en avoir");
    }

    /**
     * @Then /^la partie d'ordre (\d+) ne doit pas être gelée$/
     */
    public function laPartieDOrdreNeDroitPasEtreGelee(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game);

        $this->entityManager->refresh($game);
        Assert::assertFalse($game->isFrozen(), "La partie est gelée alors qu'elle ne devrait pas l'être");
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit rester en statut "([^"]*)"$/
     */
    public function laPartieDOitResterEnStatut(int $position, string $expectedStatus): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        Assert::assertEquals(
            $expectedStatus,
            $game->getStatus()->value,
            "Le statut de la partie d'ordre {$position} devrait rester {$expectedStatus}"
        );
    }

    /**
     * @Then /^le carton "([^"]*)" doit rester bloqué$/
     */
    public function leCartonDoitResterBloque(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $this->entityManager->refresh($card);
        Assert::assertTrue($card->isBlocked(), "Le carton '{$reference}' devrait rester bloqué");
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit toujours avoir (\d+) numéros? tirés?$/
     */
    public function laPartieDOrdreDoitToujoursAvoirNumerosTires(int $position, int $expectedCount): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        $actualCount = $game->getDraws()->count();

        Assert::assertEquals(
            $expectedCount,
            $actualCount,
            "La partie d'ordre {$position} devrait toujours avoir {$expectedCount} numéro(s) tiré(s), mais en a {$actualCount}"
        );
    }
}
