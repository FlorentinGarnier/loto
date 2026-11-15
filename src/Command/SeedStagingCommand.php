<?php

namespace App\Command;

use App\Service\StagingDatasetLoader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:seed:staging', description: 'Remplace toutes les données par le jeu de données de recette (soirée de loto en cours)')]
final class SeedStagingCommand extends Command
{
    private const TABLES = ['winner', 'draw', 'game', 'card', 'player', 'event'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StagingDatasetLoader $loader,
        #[Autowire(env: 'bool:ALLOW_DATA_RESET')]
        private readonly bool $allowDataReset,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Staging et production tournent tous deux avec APP_ENV=prod : seule cette variable les distingue.
        if (!$this->allowDataReset) {
            $io->error('Commande refusée : elle efface toutes les données. Elle n\'est autorisée que si ALLOW_DATA_RESET=1 (staging et développement, jamais en production).');

            return Command::FAILURE;
        }

        $connection = $this->em->getConnection();
        $existing = [];
        foreach (self::TABLES as $table) {
            $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table);
            if ($count > 0) {
                $existing[] = $count.' '.$table;
            }
        }

        if ([] !== $existing && $input->isInteractive()
            && !$io->confirm('Toutes les données seront effacées ('.implode(', ', $existing).'). Continuer ?', false)) {
            $io->warning('Abandon : rien n\'a été modifié.');

            return Command::FAILURE;
        }

        // Une seule transaction : en cas d'échec, les données d'avant restent en place.
        $summary = $this->em->wrapInTransaction(function () use ($connection): array {
            $connection->executeStatement('TRUNCATE TABLE '.implode(', ', self::TABLES).' RESTART IDENTITY CASCADE');
            $this->em->clear();

            return $this->loader->load();
        });

        $running = $summary['runningGame'];
        $io->success('Jeu de données de recette chargé.');
        $io->definitionList(
            ['Événement en cours' => $summary['event']->getName()],
            ['Événement passé' => $summary['pastEvent']->getName()],
            ['Joueurs' => (string) $summary['players']],
            ['Cartons' => sprintf('%d, dont %d en jeu', $summary['cards'], $summary['cardsInPlay'])],
            ['Partie en cours' => sprintf('n° %d, %d numéros tirés', $running->getPosition(), $running->getDraws()->count())],
            ['Prochain numéro gagnant' => sprintf('%d (%s)', $summary['nextWinningNumber'], implode(', ', array_map(
                static fn ($card) => $card->getReference().' — '.$card->getPlayer()?->getName(),
                $summary['nextWinningCards'],
            )))],
        );

        return Command::SUCCESS;
    }
}
