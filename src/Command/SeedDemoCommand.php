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

#[AsCommand(name: 'app:seed:demo', description: 'Seed demo data: 1 event, 3 games, players and sample cards')]
class SeedDemoCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $event = (new Event('Loto Démo', new \DateTimeImmutable()));
        $this->em->persist($event);

        $rules = [RuleType::LINE, RuleType::DOUBLE_LINE, RuleType::FULL_CARD];
        foreach ($rules as $i => $rule) {
            $game = (new Game())
                ->setEvent($event)
                ->setPosition($i + 1)
                ->setRule($rule)
                ->setPrize(match ($rule) {
                    RuleType::LINE => 'Un lot gourmand',
                    RuleType::DOUBLE_LINE => 'Panier garni',
                    RuleType::FULL_CARD => 'Téléviseur',
                })
                ->setStatus(0 === $i ? GameStatus::RUNNING : GameStatus::PENDING);
            $this->em->persist($game);
        }

        // Players
        $players = [];
        foreach ([['Alice', 'alice@example.test'], ['Bob', 'bob@example.test'], ['Chloé', 'chloe@example.test']] as [$name,$email]) {
            $p = (new Player())->setName($name)->setEmail($email);
            $this->em->persist($p);
            $players[] = $p;
        }

        // Cards (5 examples) with simple 3x5 grids
        for ($i = 1; $i <= 5; ++$i) {
            $card = (new Card())
                ->setEvent($event)
                ->setReference(sprintf('C%03d', $i))
                ->setGrid($this->generateGrid());
            if ($i <= count($players)) {
                $card->setPlayer($players[$i - 1]);
            }
            $this->em->persist($card);
        }

        $this->em->flush();

        $io->success('Demo data created. Event: '.$event->getName());

        return Command::SUCCESS;
    }

    /**
     * Generate a simple bingo-like 3x5 grid of unique numbers between 1 and 90.
     * This is not a strict bingo card generator but sufficient for demo/testing.
     *
     * @return int[][]
     */
    private function generateGrid(): array
    {
        $nums = range(1, 90);
        shuffle($nums);
        $take = array_slice($nums, 0, 15);
        $grid = [[], [], []];
        for ($i = 0; $i < 15; ++$i) {
            $grid[intdiv($i, 5)][] = $take[$i];
        }

        return $grid;
    }
}
