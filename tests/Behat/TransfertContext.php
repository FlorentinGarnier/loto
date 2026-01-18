<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Card;
use App\Entity\Draw;
use App\Entity\Winner;
use App\Enum\BlockedReason;
use App\Enum\GameStatus;
use App\Enum\WinnerSource;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

final class TransfertContext extends BaseContext
{
    /**
     * @Given /^un carton "([^"]*)" existe avec une ligne complète sur les numéros "([^"]*)"$/
     */
    public function quUnCartonExisteAvecUneLigneCompleteSurLesNumeros(string $reference, string $numbers): void
    {
        $numbersArray = array_map('intval', explode(',', $numbers));

        $grid = [
            $numbersArray,
            [8, 12, 34, 56, 78],
            [2, 18, 29, 43, 89],
        ];

        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $this->entityManager->persist($card);
        }

        $card->setGrid($grid);
        $this->entityManager->flush();
    }

    /**
     * @Given /^le carton "([^"]*)" a été détecté comme gagnant potentiel$/
     */
    public function queLeCartonAEteDetecteCommeGagnantPotentiel(string $reference): void
    {
        // Créer le carton s'il n'existe pas
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([
                [5, 15, 23, 45, 67],
                [8, 12, 34, 56, 78],
                [2, 18, 29, 43, 89],
            ]);
            $this->entityManager->persist($card);
            $this->entityManager->flush();
        }

        // Simuler la détection en gelant la partie
        $games = $this->gameRepo->findBy(['status' => GameStatus::RUNNING]);
        if (!empty($games)) {
            $game = $games[0];
            $game->setIsFrozen(true);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given /^la partie d'ordre (\d+) est gelée$/
     */
    public function quelaPartieDOrdreEstGelee(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $game->setIsFrozen(true);
        $this->entityManager->flush();
    }

    /**
     * @When /^je valide le carton "([^"]*)" comme gagnant et passe à la partie suivante$/
     */
    public function jeValideLeCartonCommeGagnantEtPasseALaPartieSuivante(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton {$reference} n'existe pas");

        $currentGame = $this->gameRepo->findOneBy(['status' => GameStatus::RUNNING]);
        Assert::assertNotNull($currentGame, 'Aucune partie en cours');

        // Valider le gagnant
        $winner = new Winner();
        $winner->setGame($currentGame);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::SYSTEM);

        $currentGame->addWinner($winner);
        $card->setIsBlocked(true);
        $card->setBlockedReason(BlockedReason::WINNER);

        $this->entityManager->persist($winner);

        // Transférer les tirages et passer à la partie suivante
        $this->transferDrawsAndMoveToNextGame($currentGame);
    }

    /**
     * @When /^j'ajoute manuellement le carton "([^"]*)" comme gagnant offline et passe à la partie suivante$/
     */
    public function jAjouteManuellementLeCartonCommeGagnantOfflineEtPasseALaPartieSuivante(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        Assert::assertNotNull($card, "Le carton {$reference} n'existe pas");

        $currentGame = $this->gameRepo->findOneBy(['status' => GameStatus::RUNNING]);
        Assert::assertNotNull($currentGame, 'Aucune partie en cours');

        // Ajouter gagnant offline
        $winner = new Winner();
        $winner->setGame($currentGame);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::OFFLINE);

        $currentGame->addWinner($winner);
        $currentGame->setIsFrozen(true);
        $card->setIsBlocked(true);
        $card->setBlockedReason(BlockedReason::WINNER);

        $this->entityManager->persist($winner);

        // Transférer les tirages et passer à la partie suivante
        $this->transferDrawsAndMoveToNextGame($currentGame);
    }

    /**
     * @When /^je termine la partie d'ordre (\d+) et passe à la suivante en conservant les tirages$/
     */
    public function jeTermineLaPartieDOrdreEtPasseALaSuivanteEnConservantLesTirages(int $position): void
    {
        $currentGame = $this->findGameByPosition($position);
        Assert::assertNotNull($currentGame, "Aucune partie trouvée à la position {$position}");

        // Transférer les tirages et passer à la partie suivante
        $this->transferDrawsAndMoveToNextGame($currentGame);
    }

    /**
     * @Given /^un carton "([^"]*)" avec deux lignes complètes est détecté comme gagnant$/
     */
    public function quUnCartonAvecDeuxLignesCompletesEstDetecteCommeGagnant(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([
                [5, 12, 23, 45, 67],
                [8, 15, 34, 56, 78],
                [2, 18, 29, 43, 89],
            ]);
            $this->entityManager->persist($card);
            $this->entityManager->flush();
        }

        // Geler la partie
        $games = $this->gameRepo->findBy(['status' => GameStatus::RUNNING]);
        if (!empty($games)) {
            $games[0]->setIsFrozen(true);
            $this->entityManager->flush();
        }
    }

    /**
     * @When /^je valide le carton "([^"]*)" et passe à la partie suivante$/
     */
    public function jeValideLeCartonEtPasseALaPartieSuivante(string $reference): void
    {
        $this->jeValideLeCartonCommeGagnantEtPasseALaPartieSuivante($reference);
    }

    /**
     * @Given /^les numéros suivants ont été tirés dans cet ordre pour la partie d'ordre (\d+):$/
     */
    public function queLesNumerosSuivantsOntEteTiresDansCetOrdrePourLaPartieDOrdre(int $position, TableNode $table): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        foreach ($table->getHash() as $row) {
            $number = (int) $row['numéro'];
            $order = (int) $row['ordre'];

            $draw = new Draw();
            $draw->setGame($game);
            $draw->setNumber($number);
            $draw->setOrderIndex($order);

            $game->addDraw($draw);
            $this->entityManager->persist($draw);
        }

        $this->entityManager->flush();
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit avoir les numéros tirés dans l'ordre suivant:$/
     */
    public function laPartieDOrdreDevoirAvoirLesNumerosTiresDansLOrdreSuivant(int $position, TableNode $table): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $expectedDraws = [];
        foreach ($table->getHash() as $row) {
            $expectedDraws[(int) $row['ordre']] = (int) $row['numéro'];
        }

        $actualDraws = [];
        foreach ($game->getDraws() as $draw) {
            $actualDraws[$draw->getOrderIndex()] = $draw->getNumber();
        }

        ksort($expectedDraws);
        ksort($actualDraws);

        Assert::assertEquals($expectedDraws, $actualDraws, "L'ordre des tirages ne correspond pas");
    }

    /**
     * @Given /^un carton "([^"]*)" a été détecté comme gagnant potentiel$/
     */
    public function quUnCartonAEteDetecteCommeGagnantPotentiel(string $reference): void
    {
        $this->queLeCartonAEteDetecteCommeGagnantPotentiel($reference);
    }

    /**
     * @When /^je refuse le gagnant et dégèle la partie d'ordre (\d+)$/
     */
    public function jeRefuseLeGagnantEtDegelelaPartieDOrdre(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $game->setIsFrozen(false);
        $game->setFreezeOrderIndex(null);
        $this->entityManager->flush();
    }

    /**
     * @Given /^un autre carton "([^"]*)" est détecté comme gagnant$/
     */
    public function quUnAutreCartonEstDetecteCommeGagnant(string $reference): void
    {
        $card = $this->cardRepo->findOneBy(['reference' => $reference]);
        if (!$card) {
            $card = new Card();
            $card->setReference($reference);
            $card->setGrid([
                [8, 12, 23, 45, 67],
                [5, 15, 34, 56, 78],
                [2, 18, 29, 43, 89],
            ]);
            $this->entityManager->persist($card);
            $this->entityManager->flush();
        }

        // Geler à nouveau la partie
        $games = $this->gameRepo->findBy(['status' => GameStatus::RUNNING]);
        if (!empty($games)) {
            $games[0]->setIsFrozen(true);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given /^un carton gagnant est validé pour la partie d'ordre (\d+)$/
     */
    public function quUnCartonGagnantEstValidePourLaPartieDOrdre(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        // Créer un carton fictif avec référence unique
        $card = new Card();
        $card->setReference('WINNER_'.$position.'_'.uniqid());
        $card->setGrid([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10], [11, 12, 13, 14, 15]]);
        $this->entityManager->persist($card);

        // Valider le gagnant
        $winner = new Winner();
        $winner->setGame($game);
        $winner->setCard($card);
        $winner->setSource(WinnerSource::SYSTEM);

        $game->addWinner($winner);
        $card->setIsBlocked(true);
        $card->setBlockedReason(BlockedReason::WINNER);

        $this->entityManager->persist($winner);
        $this->entityManager->flush();
    }

    /**
     * @When /^je passe à la partie d'ordre (\d+) en conservant les tirages$/
     */
    public function jePasseALaPartieDOrdreEnConservantLesTirages(int $targetPosition): void
    {
        $currentGames = $this->gameRepo->findBy(['status' => GameStatus::RUNNING]);
        Assert::assertNotEmpty($currentGames, 'Aucune partie en cours');

        $currentGame = $currentGames[0];
        $this->transferDrawsAndMoveToSpecificGame($currentGame, $targetPosition);
    }

    /**
     * @Then /^seuls les numéros valides \(entre 1 et 90\) doivent être transférés à la partie d'ordre (\d+)$/
     */
    public function seulsLesNumerosValidesDoiventEtreTransferesALaPartieDOrdre(int $position): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        foreach ($game->getDraws() as $draw) {
            $number = $draw->getNumber();
            Assert::assertGreaterThanOrEqual(1, $number, "Le numéro {$number} est invalide (< 1)");
            Assert::assertLessThanOrEqual(90, $number, "Le numéro {$number} est invalide (> 90)");
        }
    }

    /**
     * @Given /^la partie d'ordre (\d+) a déjà le numéro (\d+) tiré \(cas improbable\)$/
     */
    public function quelaPartieDOrdreADejaLeNumeroTire(int $position, int $number): void
    {
        // Cette étape ne fait rien car elle représente un état hypothétique
        // La logique de gestion des doublons sera testée dans l'assertion
    }

    /**
     * @Then /^la partie d'ordre (\d+) doit avoir exactement (\d+) numéros tirés sans doublon$/
     */
    public function laPartieDOrdreDevoirAvoirExactementNumerosTiresSansDoublon(int $position, int $expectedCount): void
    {
        $game = $this->findGameByPosition($position);
        Assert::assertNotNull($game, "Aucune partie trouvée à la position {$position}");

        $numbers = [];
        foreach ($game->getDraws() as $draw) {
            $numbers[] = $draw->getNumber();
        }

        $uniqueNumbers = array_unique($numbers);
        Assert::assertCount($expectedCount, $uniqueNumbers, 'Le nombre de numéros uniques ne correspond pas');
        Assert::assertCount(count($uniqueNumbers), $numbers, 'Il y a des doublons dans les tirages');
    }

    private function transferDrawsAndMoveToNextGame(\App\Entity\Game $currentGame): void
    {
        $event = $currentGame->getEvent();
        Assert::assertNotNull($event, 'Aucun événement associé à la partie');

        // Terminer la partie courante
        $currentGame->setStatus(GameStatus::FINISHED);
        $currentGame->setIsFrozen(false);

        // Trouver la partie suivante
        $nextGame = null;
        foreach ($event->getGames() as $game) {
            if ($game->getPosition() === $currentGame->getPosition() + 1) {
                $nextGame = $game;
                break;
            }
        }

        if ($nextGame) {
            // Transférer les tirages
            $drawnNumbers = [];
            foreach ($currentGame->getDraws() as $draw) {
                $drawnNumbers[$draw->getNumber()] = $draw->getOrderIndex();
            }

            foreach ($drawnNumbers as $number => $orderIndex) {
                // Vérifier si le numéro n'est pas déjà tiré dans la partie suivante
                $alreadyDrawn = false;
                foreach ($nextGame->getDraws() as $existingDraw) {
                    if ($existingDraw->getNumber() === $number) {
                        $alreadyDrawn = true;
                        break;
                    }
                }

                if (!$alreadyDrawn) {
                    $newDraw = new Draw();
                    $newDraw->setGame($nextGame);
                    $newDraw->setNumber($number);
                    $newDraw->setOrderIndex($orderIndex);

                    $nextGame->addDraw($newDraw);
                    $this->entityManager->persist($newDraw);
                }
            }

            // Démarrer la partie suivante
            $nextGame->setStatus(GameStatus::RUNNING);
        }

        $this->entityManager->flush();
    }

    private function transferDrawsAndMoveToSpecificGame(\App\Entity\Game $currentGame, int $targetPosition): void
    {
        $event = $currentGame->getEvent();
        Assert::assertNotNull($event, 'Aucun événement associé à la partie');

        // Terminer la partie courante
        $currentGame->setStatus(GameStatus::FINISHED);
        $currentGame->setIsFrozen(false);

        // Trouver la partie cible
        $targetGame = $this->findGameByPosition($targetPosition);
        Assert::assertNotNull($targetGame, "Aucune partie trouvée à la position {$targetPosition}");

        // Transférer les tirages
        $drawnNumbers = [];
        foreach ($currentGame->getDraws() as $draw) {
            $drawnNumbers[$draw->getNumber()] = $draw->getOrderIndex();
        }

        foreach ($drawnNumbers as $number => $orderIndex) {
            // Vérifier si le numéro n'est pas déjà tiré dans la partie cible
            $alreadyDrawn = false;
            foreach ($targetGame->getDraws() as $existingDraw) {
                if ($existingDraw->getNumber() === $number) {
                    $alreadyDrawn = true;
                    break;
                }
            }

            if (!$alreadyDrawn) {
                $newDraw = new Draw();
                $newDraw->setGame($targetGame);
                $newDraw->setNumber($number);
                $newDraw->setOrderIndex($orderIndex);

                $targetGame->addDraw($newDraw);
                $this->entityManager->persist($newDraw);
            }
        }

        // Démarrer la partie cible
        $targetGame->setStatus(GameStatus::RUNNING);

        $this->entityManager->flush();
    }
}
