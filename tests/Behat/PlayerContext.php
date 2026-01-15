<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Card;
use App\Entity\Player;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\PlayerRepository;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;

final class PlayerContext implements Context
{
    private EntityManagerInterface $entityManager;
    private PlayerRepository $playerRepo;
    private EventRepository $eventRepo;
    private CardRepository $cardRepo;
    private ?Player $currentPlayer = null;
    private array $players = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        PlayerRepository $playerRepo,
        EventRepository $eventRepo,
        CardRepository $cardRepo,
    ) {
        $this->entityManager = $entityManager;
        $this->playerRepo = $playerRepo;
        $this->eventRepo = $eventRepo;
        $this->cardRepo = $cardRepo;
    }

    /**
     * @When /^je crée un joueur avec les informations suivantes:$/
     */
    public function jeCreerUnJoueurAvecLesInformationsSuivantes(TableNode $table): void
    {
        $data = $table->getHash()[0];

        $player = new Player();
        $player->setName($data['nom']);
        $player->setEmail($data['email'] ?? null);
        $player->setPhone($data['téléphone'] ?? null);

        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $this->currentPlayer = $player;
    }

    /**
     * @Then /^le joueur "([^"]*)" doit exister$/
     */
    public function leJoueurDoitExister(string $name): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $name]);
        Assert::assertNotNull($player, "Le joueur '{$name}' n'existe pas");
        $this->currentPlayer = $player;
    }

    /**
     * @Then /^le joueur "([^"]*)" doit avoir l'email "([^"]*)"$/
     */
    public function leJoueurDoitAvoirLEmail(string $name, string $expectedEmail): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $name]);
        Assert::assertNotNull($player, "Le joueur '{$name}' n'existe pas");
        Assert::assertEquals($expectedEmail, $player->getEmail(), "L'email du joueur ne correspond pas");
    }

    /**
     * @Then /^le joueur "([^"]*)" doit avoir le téléphone "([^"]*)"$/
     */
    public function leJoueurDoitAvoirLeTelephone(string $name, string $expectedPhone): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $name]);
        Assert::assertNotNull($player, "Le joueur '{$name}' n'existe pas");
        Assert::assertEquals($expectedPhone, $player->getPhone(), 'Le téléphone du joueur ne correspond pas');
    }

    /**
     * @Given /^qu'un joueur "([^"]*)" existe$/
     */
    public function quUnJoueurExiste(string $name): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $name]);

        if (!$player) {
            $player = new Player();
            $player->setName($name);
            $this->entityManager->persist($player);
            $this->entityManager->flush();
        }

        $this->currentPlayer = $player;
    }

    /**
     * @When /^j'associe le joueur "([^"]*)" à l'événement "([^"]*)"$/
     */
    public function jAssocieLeJoueurALEvenement(string $playerName, string $eventName): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $playerName]);
        Assert::assertNotNull($player, "Le joueur '{$playerName}' n'existe pas");

        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $player->setEvent($event);
        $this->entityManager->flush();
    }

    /**
     * @Then /^le joueur "([^"]*)" doit être associé à l'événement "([^"]*)"$/
     */
    public function leJoueurDoitEtreAssocieALEvenement(string $playerName, string $eventName): void
    {
        $player = $this->playerRepo->findOneBy(['name' => $playerName]);
        Assert::assertNotNull($player, "Le joueur '{$playerName}' n'existe pas");

        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        Assert::assertEquals($event->getId(), $player->getEvent()?->getId(), "Le joueur n'est pas associé à l'événement");
    }

    /**
     * @Given /^que les joueurs suivants sont associés à l'événement "([^"]*)":$/
     */
    public function queLesJoueursSuivantsSontAssociesALEvenement(string $eventName, TableNode $table): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        foreach ($table->getHash() as $row) {
            $player = $this->playerRepo->findOneBy(['name' => $row['nom']]);

            if (!$player) {
                $player = new Player();
                $player->setName($row['nom']);
                $this->entityManager->persist($player);
            }

            $player->setEvent($event);
            $this->players[] = $player;
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^je liste les joueurs de l'événement "([^"]*)"$/
     */
    public function jeListeLesJoueursDeLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $this->players = $this->playerRepo->findBy(['event' => $event]);
    }

    /**
     * @Then /^je dois voir (\d+) joueurs$/
     */
    public function jeDoisVoirJoueurs(int $expectedCount): void
    {
        Assert::assertCount($expectedCount, $this->players, 'Le nombre de joueurs ne correspond pas');
    }

    /**
     * @Then /^je dois voir les joueurs "([^"]*)"$/
     */
    public function jeDoisVoirLesJoueurs(string $names): void
    {
        $expectedNames = array_map('trim', explode(',', $names));
        $actualNames = array_map(fn ($p) => $p->getName(), $this->players);

        sort($expectedNames);
        sort($actualNames);

        Assert::assertEquals($expectedNames, $actualNames, 'Les noms des joueurs ne correspondent pas');
    }

    /**
     * @Given /^que les joueurs suivants ont des cartons pour l'événement "([^"]*)":$/
     */
    public function queLesJoueursSuivantsOntDesCartonsPourLEvenement(string $eventName, TableNode $table): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        foreach ($table->getHash() as $row) {
            $player = $this->playerRepo->findOneBy(['name' => $row['nom']]);

            if (!$player) {
                $player = new Player();
                $player->setName($row['nom']);
                $player->setEvent($event);
                $this->entityManager->persist($player);
            }

            $references = array_map('trim', explode(',', $row['références_cartons']));

            foreach ($references as $ref) {
                $card = $this->cardRepo->findOneBy(['reference' => $ref]);

                if (!$card) {
                    $card = new Card();
                    $card->setReference($ref);
                    $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
                    $this->entityManager->persist($card);
                }

                $card->setPlayer($player);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @When /^je désassocie tous les joueurs de leurs cartons pour l'événement "([^"]*)"$/
     */
    public function jeDesassocieTousLesJoueursDeLeurCartonsPourLEvenement(string $eventName): void
    {
        $event = $this->eventRepo->findOneBy(['name' => $eventName]);
        Assert::assertNotNull($event, "L'événement '{$eventName}' n'existe pas");

        $cards = $this->cardRepo->findByEvent($event);

        foreach ($cards as $card) {
            $card->setPlayer(null);
        }

        $this->entityManager->flush();
    }

    /**
     * @Then /^les cartons "([^"]*)" ne doivent plus avoir de joueurs associés$/
     */
    public function lesCartonsNeDoiventPlusAvoirDeJoueursAssocies(string $references): void
    {
        $refs = array_map('trim', explode(',', $references));

        foreach ($refs as $ref) {
            $card = $this->cardRepo->findOneBy(['reference' => $ref]);
            Assert::assertNotNull($card, "Le carton '{$ref}' n'existe pas");
            Assert::assertNull($card->getPlayer(), "Le carton '{$ref}' a encore un joueur associé");
        }
    }
}
