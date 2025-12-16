<?php

namespace App\Command;

use App\Entity\Card;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cards:import', description: 'Importe des cartons depuis un fichier CSV (cardRef,line1,line2,line3) dans un évènement donné')]
class ImportCardsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('event-id', InputArgument::OPTIONAL, "ID de l'évènement cible")
            ->addArgument('path', InputArgument::OPTIONAL, 'Chemin du fichier CSV', 'var/cards.csv')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, "N'importe rien, affiche seulement ce qui serait fait");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eventId = (int) $input->getArgument('event-id');
        $path = (string) $input->getArgument('path');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($eventId) {
            /** @var Event|null $event */
            $event = $this->em->getRepository(Event::class)->find($eventId);
            if (!$event) {
                $io->error("Évènement #$eventId introuvable");

                return Command::FAILURE;
            }
        }

        if (!is_file($path) || !is_readable($path)) {
            $io->error("Fichier CSV introuvable ou illisible: $path");

            return Command::FAILURE;
        }

        $io->title(sprintf('Import des cartons pour l\'évènement depuis %s', $path));

        $handle = fopen($path, 'r');
        if (false === $handle) {
            $io->error('Impossible d\'ouvrir le fichier CSV');

            return Command::FAILURE;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $io->error('Fichier CSV vide');

            return Command::FAILURE;
        }

        // Normaliser les entêtes
        $header = array_map(static fn ($h) => is_string($h) ? trim($h) : $h, $header);
        $expected = ['cardRef', 'line1', 'line2', 'line3'];
        $lower = array_map('strtolower', $header);
        if (array_map('strtolower', $expected) !== $lower) {
            $io->warning('En-têtes CSV inattendues: '.implode(',', $header).' (attendu: '.implode(',', $expected).')');
        }

        $repo = $this->em->getRepository(Card::class);

        $count = 0;
        $skipped = 0;
        $updated = 0;
        $errors = 0;
        $lineNo = 1; // header line = 1
        while (($row = fgetcsv($handle)) !== false) {
            ++$lineNo;
            if (count($row) < 4) {
                $io->warning("Ligne $lineNo ignorée: colonnes insuffisantes");
                ++$errors;
                continue;
            }

            [$ref, $l1, $l2, $l3] = $row;
            $ref = trim((string) $ref);
            if ('' === $ref) {
                $io->warning("Ligne $lineNo: référence vide, ignorée");
                ++$errors;
                continue;
            }

            try {
                $grid = $this->parseLines([$l1, $l2, $l3]);
            } catch (\InvalidArgumentException $e) {
                $io->warning("Ligne $lineNo (ref=$ref): ".$e->getMessage());
                ++$errors;
                continue;
            }

            /** @var Card|null $existing */
            $existing = $repo->findOneBy(['reference' => $ref]);
            if ($existing) {
                // Par défaut, on ignore les doublons
                ++$skipped;
                if ($io->isVerbose()) {
                    $io->text("ref=$ref déjà présente, ignorée");
                }
                continue;
            }

            if ($dryRun) {
                ++$count;
                if ($io->isVeryVerbose()) {
                    $io->text("(dry-run) Ajouter ref=$ref");
                }
                continue;
            }

            $card = (new Card())
                ->setReference($ref)
                ->setGrid($grid);

            $this->em->persist($card);
            ++$count;

            if (($count + $skipped + $errors) % 50 === 0) {
                $this->em->flush();
                $this->em->clear(Card::class);
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf('Terminé. Ajoutés: %d, ignorés (doublons): %d, erreurs: %d%s', $count, $skipped, $errors, $dryRun ? ' (dry-run)' : ''));

        return $errors > 0 ? Command::INVALID : Command::SUCCESS;
    }

    /**
     * @param array{0:string,1:string,2:string} $lines
     *
     * @return int[][]
     */
    private function parseLines(array $lines): array
    {
        $grid = [];
        foreach ($lines as $idx => $line) {
            $line = trim((string) $line, " \t\n\r\0\x0B\"'");
            if ('' === $line) {
                throw new \InvalidArgumentException(sprintf('ligne %d vide', $idx + 1));
            }
            // Les valeurs sont séparées par un espace
            $parts = preg_split('/\s+/', $line) ?: [];
            if (5 !== count($parts)) {
                throw new \InvalidArgumentException(sprintf('ligne %d invalide: 5 nombres attendus, trouvé %d', $idx + 1, count($parts)));
            }
            $nums = [];
            foreach ($parts as $p) {
                if (!preg_match('/^\d{1,2}$/', $p) && !preg_match('/^90$/', $p)) {
                    // autorise 1..90
                    if (!is_numeric($p)) {
                        throw new \InvalidArgumentException(sprintf('valeur non numérique "%s"', $p));
                    }
                }
                $n = (int) $p;
                if ($n < 1 || $n > 90) {
                    throw new \InvalidArgumentException(sprintf('nombre hors plage 1..90: %d', $n));
                }
                $nums[] = $n;
            }
            // Optionnel: vérifier unicité sur la ligne
            if (5 !== count(array_unique($nums))) {
                throw new \InvalidArgumentException(sprintf('doublons sur la ligne %d', $idx + 1));
            }
            $grid[] = $nums;
        }
        if (3 !== count($grid)) {
            throw new \InvalidArgumentException('La grille doit contenir 3 lignes');
        }

        return $grid;
    }
}
