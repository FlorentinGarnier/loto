<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Command\SeedStagingCommand;
use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DrawService;
use App\Service\StagingDatasetLoader;
use App\Service\WinnerDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DatasetContext extends BaseContext
{
    private array $summary = [];
    private string $firstFingerprint = '';
    private ?int $commandStatus = null;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
        KernelBrowser $client,
        private readonly StagingDatasetLoader $loader,
        private readonly DrawService $drawService,
        private readonly WinnerDetectionService $winnerDetectionService,
    ) {
        parent::__construct($entityManager, $eventRepo, $gameRepo, $cardRepo, $playerRepo, $client);
    }

    /**
     * @Given /^je charge le jeu de données de recette$/
     */
    public function jeChargeLeJeuDeDonneesDeRecette(): void
    {
        $this->summary = $this->loader->load();
    }

    /**
     * @When /^je recharge le jeu de données de recette$/
     */
    public function jeRechargeLeJeuDeDonneesDeRecette(): void
    {
        $this->firstFingerprint = $this->fingerprint();
        $this->cleanDatabase();
        $this->entityManager->clear();
        $this->summary = $this->loader->load();
    }

    /**
     * @Then /^les cartons, les joueurs et les tirages doivent être identiques au premier chargement$/
     */
    public function lesDonneesDoiventEtreIdentiques(): void
    {
        Assert::assertNotSame('', $this->firstFingerprint);
        Assert::assertSame($this->firstFingerprint, $this->fingerprint());
    }

    /**
     * @Then /^l'événement le plus récent doit être "([^"]*)"$/
     */
    public function lEvenementLePlusRecentDoitEtre(string $name): void
    {
        Assert::assertSame($name, $this->latestEvent()->getName());
    }

    /**
     * @Then /^l'événement le plus récent doit compter (\d+) parties et (\d+) joueurs$/
     */
    public function lEvenementLePlusRecentDoitCompter(int $games, int $players): void
    {
        $event = $this->latestEvent();
        Assert::assertCount($games, $this->gameRepo->findByEventOrdered($event));
        Assert::assertCount($players, $this->playerRepo->findBy(['event' => $event]));
    }

    /**
     * @Then /^il doit y avoir (\d+) événements$/
     */
    public function ilDoitYAvoirEvenements(int $count): void
    {
        Assert::assertSame($count, $this->eventRepo->count([]));
    }

    /**
     * @Then /^les (\d+) premières parties de la soirée doivent être terminées avec un gagnant validé$/
     */
    public function lesPremieresPartiesDoiventEtreTerminees(int $count): void
    {
        foreach (array_slice($this->gameRepo->findByEventOrdered($this->latestEvent()), 0, $count) as $game) {
            Assert::assertSame(GameStatus::FINISHED, $game->getStatus(), 'Partie '.$game->getPosition());
            Assert::assertGreaterThanOrEqual(1, $game->getWinners()->count(), 'Partie '.$game->getPosition());
            foreach ($game->getWinners() as $winner) {
                if (null !== $winner->getCard()) {
                    Assert::assertTrue($winner->getCard()->isBlocked(), 'Le carton gagnant doit être bloqué');
                }
            }
        }
    }

    /**
     * @Then /^la partie d'ordre (\d+) de la soirée doit être en cours et non gelée$/
     */
    public function laPartieDeLaSoireeDoitEtreEnCours(int $position): void
    {
        $game = $this->runningGame();
        Assert::assertSame($position, $game->getPosition());
        Assert::assertFalse($game->isFrozen());
        Assert::assertGreaterThan(0, $game->getDraws()->count());
        Assert::assertSame([], $this->winnerDetectionService->findPotentialWinners($game, $this->cardsInPlay()));
    }

    /**
     * @Then /^(\d+) cartons doivent rester libres$/
     */
    public function cartonsDoiventResterLibres(int $count): void
    {
        Assert::assertCount($count, $this->cardRepo->findBy(['player' => null]));
    }

    /**
     * @When /^je tire le numéro gagnant annoncé par le jeu de données$/
     */
    public function jeTireLeNumeroGagnantAnnonce(): void
    {
        $this->drawService->toggleNumber($this->runningGame(), $this->summary['nextWinningNumber'], $this->cardsInPlay());
    }

    /**
     * @Then /^la partie en cours de la soirée doit être gelée$/
     */
    public function laPartieEnCoursDoitEtreGelee(): void
    {
        Assert::assertTrue($this->runningGame()->isFrozen());
    }

    /**
     * @Then /^les cartons annoncés par le jeu de données doivent être les gagnants potentiels$/
     */
    public function lesCartonsAnnoncesDoiventEtreLesGagnantsPotentiels(): void
    {
        $references = static fn (array $cards): array => array_map(static fn (Card $card): string => $card->getReference(), $cards);
        $potentials = $this->winnerDetectionService->findPotentialWinners($this->runningGame(), $this->cardsInPlay());

        Assert::assertNotSame([], $this->summary['nextWinningCards']);
        Assert::assertSame($references($this->summary['nextWinningCards']), $references(array_column($potentials, 'card')));
    }

    /**
     * @When /^je lance la commande du jeu de données de recette (sans|avec) autorisation$/
     */
    public function jeLanceLaCommande(string $authorization): void
    {
        $tester = new CommandTester(new SeedStagingCommand($this->entityManager, $this->loader, 'avec' === $authorization));
        $this->commandStatus = $tester->execute([], ['interactive' => false]);
    }

    /**
     * @Then /^la commande du jeu de données doit être refusée$/
     */
    public function laCommandeDoitEtreRefusee(): void
    {
        Assert::assertSame(Command::FAILURE, $this->commandStatus);
    }

    /**
     * @Then /^la commande du jeu de données doit réussir$/
     */
    public function laCommandeDoitReussir(): void
    {
        Assert::assertSame(Command::SUCCESS, $this->commandStatus);
    }

    private function latestEvent(): Event
    {
        $event = $this->eventRepo->findLatest();
        Assert::assertNotNull($event, 'Aucun événement en base');

        return $event;
    }

    private function runningGame(): Game
    {
        $game = $this->gameRepo->findRunningByEvent($this->latestEvent());
        Assert::assertNotNull($game, 'Aucune partie en cours');

        return $game;
    }

    /** @return Card[] */
    private function cardsInPlay(): array
    {
        return $this->cardRepo->findByEvent($this->latestEvent());
    }

    private function fingerprint(): string
    {
        $cards = array_map(static fn (Card $card): array => [
            $card->getReference(),
            $card->getGrid(),
            $card->getPlayer()?->getName(),
            $card->getPlayer()?->getEmail(),
            $card->getPlayer()?->getPhone(),
            $card->isBlocked(),
        ], $this->cardRepo->findBy([], ['id' => 'ASC']));

        $draws = [];
        foreach ($this->runningGame()->getDraws() as $draw) {
            $draws[$draw->getOrderIndex()] = $draw->getNumber();
        }

        return json_encode([$cards, $draws, $this->summary['nextWinningNumber']], JSON_THROW_ON_ERROR);
    }
}
