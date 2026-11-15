<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Jeu de données de recette : une soirée de loto en cours et un événement passé.
 *
 * Tout est tiré d'une graine fixe : chaque chargement donne les mêmes joueurs, les mêmes cartons et les mêmes
 * tirages. La soirée est jouée par les services de l'application, comme le ferait l'animateur, pour que l'état
 * obtenu soit un état que l'application sait produire.
 *
 * Aucune donnée réelle : adresses en example.test, téléphones dans la tranche 06 39 98 réservée à la fiction.
 */
final class StagingDatasetLoader
{
    public const EVENT_NAME = 'Grand loto du comité des fêtes';
    public const PAST_EVENT_NAME = 'Loto de printemps';

    private const SEED = 20261115;
    private const CARD_COUNT = 150;
    /** Nombre de cartons achetés par joueur, répété sur la liste des joueurs. */
    private const CARDS_PER_PLAYER = [3, 2, 4, 1, 6, 3, 2, 4, 3, 2];
    /** Les derniers joueurs sont inscrits sans carton : ils servent à tester l'attribution. */
    private const PLAYERS_WITHOUT_CARD = 4;

    private const GAMES = [
        [RuleType::QUINE, 'Panier garni'],
        [RuleType::DOUBLE_QUINE, 'Bon d\'achat de 50 €'],
        [RuleType::FULL_CARD, 'Robot pâtissier'],
        [RuleType::QUINE, 'Coffret de vins'],
        [RuleType::DOUBLE_QUINE, 'Jambon sec'],
        [RuleType::FULL_CARD, 'Vélo de ville'],
        [RuleType::QUINE, 'Corbeille de fruits'],
        [RuleType::DOUBLE_QUINE, 'Machine à café'],
        [RuleType::FULL_CARD, 'Week-end en gîte'],
        [RuleType::QUINE, 'Plateau de fromages'],
        [RuleType::DOUBLE_QUINE, 'Bon d\'achat de 100 €'],
        [RuleType::FULL_CARD, 'Téléviseur'],
    ];
    /** Partie « salle uniquement » : pas de détection automatique. */
    private const HALL_ONLY_POSITION = 7;

    private const PAST_GAMES = [
        [RuleType::QUINE, 'Filet garni', 'Carton 0118'],
        [RuleType::DOUBLE_QUINE, 'Bon d\'achat de 30 €', 'Carton 0342'],
        [RuleType::FULL_CARD, 'Aspirateur', 'Carton 0057'],
        [RuleType::QUINE, 'Coffret de bières', 'Carton 0291'],
        [RuleType::DOUBLE_QUINE, 'Service à raclette', 'Carton 0406'],
        [RuleType::FULL_CARD, 'Tablette', 'Carton 0173'],
    ];

    private const FIRST_NAMES = [
        'Josiane', 'Bernard', 'Monique', 'Gérard', 'Nicole', 'Michel', 'Sylvie', 'Alain', 'Martine', 'Patrick',
        'Chantal', 'Daniel', 'Françoise', 'Jean-Pierre', 'Nathalie', 'Thierry', 'Isabelle', 'Christophe', 'Sandrine', 'Laurent',
        'Céline', 'Stéphane', 'Aurélie', 'Julien', 'Émilie', 'Nicolas', 'Laura', 'Kevin', 'Manon', 'Romain',
        'Yvette', 'René', 'Ginette', 'Roger', 'Odile', 'Hervé', 'Karine', 'Fabrice', 'Élodie', 'Mathieu',
    ];
    private const LAST_NAMES = [
        'Martin', 'Lemaire', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau',
        'Simon', 'Laurent', 'Lefèvre', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier',
        'Morel', 'Girard', 'André', 'Mercier', 'Dupont', 'Lambert', 'Bonnet', 'François', 'Martinez', 'Legrand',
        'Garnier', 'Faure', 'Rousseau', 'Blanc', 'Guérin', 'Muller', 'Henry', 'Roussel', 'Perrin', 'Marchand',
    ];
    private const NOTES = [
        2 => 'Table 4, vient avec sa sœur.',
        7 => 'A payé par chèque.',
        11 => 'Malentendant : lui confirmer les numéros de près.',
        19 => 'Doit partir avant 23 h.',
        28 => 'Bénévole de l\'association.',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CardRepository $cardRepository,
        private readonly CardGridGenerator $gridGenerator,
        private readonly DrawService $drawService,
        private readonly WinnerDetectionService $winnerDetectionService,
        private readonly WinnerService $winnerService,
    ) {
    }

    /**
     * Charge le jeu de données dans une base vide (la purge est à la charge de l'appelant).
     *
     * @return array{
     *     event: Event,
     *     pastEvent: Event,
     *     players: int,
     *     cards: int,
     *     cardsInPlay: int,
     *     runningGame: Game,
     *     nextWinningNumber: int,
     *     nextWinningCards: Card[],
     * }
     */
    public function load(): array
    {
        $random = new Randomizer(new Mt19937(self::SEED));

        $pastEvent = $this->createPastEvent();

        $event = new Event(self::EVENT_NAME, new \DateTimeImmutable('today 20:00'));
        $this->em->persist($event);

        $games = [];
        foreach (self::GAMES as $i => [$rule, $prize]) {
            $game = (new Game())
                ->setPosition($i + 1)
                ->setRule($rule)
                ->setPrize($prize)
                ->setHallOnly(self::HALL_ONLY_POSITION === $i + 1);
            $event->addGame($game);
            $games[] = $game;
        }

        $players = $this->createPlayers($event);
        $this->createCards($players, $random);
        $this->em->flush();

        $cards = $this->cardRepository->findByEvent($event);

        // Première série : quine, double quine et carton plein sur les mêmes numéros.
        $numbers = $random->shuffleArray(range(1, 90));
        $games[0]->setStatus(GameStatus::RUNNING);
        for ($i = 0; $i < 3; ++$i) {
            $this->playUntilWin($games[$i], $cards, $numbers);
            if (1 === $i) {
                // Égalité en salle avec un carton qui n'a pas été enregistré à l'entrée.
                $this->winnerService->validateOfflineWinner($games[$i], 'Carton 0457 (non enregistré)');
            }
            $this->moveToNextGame($games[$i], $games[$i + 1]);
        }

        // Deuxième série : démarque, puis une quine arrêtée juste avant le numéro gagnant.
        $running = $games[3];
        $this->drawService->clearAll($running);
        [$winningNumber, $winningCards] = $this->playUntilOneNumberShort($running, $cards, $random->shuffleArray(range(1, 90)));

        return [
            'event' => $event,
            'pastEvent' => $pastEvent,
            'players' => count($players) + $pastEvent->getPlayers()->count(),
            'cards' => self::CARD_COUNT,
            'cardsInPlay' => count($cards),
            'runningGame' => $running,
            'nextWinningNumber' => $winningNumber,
            'nextWinningCards' => $winningCards,
        ];
    }

    /**
     * Un événement terminé, animé sans cartons enregistrés : toutes les parties ont un gagnant saisi à la main.
     */
    private function createPastEvent(): Event
    {
        $event = new Event(self::PAST_EVENT_NAME, new \DateTimeImmutable('first day of -6 months 14:30'));
        $this->em->persist($event);

        $games = [];
        foreach (self::PAST_GAMES as $i => [$rule, $prize]) {
            $game = (new Game())
                ->setPosition($i + 1)
                ->setRule($rule)
                ->setPrize($prize)
                ->setHallOnly(true)
                ->setStatus(GameStatus::FINISHED);
            $event->addGame($game);
            $games[] = $game;
        }

        foreach (['Paulette Chevalier', 'Marcel Lemoine', 'Simone Barbier', 'Lucien Renard', 'Huguette Colin'] as $i => $name) {
            $event->addPlayer($player = $this->createPlayer($name, 100 + $i));
            $this->em->persist($player);
        }
        $this->em->flush();

        foreach ($games as $i => $game) {
            $this->winnerService->validateOfflineWinner($game, self::PAST_GAMES[$i][2]);
        }

        return $event;
    }

    /**
     * @return Player[]
     */
    private function createPlayers(Event $event): array
    {
        $players = [];
        foreach (self::FIRST_NAMES as $i => $firstName) {
            $player = $this->createPlayer($firstName.' '.self::LAST_NAMES[$i], $i)
                ->setNotes(self::NOTES[$i] ?? null);
            $event->addPlayer($player);
            $this->em->persist($player);
            $players[] = $player;
        }

        return $players;
    }

    /**
     * Les coordonnées sont facultatives à l'inscription : un joueur sur quatre n'a pas d'e-mail,
     * un sur cinq pas de téléphone.
     */
    private function createPlayer(string $name, int $index): Player
    {
        $slug = (new AsciiSlugger('fr'))->slug($name, '.')->lower();

        return (new Player())
            ->setName($name)
            ->setEmail(3 === $index % 4 ? null : $slug.'@example.test')
            ->setPhone(4 === $index % 5 ? null : sprintf('06 39 98 %02d %02d', intdiv($index, 100), $index % 100));
    }

    /**
     * Les cartons sont vendus dans l'ordre des références ; ceux qui restent sont libres.
     *
     * @param Player[] $players
     */
    private function createCards(array $players, Randomizer $random): void
    {
        $owners = [];
        foreach (array_slice($players, 0, -self::PLAYERS_WITHOUT_CARD) as $i => $player) {
            array_push($owners, ...array_fill(0, self::CARDS_PER_PLAYER[$i % count(self::CARDS_PER_PLAYER)], $player));
        }

        for ($i = 0; $i < self::CARD_COUNT; ++$i) {
            $card = (new Card())
                ->setReference(sprintf('C-%04d', $i + 1))
                ->setGrid($this->gridGenerator->generate($random))
                ->setPlayer($owners[$i] ?? null);
            $this->em->persist($card);
        }
    }

    /**
     * Tire les numéros un à un jusqu'au gel de la partie, puis valide tous les cartons gagnants (égalités comprises).
     *
     * @param Card[] $cards
     * @param int[]  $numbers numéros restant à tirer ; ceux qui sortent en sont retirés
     */
    private function playUntilWin(Game $game, array $cards, array &$numbers): void
    {
        while (!$game->isFrozen()) {
            if ([] === $numbers) {
                throw new \LogicException(sprintf('Aucun gagnant pour la partie %d : jeu de données incohérent.', $game->getPosition()));
            }
            $this->drawService->toggleNumber($game, array_shift($numbers), $cards);
        }

        foreach ($this->winnerDetectionService->findPotentialWinners($game, $cards) as $potential) {
            $this->winnerService->validateSystemWinner($game, $potential['card']);
        }
    }

    /**
     * Même effet que le bouton « partie suivante » : la partie est terminée, la suivante démarre avec ses numéros.
     */
    private function moveToNextGame(Game $game, Game $next): void
    {
        $game->setStatus(GameStatus::FINISHED);
        $next->setStatus(GameStatus::RUNNING);
        foreach ($game->getDraws() as $draw) {
            $next->addDraw($draw);
        }
        $this->em->flush();
    }

    /**
     * Tire les numéros tant que le suivant ne fait gagner personne, et s'arrête juste avant.
     *
     * @param Card[] $cards
     * @param int[]  $numbers
     *
     * @return array{0: int, 1: Card[]} le numéro qui fera gagner et les cartons qu'il complète
     */
    private function playUntilOneNumberShort(Game $game, array $cards, array $numbers): array
    {
        $drawn = [];
        foreach ($numbers as $number) {
            $winners = $this->cardsCompletedBy($game, $cards, [...$drawn, $number]);
            if ([] !== $winners) {
                return [$number, $winners];
            }
            $this->drawService->toggleNumber($game, $number, $cards);
            $drawn[] = $number;
        }

        throw new \LogicException('Aucun numéro gagnant pour la partie en cours : jeu de données incohérent.');
    }

    /**
     * @param Card[] $cards
     * @param int[]  $drawn
     *
     * @return Card[] cartons non bloqués qui remplissent la règle de la partie avec ces numéros
     */
    private function cardsCompletedBy(Game $game, array $cards, array $drawn): array
    {
        $winners = [];
        foreach ($cards as $card) {
            if ($card->isBlocked()) {
                continue;
            }
            $completeLines = 0;
            foreach ($card->getGrid() as $line) {
                if ([] === array_diff($line, $drawn)) {
                    ++$completeLines;
                }
            }
            if ($completeLines >= $game->getRule()->getRequiredLines()) {
                $winners[] = $card;
            }
        }

        return $winners;
    }
}
