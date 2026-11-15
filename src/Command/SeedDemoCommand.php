<?php

namespace App\Command;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:demo', description: 'Crée des données de démonstration : 1 événement, 3 parties, 3 joueurs et 5 cartons')]
class SeedDemoCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Les références de cartons sont uniques : un second lancement échouerait en base
        if (null !== $this->em->getRepository(Card::class)->findOneBy(['reference' => 'C001'])) {
            $io->error('Les données de démonstration existent déjà (carton C001). Videz la base avant de relancer.');

            return Command::FAILURE;
        }

        $event = (new Event('Loto Démo', new \DateTimeImmutable()));
        $this->em->persist($event);

        $rules = [RuleType::QUINE, RuleType::DOUBLE_QUINE, RuleType::FULL_CARD];
        foreach ($rules as $i => $rule) {
            $game = (new Game())
                ->setEvent($event)
                ->setPosition($i + 1)
                ->setRule($rule)
                ->setPrize(match ($rule) {
                    RuleType::QUINE => 'Un lot gourmand',
                    RuleType::DOUBLE_QUINE => 'Panier garni',
                    RuleType::FULL_CARD => 'Téléviseur',
                })
                ->setStatus(0 === $i ? GameStatus::RUNNING : GameStatus::PENDING);
            $this->em->persist($game);
        }

        // Joueurs, rattachés à l'événement : un carton n'est en jeu que par son joueur
        $players = [];
        foreach ([['Alice', 'alice@example.test'], ['Bob', 'bob@example.test'], ['Chloé', 'chloe@example.test']] as [$name, $email]) {
            $p = (new Player())->setName($name)->setEmail($email)->setEvent($event);
            $this->em->persist($p);
            $players[] = $p;
        }

        // 5 cartons : les 3 premiers attribués, les 2 derniers libres
        for ($i = 1; $i <= 5; ++$i) {
            $card = (new Card())
                ->setReference(sprintf('C%03d', $i))
                ->setGrid($this->generateGrid());
            if ($i <= count($players)) {
                $card->setPlayer($players[$i - 1]);
            }
            $this->em->persist($card);
        }

        $this->em->flush();

        $io->success('Données de démonstration créées. Événement : '.$event->getName());

        return Command::SUCCESS;
    }

    /**
     * Génère une grille de loto traditionnelle : 3 lignes de 5 numéros entre 1 et 90, sans doublon,
     * chaque numéro d'une ligne dans une colonne différente (1-9, 10-19, …, 80-90), triés par colonne.
     *
     * @return int[][]
     */
    private function generateGrid(): array
    {
        $used = [];
        $grid = [];
        for ($line = 0; $line < 3; ++$line) {
            $columns = range(0, 8);
            shuffle($columns);
            $columns = array_slice($columns, 0, 5);
            sort($columns);

            $numbers = [];
            foreach ($columns as $column) {
                $min = 0 === $column ? 1 : $column * 10;
                $max = 8 === $column ? 90 : $column * 10 + 9;
                do {
                    $number = random_int($min, $max);
                } while (isset($used[$number]));
                $used[$number] = true;
                $numbers[] = $number;
            }
            $grid[] = $numbers;
        }

        return $grid;
    }
}
