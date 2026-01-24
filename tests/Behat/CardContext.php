<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Card;
use App\Entity\Player;
use App\Enum\BlockedReason;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class CardContext extends BaseContext
{
    private ?Card $currentCard = null;
    private array $cards = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
        KernelBrowser $client,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo, $client);
    }

    /**
     * @BeforeScenario
     */
    public function resetCardsArray(): void
    {
        $this->cards = [];
        $this->currentCard = null;
    }

    /**
     * @When /^je crée un carton avec la référence "([^"]*)" et la grille suivante:$/
     */
    public function jeCreerUnCartonAvecLaReferenceEtLaGrilleSuivante(string $reference, TableNode $table): void
    {
        $grid = [];

        foreach ($table->getHash() as $row) {
            $lineNumber = (int) $row['ligne'] - 1;
            $numbers = array_map('intval', explode(',', $row['numéros']));
            $grid[$lineNumber] = $numbers;
        }

        $card = new Card();
        $card->setReference($reference);
        $card->setGrid($grid);

        $this->entityManager->persist($card);
        $this->entityManager->flush();

        $this->currentCard = $card;
    }

    /**
     * @Then /^le carton "([^"]*)" doit exister$/
     */
    public function leCartonDoitExister(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");
        $this->currentCard = $card;
    }

    /**
     * @Then /^le carton "([^"]*)" doit contenir les numéros "([^"]*)" sur la ligne (\d+)$/
     */
    public function leCartonDoitContenirLesNumerosSurLaLigne(string $reference, string $numbers, int $line): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $expectedNumbers = array_map('intval', explode(',', $numbers));
        $actualNumbers = $card->getGrid()[$line - 1];

        Assert::assertEquals($expectedNumbers, $actualNumbers, "Les numéros de la ligne {$line} ne correspondent pas");
    }

    /**
     * @Given /^un carton "([^"]*)" existe$/
     */
    public function quUnCartonExiste(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);

        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
            $this->entityManager->persist($card);
            $this->entityManager->flush();
        }

        $this->currentCard = $card;
    }

    /**
     * @When /^j'associe le carton "([^"]*)" au joueur "([^"]*)"$/
     */
    public function jAssocieLeCartonAuJoueur(string $reference, string $playerName): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $player = $this->playerRepo->findOneBy(['name' => $playerName]);
        Assert::assertNotNull($player, "Le joueur '{$playerName}' n'existe pas");

        $card->setPlayer($player);
        $this->entityManager->flush();
    }

    /**
     * @Then /^le carton "([^"]*)" doit être associé au joueur "([^"]*)"$/
     */
    public function leCartonDoitEtreAssocieAuJoueur(string $reference, string $playerName): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $player = $card->getPlayer();
        Assert::assertNotNull($player, "Le carton n'a pas de joueur associé");
        Assert::assertEquals($playerName, $player->getName(), 'Le joueur associé ne correspond pas');
    }

    /**
     * @When /^je bloque le carton "([^"]*)" pour la raison "([^"]*)"$/
     */
    public function jeBloqueLeCartonPourLaRaison(string $reference, string $reason): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $card->block(BlockedReason::from($reason));
        $this->entityManager->flush();
    }

    /**
     * @Then /^le carton "([^"]*)" doit être bloqué$/
     */
    public function leCartonDoitEtreBloque(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $this->entityManager->refresh($card);
        Assert::assertTrue($card->isBlocked(), "Le carton n'est pas bloqué");
    }

    /**
     * @Then /^le carton "([^"]*)" doit avoir la raison de blocage "([^"]*)"$/
     */
    public function leCartonDoitAvoirLaRaisonDeBlocage(string $reference, string $expectedReason): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $this->entityManager->refresh($card);
        Assert::assertEquals($expectedReason, $card->getBlockedReason()?->value, 'La raison de blocage ne correspond pas');
    }

    /**
     * @Given /^le carton "([^"]*)" est bloqué pour la raison "([^"]*)"$/
     */
    public function queLeCartonEstBloquePourLaRaison(string $reference, string $reason): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $card->block(BlockedReason::from($reason));
        $this->entityManager->flush();
    }

    /**
     * @When /^je débloque le carton "([^"]*)"$/
     */
    public function jeDébloqueLeCarton(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $card->unblock();
        $this->entityManager->flush();
    }

    /**
     * @Then /^le carton "([^"]*)" ne doit plus être bloqué$/
     */
    public function leCartonNeDroitPlusEtreBloque(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        $this->entityManager->refresh($card);
        Assert::assertFalse($card->isBlocked(), 'Le carton est encore bloqué');
    }

    /**
     * @Given /^les cartons suivants existent pour l'événement "([^"]*)":$/
     */
    public function queLesCartonsSuivantsExistentPourLEvenement(string $eventName, TableNode $table): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        foreach ($table->getHash() as $row) {
            $card = $this->cardRepo->findOneBy(['reference' => $row['référence']]);

            if (!$card) {
                $card = new Card();
                $card->setReference($row['référence']);
                $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
                $this->entityManager->persist($card);
            }

            if (!empty($row['joueur'])) {
                $player = $this->playerRepo->findOneBy(['name' => $row['joueur']]);

                if (!$player) {
                    $player = new Player();
                    $player->setName($row['joueur']);
                    $player->setEvent($event);
                    $this->entityManager->persist($player);
                }

                $card->setPlayer($player);
            }

            $this->cards[] = $card;
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^je liste les cartons de l'événement "([^"]*)"$/
     */
    public function jeListeLesCartonsDeLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        // Si on a déjà créé des cartons dans ce contexte (via "les cartons suivants existent"),
        // on les utilise car ils incluent les cartons sans joueur
        if (empty($this->cards)) {
            $this->cards = $this->cardRepo->findByEvent($event);
        }
        // Sinon, on garde les cartons déjà créés dans $this->cards
    }

    /**
     * @Then /^je dois voir (\d+) cartons$/
     */
    public function jeDoisVoirCartons(int $expectedCount): void
    {
        Assert::assertCount($expectedCount, $this->cards, 'Le nombre de cartons ne correspond pas');
    }

    /**
     * @Then /^le carton "([^"]*)" ne doit pas avoir de joueur associé$/
     */
    public function leCartonNeDroitPasAvoirDeJoueurAssocie(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton '{$reference}' n'existe pas");

        Assert::assertNull($card->getPlayer(), 'Le carton a un joueur associé');
    }
}
