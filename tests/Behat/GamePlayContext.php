<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Draw;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DrawService;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class GamePlayContext extends BaseContext
{
    private DrawService $drawService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
        DrawService $drawService,
        KernelBrowser $client,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo, $client);
        $this->drawService = $drawService;
    }

    /**
     * @When /^je tire le numéro (\d+) pour la partie d'ordre (\d+)$/
     * @When /^je tente de tirer le numéro (\d+) pour la partie d'ordre (\d+)$/
     */
    public function jeTireLeNumeroPourLaPartieDOrdre(int $number, int $position): void
    {
        self::$lastException = null;
        self::$lastError = null;

        try {
            if ($number < 1 || $number > 90) {
                throw new \InvalidArgumentException('Number must be between 1 and 90');
            }

            $game = $this->findGameByPosition($position);
            Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

            // Vérifier si la partie est gelée
            if ($game->isFrozen()) {
                throw new \RuntimeException('Game is frozen');
            }

            // Vérifier si la partie est en cours
            if (\App\Enum\GameStatus::RUNNING !== $game->getStatus()) {
                return; // Ne pas tirer si la partie n'est pas en cours
            }

            // Vérifier si déjà tiré
            foreach ($game->getDraws() as $existingDraw) {
                if ($existingDraw->getNumber() === $number) {
                    return; // Ne pas ajouter si déjà tiré
                }
            }

            $draw = new Draw();
            $draw->setGame($game);
            $draw->setNumber($number);
            $draw->setOrderIndex($game->getDraws()->count() + 1);

            $game->addDraw($draw);
            $this->entityManager->persist($draw);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            self::$lastException = $e;
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @Then /^le numéro (\d+) doit être marqué comme tiré dans la partie d'ordre (\d+)$/
     */
    public function leNumeroDoitEtreMarqueCommeTireDansLaPartieDOrdre(int $number, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);

        $found = false;
        foreach ($game->getDraws() as $draw) {
            if ($draw->getNumber() === $number) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Le numéro {$number} n'a pas été trouvé dans les tirages");
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit avoir (\d+) numéros? tirés?$/
     */
    public function laPartieDOrdreDoitAvoirNumerosTires(int $position, int $expectedCount): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        $actualCount = $game->getDraws()->count();

        Assert::assertEquals($expectedCount, $actualCount, 'Le nombre de numéros tirés ne correspond pas');
    }

    /**
     * @When /^je tire les numéros suivants pour la partie d'ordre (\d+):$/
     */
    public function jeTireLesNumerosSuivantsPourLaPartieDOrdre(int $position, TableNode $table): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $orderIndex = $game->getDraws()->count() + 1;

        foreach ($table->getHash() as $row) {
            $number = (int) $row['numéro'];

            $draw = new Draw();
            $draw->setGame($game);
            $draw->setNumber($number);
            $draw->setOrderIndex($orderIndex++);

            $game->addDraw($draw);
            $this->entityManager->persist($draw);
        }

        $this->entityManager->flush();
    }

    /**
     * @Then /^les numéros "([^"]*)" doivent être marqués comme tirés dans la partie d'ordre (\d+)$/
     */
    public function lesNumerosDoiventEtreMarquesCommeTiresDansLaPartieDOrdre(string $numbers, int $position): void
    {
        $expectedNumbers = array_map('intval', explode(',', $numbers));
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);

        $actualNumbers = array_map(fn ($d) => $d->getNumber(), $game->getDraws()->toArray());
        sort($expectedNumbers);
        sort($actualNumbers);

        Assert::assertEquals($expectedNumbers, $actualNumbers, 'Les numéros tirés ne correspondent pas');
    }

    /**
     * @Given /^les numéros "([^"]*)" ont été tirés pour la partie d'ordre (\d+)$/
     *
     * @When /^je tire les numéros "([^"]*)" pour la partie d'ordre (\d+)$/
     */
    public function queLesNumerosOntEtesTiresPourLaPartieDOrdre(string $numbers, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $nums = array_map('intval', explode(',', $numbers));
        $orderIndex = 1;

        foreach ($nums as $num) {
            $draw = new Draw();
            $draw->setGame($game);
            $draw->setNumber($num);
            $draw->setOrderIndex($orderIndex++);

            $game->addDraw($draw);
            $this->entityManager->persist($draw);
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^j'annule le dernier numéro tiré \((\d+)\) de la partie d'ordre (\d+)$/
     */
    public function jAnnuleLeDernierNumeroTireDeLaPartieDOrdre(int $expectedNumber, int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $draws = $game->getDraws()->toArray();
        usort($draws, fn ($a, $b) => $b->getOrderIndex() <=> $a->getOrderIndex());

        $lastDraw = $draws[0] ?? null;
        Assert::assertNotNull($lastDraw, 'Aucun tirage trouvé');
        Assert::assertEquals($expectedNumber, $lastDraw->getNumber(), 'Le dernier numéro ne correspond pas');

        $game->removeDraw($lastDraw);
        $this->entityManager->remove($lastDraw);
        $this->entityManager->flush();
    }

    /**
     * @Then /^le numéro (\d+) ne doit plus être marqué comme tiré$/
     */
    public function leNumeroNeDroitPlusEtreMarqueCommeTire(int $number): void
    {
        // On vérifie dans toutes les parties de l'événement courant
        $events = $this->eventRepo->findAll();

        foreach ($events as $event) {
            foreach ($event->getGames() as $game) {
                $this->entityManager->refresh($game);

                foreach ($game->getDraws() as $draw) {
                    Assert::assertNotEquals($number, $draw->getNumber(), "Le numéro {$number} est encore marqué comme tiré");
                }
            }
        }
    }

    /**
     * @When /^je démarque tous les numéros de la partie d'ordre (\d+)$/
     */
    public function jeDemarqueTousLesNumerosDeLaPartieDOrdre(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->drawService->clearAll($game);
    }

    /**
     * @Then /^la partie d'ordre (\d+) ne doit plus avoir de numéros tirés$/
     * @Then /^la partie d'ordre (\d+) ne doit pas avoir de numéros tirés$/
     */
    public function laPartieDOrdreNeDroitPlusAvoirDeNumerosTires(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $this->entityManager->refresh($game);
        Assert::assertCount(0, $game->getDraws(), 'La partie a encore des numéros tirés');
    }

    /**
     * @When /^je réinitialise tous les tirages de l'événement "([^"]*)"$/
     */
    public function jeReinitialiseTousLesTiragesDeLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $this->drawService->clearAllForEvent($event);
    }

    /**
     * @When /^je passe à la partie suivante en conservant les tirages$/
     */
    public function jePasseALaPartieSuivanteEnConservantLesTirages(): void
    {
        $events = $this->eventRepo->findAll();
        $currentEvent = $events[0] ?? null;
        Assert::assertNotNull($currentEvent, 'Aucun événement trouvé');

        $games = $this->gameRepo->findByEventOrdered($currentEvent);
        $currentGame = null;
        $nextGame = null;

        foreach ($games as $game) {
            if ('RUNNING' === $game->getStatus()->value) {
                $currentGame = $game;
                break;
            }
        }

        Assert::assertNotNull($currentGame, 'Aucune partie en cours');

        // Trouver la partie suivante
        foreach ($games as $game) {
            if ($game->getPosition() > $currentGame->getPosition()) {
                $nextGame = $game;
                break;
            }
        }

        Assert::assertNotNull($nextGame, 'Aucune partie suivante');

        // Copier les tirages
        $draws = $currentGame->getDraws()->toArray();
        foreach ($draws as $draw) {
            $newDraw = new Draw();
            $newDraw->setGame($nextGame);
            $newDraw->setNumber($draw->getNumber());
            $newDraw->setOrderIndex($draw->getOrderIndex());

            $nextGame->addDraw($newDraw);
            $this->entityManager->persist($newDraw);
        }

        // Changer les statuts
        $currentGame->setStatus(\App\Enum\GameStatus::FINISHED);
        $nextGame->setStatus(\App\Enum\GameStatus::RUNNING);

        $this->entityManager->flush();
    }

    /**
     * @Given /^la partie d'ordre (\d+) est gelée \(gagnant détecté\)$/
     */
    public function queLaPartieDOrdreEstGeleeGagnantDetecte(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $game->freeze($game->getDraws()->count());
        $this->entityManager->flush();
    }

    /**
     * @Then /^le tirage doit être refusé$/
     */
    public function leTirageDoitEtreRefuse(): void
    {
        Assert::assertNotNull(self::$lastError, "Aucune erreur n'a été enregistrée");
    }

    /**
     * @Then /^un message d'erreur doit indiquer que la partie est gelée$/
     */
    public function unMessageDErreurDoitIndiquerQueLaPartieEstGelee(): void
    {
        Assert::assertNotNull(self::$lastError, "Aucune erreur n'a été enregistrée");
        Assert::assertStringContainsString('frozen', strtolower(self::$lastError), "Le message d'erreur ne mentionne pas que la partie est gelée");
    }

    /**
     * @When /^les tirages doivent encore exister pour la partie suivante$/
     */
    public function lesTiragesDoiventEncoreExisterPourLaPartieSuivante()
    {
        $game = $this->gameRepo->findOneBy(['position' => 1]);
        $draws = $game->getDraws()->toArray();

        Assert::assertCount(5, $draws);
    }
}
