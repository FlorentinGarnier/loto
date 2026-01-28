<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Card;
use App\Entity\Draw;
use App\Entity\Player;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\WinnerDetectionService;
use App\Service\WinnerService;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class WinnerContext extends BaseContext
{
    private WinnerDetectionService $winnerDetectionService;
    private WinnerService $winnerService;
    private array $potentialWinners = [];
    private array $publishedState = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
        WinnerDetectionService $winnerDetectionService,
        WinnerService $winnerService,
        KernelBrowser $browser,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo, $browser);
        $this->winnerDetectionService = $winnerDetectionService;
        $this->winnerService = $winnerService;
    }

    /**
     * @BeforeScenario
     */
    public function resetContextState(): void
    {
        $this->potentialWinners = [];
        $this->publishedState = [];
    }

    /**
     * @Given /^un carton "([^"]*)" existe avec la grille suivante:$/
     */
    public function quUnCartonExisteAvecLaGrilleSuivante(string $reference, TableNode $table): void
    {
        $grid = [];

        foreach ($table->getHash() as $row) {
            $lineNumber = (int) $row['ligne'] - 1;
            $numbers = array_map('intval', explode(',', $row['numéros']));
            $grid[$lineNumber] = $numbers;
        }

        $card = $this->cardRepo->findOneBy(['reference' => $reference]);

        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $this->entityManager->persist($card);
        }

        $card->setGrid($grid);

        // Créer un joueur fictif pour que findByEvent fonctionne
        if (!$card->getPlayer()) {
            $events = $this->eventRepo->findAll();
            if (!empty($events)) {
                $player = new Player();
                $player->setName('Player_'.$reference);
                $player->setEvent($events[0]);
                $this->entityManager->persist($player);
                $card->setPlayer($player);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^la détection automatique s'exécute$/
     */
    public function laDetectionAutomatiqueExecute(): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;
        Assert::assertNotNull($currentEvent, 'Aucun événement trouvé');

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        $cards = $this->cardRepo->findByEvent($currentEvent);
        $freezeOrderIndex = $this->winnerDetectionService->checkForWinners($runningGame, $cards);

        if (null !== $freezeOrderIndex && !$runningGame->isFrozen()) {
            $runningGame->freeze($freezeOrderIndex);
            $this->entityManager->flush();
        }

        $this->potentialWinners = $this->winnerDetectionService->findPotentialWinners($runningGame, $cards);
    }

    /**
     * @Given /^la partie d'ordre (\d+) est marquée "salle uniquement"$/
     */
    public function laPartieDOrdreEstMarqueeSalleUniquement(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $game->setHallOnly(true);
        $this->entityManager->flush();
    }

    /**
     * @Then /^le carton "([^"]*)" ne doit pas être détecté comme gagnant potentiel$/
     */
    public function leCartonNeDoitPasEtreDetecteCommeGagnantPotentiel(string $reference): void
    {
        $found = false;

        foreach ($this->potentialWinners as $potential) {
            if ($potential['card']->getReference() === $reference) {
                $found = true;
                break;
            }
        }

        Assert::assertFalse($found, "Le carton '{$reference}' a été détecté comme gagnant potentiel (alors qu'il ne devrait pas)");
    }
    /**
     * @Then /^le carton "([^"]*)" doit être détecté comme gagnant potentiel$/
     */
    public function leCartonDoitEtreDetecteCommeGagnantPotentiel(string $reference): void
    {
        $found = false;

        foreach ($this->potentialWinners as $potential) {
            if ($potential['card']->getReference() === $reference) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Le carton '{$reference}' n'a pas été détecté comme gagnant potentiel");
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit être gelée$/
     */
    public function laPartieDOrdreDoitEtreGelee(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        Assert::assertTrue($game->isFrozen(), "La partie n'est pas gelée");
    }

    /**
     * @Then /^l'index de gel doit correspondre au numéro (\d+)$/
     */
    public function lIndexDeGelDoitCorrespondreAuNumero(int $number): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;
        Assert::assertNotNull($currentEvent, 'Aucun événement trouvé');

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');
        $this->entityManager->refresh($runningGame);

        // Vérifier que l'index de gel correspond au dernier tirage qui contenait le numéro
        $freezeOrderIndex = $runningGame->getFreezeOrderIndex();
        Assert::assertNotNull($freezeOrderIndex, "Pas d'index de gel");

        // Trouver le tirage avec ce numéro
        $found = false;
        foreach ($runningGame->getDraws() as $draw) {
            if ($draw->getNumber() === $number && $draw->getOrderIndex() === $freezeOrderIndex) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Le numéro {$number} ne correspond pas à l'index de gel");
    }

    /**
     * @Given /^tous les numéros du carton "([^"]*)" ont été tirés$/
     */
    public function queTousLesNumerosduCartonOntEtesTires(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;
        Assert::assertNotNull($currentEvent, 'Aucun événement trouvé');

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        $allNumbers = [];
        foreach ($card->getGrid() as $line) {
            foreach ($line as $num) {
                $allNumbers[] = $num;
            }
        }

        $orderIndex = 1;
        foreach ($allNumbers as $num) {
            $draw = new Draw();
            $draw->setGame($runningGame);
            $draw->setNumber($num);
            $draw->setOrderIndex($orderIndex++);

            $runningGame->addDraw($draw);
            $this->entityManager->persist($draw);
        }

        $this->entityManager->flush();
    }

    /**
     * @Given /^un carton "([^"]*)" est détecté comme gagnant potentiel$/
     */
    public function quUnCartonEstDetecteCommeGagnantPotentiel(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);

        // Créer le carton s'il n'existe pas
        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([
                [5, 15, 23, 45, 67],
                [8, 12, 34, 56, 78],
                [2, 18, 29, 43, 89],
            ]);
            $this->entityManager->persist($card);

            // Créer un joueur fictif pour que findByEvent fonctionne
            $events = $this->eventRepo->findAll();
            if (!empty($events)) {
                $player = new Player();
                $player->setName('Player_'.$reference);
                $player->setEvent($events[0]);
                $this->entityManager->persist($player);
                $card->setPlayer($player);
            }

            $this->entityManager->flush();
        }

        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        // Tirer les numéros de la première ligne pour que le carton soit gagnant (QUINE)
        $firstLine = $card->getGrid()[0];
        $orderIndex = 1;
        foreach ($firstLine as $number) {
            $draw = new Draw();
            $draw->setGame($runningGame);
            $draw->setNumber($number);
            $draw->setOrderIndex($orderIndex++);
            $runningGame->addDraw($draw);
            $this->entityManager->persist($draw);
        }
        $this->entityManager->flush();

        $cards = $this->cardRepo->findByEvent($currentEvent);
        $this->potentialWinners = $this->winnerDetectionService->findPotentialWinners($runningGame, $cards);

        $found = false;
        foreach ($this->potentialWinners as $potential) {
            if ($potential['card']->getReference() === $reference) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Le carton n'est pas un gagnant potentiel");
    }

    /**
     * @When /^je valide le carton "([^"]*)" comme gagnant$/
     */
    public function jeValidleLeCartonCommeGagnant(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        $this->winnerService->validateSystemWinner($runningGame, $card);
    }

    /**
     * @Then /^un gagnant de source "([^"]*)" doit être enregistré pour la partie d'ordre (\d+)$/
     */
    public function unGagnantDeSourceDoitEtreEnregistrePourLaPartieDOrdre(string $source, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);

        $found = false;
        foreach ($game->getWinners() as $winner) {
            if ($winner->getSource()->value === $source) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Aucun gagnant de source '{$source}' trouvé");
    }

    /**
     * @Then /^le gagnant doit référencer le carton "([^"]*)"$/
     */
    public function leGagnantDoitReferencerLeCarton(string $reference): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $found = false;
        foreach ($currentEvent->getGames() as $game) {
            foreach ($game->getWinners() as $winner) {
                if ($winner->getCard() && $winner->getCard()->getReference() === $reference) {
                    $found = true;
                    break 2;
                }
            }
        }

        Assert::assertTrue($found, "Aucun gagnant ne référence le carton '{$reference}'");
    }

    /**
     * @Then /^le carton "([^"]*)" doit être bloqué avec la raison "([^"]*)"$/
     */
    public function leCartonDoitEtreBloquAvecLaRaison(string $reference, string $reason): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $this->entityManager->refresh($card);

        Assert::assertTrue($card->isBlocked(), "Le carton n'est pas bloqué");
        Assert::assertEquals($reason, $card->getBlockedReason()?->value, 'La raison de blocage ne correspond pas');
    }

    /**
     * @When /^j'ajoute manuellement le carton "([^"]*)" comme gagnant offline$/
     */
    public function jAjouteManuellementLeCartonCommeGagnantOffline(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        $this->winnerService->validateOfflineWinner($runningGame, $reference, $card);
    }

    /**
     * @When /^j'ajoute manuellement la référence "([^"]*)" comme gagnant offline sans carton$/
     */
    public function jAjouteManuellementLaReferenceCommeGagnantOfflineSansCarton(string $reference): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        Assert::assertNotNull($runningGame, 'Aucune partie en cours');

        $this->winnerService->validateOfflineWinner($runningGame, $reference, null);
    }

    /**
     * @Then /^le gagnant doit avoir la référence "([^"]*)"$/
     */
    public function leGagnantDoitAvoirLaReference(string $reference): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $found = false;
        foreach ($currentEvent->getGames() as $game) {
            foreach ($game->getWinners() as $winner) {
                if ($winner->getReference() === $reference) {
                    $found = true;
                    break 2;
                }
            }
        }

        Assert::assertTrue($found, "Aucun gagnant avec la référence '{$reference}' trouvé");
    }

    /**
     * @Given /^les cartons suivants existent:$/
     */
    public function queLesCartonsSuivantsExistent(TableNode $table): void
    {
        // Récupérer l'événement courant
        $events = $this->eventRepo->findAll();
        $currentEvent = !empty($events) ? $events[0] : null;

        foreach ($table->getHash() as $row) {
            $card = $this->cardRepo->findOneBy(['reference' => $row['référence']]);

            if (!$card) {
                $card = new Card();
                $card->setReference($row['référence']);
                $this->entityManager->persist($card);
            }

            $numbers = array_map('intval', explode(',', $row['ligne_1_numéros']));
            $grid = [
                $numbers,
                [6, 7, 8, 9, 10],
                [11, 12, 13, 14, 15],
            ];

            $card->setGrid($grid);

            // Créer un joueur fictif pour que findByEvent fonctionne
            if (!$card->getPlayer() && $currentEvent) {
                $player = new Player();
                $player->setName('Player_'.$row['référence']);
                $player->setEvent($currentEvent);
                $this->entityManager->persist($player);
                $card->setPlayer($player);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @Then /^(\d+) cartons? gagnants? potentiels? doit être détectés?$/
     */
    public function cartonsGagnantsPotentielsDoitEtreDetectes(int $expectedCount): void
    {
        Assert::assertCount($expectedCount, $this->potentialWinners, 'Le nombre de gagnants potentiels ne correspond pas');
    }

    /**
     * @Then /^le carton "([^"]*)" doit être dans la liste des gagnants potentiels$/
     */
    public function leCartonDoitEtreDansLaListeDesGagnantsPotentiels(string $reference): void
    {
        $found = false;

        foreach ($this->potentialWinners as $potential) {
            if ($potential['card']->getReference() === $reference) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Le carton '{$reference}' n'est pas dans la liste des gagnants potentiels");
    }

    /**
     * @Then /^le carton "([^"]*)" ne doit pas être dans la liste des gagnants potentiels$/
     */
    public function leCartonNeDroitPasEtreDansLaListeDesGagnantsPotentiels(string $reference): void
    {
        foreach ($this->potentialWinners as $potential) {
            Assert::assertNotEquals($reference, $potential['card']->getReference(), "Le carton '{$reference}' est dans la liste des gagnants potentiels");
        }
    }

    /**
     * @Given /^la partie d'ordre (\d+) a un gagnant validé$/
     */
    public function queLaPartieDOrdreAUnGagnantValide(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $card = new Card();
        $card->setReference('WINNER_'.$position.'_'.uniqid());
        $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
        $this->entityManager->persist($card);

        $game->freeze(1);
        $this->winnerService->validateSystemWinner($game, $card);
    }

    /**
     * @When /^je réinitialise tous les gagnants de l'événement "([^"]*)"$/
     */
    public function jeReinitialiseTousLesGagnantsDeLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $this->winnerService->clearAllForEvent($event);
    }

    /**
     * @Then /^la partie d'ordre (\d+) ne doit plus avoir de gagnants$/
     */
    public function laPartieDOrdreNeDroitPlusAvoirDeGagnants(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        Assert::assertCount(0, $game->getWinners(), 'La partie a encore des gagnants');
    }

    /**
     * @Then /^tous les cartons bloqués doivent être débloqués$/
     */
    public function tousLesCartonsBloquesDoiventEtreDebloques(): void
    {
        $cards = $this->cardRepo->findAll();

        foreach ($cards as $card) {
            $this->entityManager->refresh($card);
            Assert::assertFalse($card->isBlocked(), "Le carton '{$card->getReference()}' est encore bloqué");
        }
    }

    /**
     * @Given /^un carton "([^"]*)" avec joueur "([^"]*)" existe avec la grille suivante:$/
     */
    public function quUnCartonAvecJoueurExisteAvecLaGrilleSuivante(string $reference, string $playerName, TableNode $table): void
    {
        $grid = [];

        foreach ($table->getHash() as $row) {
            $lineNumber = (int) $row['ligne'] - 1;
            $numbers = array_map('intval', explode(',', $row['numéros']));
            $grid[$lineNumber] = $numbers;
        }

        $card = $this->cardRepo->findOneBy(['reference' => $reference]);

        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $this->entityManager->persist($card);
        }

        $card->setGrid($grid);

        // Créer ou récupérer le joueur
        $player = $this->playerRepo->findOneBy(['name' => $playerName]);

        if (!$player) {
            $player = new Player();
            $player->setName($playerName);

            // Associer le joueur à l'événement courant pour que findByEvent fonctionne
            $events = $this->eventRepo->findAll();
            if (!empty($events)) {
                $player->setEvent($events[0]);
            }

            $this->entityManager->persist($player);
        }

        $card->setPlayer($player);
        $this->entityManager->flush();
    }

    /**
     * @When /^la détection automatique s'exécute et publie l'état via Mercure$/
     */
    public function laDetectionAutomatiqueExecuteEtPublieLEtatViaMercure(): void
    {
        $this->laDetectionAutomatiqueExecute();

        // Simuler la publication Mercure
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;

        $runningGame = null;
        foreach ($currentEvent->getGames() as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $runningGame = $game;
                break;
            }
        }

        $detectedCard = null;
        if ($runningGame->isFrozen() && count($this->potentialWinners) > 0) {
            $firstPotential = $this->potentialWinners[0];
            $card = $firstPotential['card'];

            // Rafraîchir l'entité pour éviter les erreurs de lazy loading
            $this->entityManager->refresh($card);

            $detectedCard = [
                'reference' => $card->getReference(),
                'grid' => $card->getFormattedGrid(),
                'player' => $card->getPlayer() ? $card->getPlayer()->getName() : null,
            ];
        }

        $this->publishedState = [
            'detectedCard' => $detectedCard,
        ];
    }

    /**
     * @Then /^l'état publié doit contenir les informations du carton détecté:$/
     */
    public function lEtatPublieDoitContenirLesInformationsduCartonDetecte(TableNode $table): void
    {
        Assert::assertArrayHasKey('detectedCard', $this->publishedState, "La clé 'detectedCard' n'existe pas dans l'état publié");
        Assert::assertNotNull($this->publishedState['detectedCard'], "Aucun carton détecté dans l'état publié");

        $rows = $table->getHash();
        foreach ($rows as $row) {
            $field = $row['champ'];
            $expectedValue = $row['valeur'];

            Assert::assertArrayHasKey($field, $this->publishedState['detectedCard'], "Le champ '{$field}' n'existe pas dans detectedCard");
            Assert::assertEquals($expectedValue, $this->publishedState['detectedCard'][$field], "Le champ '{$field}' ne correspond pas");
        }
    }

    /**
     * @Then /^l'état publié doit contenir la grille formatée du carton$/
     */
    public function lEtatPublieDoitContenirLaGrilleFormateeduCarton(): void
    {
        Assert::assertNotNull($this->publishedState['detectedCard'], "Aucun carton détecté dans l'état publié");
        Assert::assertArrayHasKey('grid', $this->publishedState['detectedCard'], "Pas de grille dans l'état publié");
        Assert::assertIsArray($this->publishedState['detectedCard']['grid'], "La grille n'est pas un tableau");
    }
}
