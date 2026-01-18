<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Event;
use App\Entity\Game;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;

abstract class BaseContext implements Context
{
    protected EntityManagerInterface $entityManager;
    protected EventRepository $eventRepo;
    protected GameRepository $gameRepo;
    protected CardRepository $cardRepo;
    protected PlayerRepository $playerRepo;

    // Propriétés statiques partagées entre tous les contextes pour gérer les erreurs
    protected static ?\Throwable $lastException = null;
    protected static ?string $lastError = null;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $eventRepo,
        GameRepository $gameRepo,
        CardRepository $cardRepo,
        PlayerRepository $playerRepo,
    ) {
        $this->entityManager = $entityManager;
        $this->eventRepo = $eventRepo;
        $this->gameRepo = $gameRepo;
        $this->cardRepo = $cardRepo;
        $this->playerRepo = $playerRepo;
    }

    /**
     * @BeforeScenario
     */
    public function cleanDatabase(): void
    {
        // Réinitialiser les erreurs partagées
        self::$lastException = null;
        self::$lastError = null;

        // Nettoyer la base de données avant chaque scénario
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Désactiver temporairement les contraintes de clés étrangères
        $connection->executeStatement('SET CONSTRAINTS ALL DEFERRED');

        // Liste des tables à nettoyer (dans l'ordre inverse des dépendances)
        $tables = ['winner', 'draw', 'game', 'card', 'player', 'event'];

        foreach ($tables as $table) {
            $connection->executeStatement($platform->getTruncateTableSQL($table, true));
        }

        // Réactiver les contraintes
        $connection->executeStatement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    /**
     * Trouve une partie (Game) par sa position dans un événement.
     */
    protected function findGameByPosition(int $position): ?Game
    {
        $events = $this->eventRepo->findAll();
        if (empty($events)) {
            return null;
        }

        $event = $events[0];
        foreach ($event->getGames() as $game) {
            if ($game->getPosition() === $position) {
                return $game;
            }
        }

        return null;
    }

    /**
     * Récupère le premier événement disponible.
     */
    protected function getCurrentEvent(): ?Event
    {
        $events = $this->eventRepo->findAll();

        return !empty($events) ? $events[0] : null;
    }
}
