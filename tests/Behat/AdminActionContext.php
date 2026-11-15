<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Player;
use PHPUnit\Framework\Assert;

/**
 * Actions d'administration jouées par HTTP, comme le fait le navigateur :
 * vérifie que le jeton CSRF est exigé et que les vues l'envoient.
 */
final class AdminActionContext extends BaseContext
{
    /**
     * @When /^j'envoie "(POST|DELETE) ([^"]+)" sans jeton CSRF$/
     */
    public function jEnvoieSansJetonCsrf(string $method, string $path): void
    {
        // Corps JSON valide pour les routes qui en lisent un : seul le jeton manque
        $this->client->request(
            $method,
            $this->resolvePath($path),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['order' => [], 'ref' => 'CSRF-001', 'createIfMissing' => true]),
        );
    }

    /**
     * @When /^j'envoie "(POST|DELETE) ([^"]+)" avec un jeton CSRF invalide$/
     */
    public function jEnvoieAvecUnJetonCsrfInvalide(string $method, string $path): void
    {
        $this->client->request($method, $this->resolvePath($path), ['_token' => 'jeton-invalide']);
    }

    /**
     * @When /^j'envoie "(POST|DELETE) ([^"]+)" avec le jeton de l'administration$/
     */
    public function jEnvoieAvecLeJetonDeLAdministration(string $method, string $path): void
    {
        $this->client->request($method, $this->resolvePath($path), ['_token' => $this->adminToken()]);
    }

    /**
     * @When /^j'envoie en JSON "(POST|DELETE) ([^"]+)" avec le jeton de l'administration$/
     */
    public function jEnvoieEnJsonAvecLeJetonDeLAdministration(string $method, string $path): void
    {
        $this->client->request(
            $method,
            $this->resolvePath($path),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['_token' => $this->adminToken(), 'order' => [], 'ref' => 'CSRF-001', 'createIfMissing' => true]),
        );
    }

    /**
     * @When /^je clique sur "([^"]*)" depuis le tableau de bord$/
     */
    public function jeCliqueSurDepuisLeTableauDeBord(string $button): void
    {
        $this->clickOn('/admin', $button);
    }

    /**
     * @When /^je clique sur "([^"]*)" depuis la page de l'événement$/
     */
    public function jeCliqueSurDepuisLaPageDeLEvenement(string $button): void
    {
        $this->clickOn(sprintf('/admin/events/%d', $this->getCurrentEvent()?->getId()), $button);
    }

    /**
     * @When /^l'écran d'animation tire le numéro (\d+)$/
     */
    public function lEcranDAnimationTireLeNumero(int $number): void
    {
        $token = $this->adminToken();
        $this->client->request(
            'POST',
            $this->resolvePath("/admin/game/{game}/toggle/{$number}"),
            ['_token' => $token],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    /**
     * @When /^l'écran d'animation relit l'état de la partie$/
     */
    public function lEcranDAnimationRelitLEtatDeLaPartie(): void
    {
        $this->client->request('GET', $this->resolvePath('/admin/game/{game}/state'));
    }

    /**
     * @When /^l'écran de salle relit l'état$/
     */
    public function lEcranDeSalleRelitLEtat(): void
    {
        $this->client->request('GET', '/public/state');
    }

    /**
     * @When /^j'ouvre l'écran de salle$/
     */
    public function jOuvreLEcranDeSalle(): void
    {
        $this->client->request('GET', '/public');
        Assert::assertSame(200, $this->client->getResponse()->getStatusCode(), "L'écran de salle ne s'affiche pas");
    }

    /**
     * @Then /^la réponse doit donner la raison "([^"]*)"$/
     */
    public function laReponseDoitDonnerLaRaison(string $reason): void
    {
        Assert::assertStringContainsString($reason, (string) ($this->jsonResponse()['message'] ?? ''));
    }

    /**
     * @Then /^l'état renvoyé doit compter (\d+) numéros tirés$/
     */
    public function lEtatRenvoyeDoitCompterNumerosTires(int $count): void
    {
        Assert::assertCount($count, $this->state()['draws'] ?? null);
    }

    /**
     * @Then /^l'état renvoyé doit indiquer une partie gelée$/
     */
    public function lEtatRenvoyeDoitIndiquerUnePartieGelee(): void
    {
        Assert::assertTrue($this->state()['isFrozen'] ?? null);
    }

    /**
     * @Then /^l'état renvoyé ne doit pas indiquer une partie gelée$/
     */
    public function lEtatRenvoyeNeDoitPasIndiquerUnePartieGelee(): void
    {
        Assert::assertFalse($this->state()['isFrozen'] ?? null);
    }

    /**
     * @Then /^l'état renvoyé ne doit désigner aucune partie$/
     */
    public function lEtatRenvoyeNeDoitDesignerAucunePartie(): void
    {
        $state = $this->state();
        Assert::assertArrayHasKey('gameId', $state);
        Assert::assertNull($state['gameId']);
    }

    /**
     * @Then /^l'écran de salle doit être en attente$/
     */
    public function lEcranDeSalleDoitEtreEnAttente(): void
    {
        $crawler = $this->client->getCrawler();
        Assert::assertNull($crawler->filter('#empty')->attr('hidden'), "Le message d'attente est masqué");
        Assert::assertNotNull($crawler->filter('#main')->attr('hidden'), "Le tirage est affiché alors qu'aucune partie n'est en cours");
    }

    /**
     * @Then /^l'écran de salle doit contenir la grille des 90 numéros$/
     */
    public function lEcranDeSalleDoitContenirLaGrilleDes90Numeros(): void
    {
        Assert::assertCount(90, $this->client->getCrawler()->filter('#grid .cell'));
    }

    /**
     * @Then /^la requête doit être refusée$/
     */
    public function laRequeteDoitEtreRefusee(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        Assert::assertGreaterThanOrEqual(400, $status, "La requête a été acceptée (statut {$status})");
    }

    /**
     * @Then /^la requête doit être acceptée$/
     */
    public function laRequeteDoitEtreAcceptee(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        Assert::assertLessThan(400, $status, "La requête a été refusée (statut {$status})");
    }

    private function clickOn(string $page, string $button): void
    {
        $crawler = $this->client->request('GET', $page);
        Assert::assertSame(200, $this->client->getResponse()->getStatusCode(), "La page {$page} ne s'affiche pas");

        $node = $crawler->selectButton($button);
        Assert::assertGreaterThan(0, $node->count(), "Bouton « {$button} » introuvable sur {$page}");

        $this->client->submit($node->form());
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(): array
    {
        $response = $this->client->getResponse();
        Assert::assertStringContainsString('json', (string) $response->headers->get('Content-Type'), 'La réponse n\'est pas du JSON');

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * État de la partie : la réponse elle-même, ou sa clé « state » pour un tirage.
     *
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $json = $this->jsonResponse();

        return $json['state'] ?? $json;
    }

    /**
     * Jeton exposé aux scripts par le gabarit de l'administration (balise meta « csrf-token »).
     */
    private function adminToken(): string
    {
        $crawler = $this->client->request('GET', '/admin');
        $meta = $crawler->filter('meta[name="csrf-token"]');
        Assert::assertCount(1, $meta, 'Le tableau de bord n\'expose pas le jeton CSRF');

        return (string) $meta->attr('content');
    }

    /**
     * Remplace {game}, {event} et {player} par les identifiants de la partie d'ordre 1,
     * de l'événement courant et d'un joueur de cet événement.
     */
    private function resolvePath(string $path): string
    {
        $event = $this->getCurrentEvent();
        Assert::assertNotNull($event, 'Aucun événement');

        if (str_contains($path, '{player}')) {
            $player = $this->playerRepo->findOneBy([]);
            if (!$player) {
                $player = (new Player())->setName('Joueur CSRF')->setEvent($event);
                $this->entityManager->persist($player);
                $this->entityManager->flush();
            }
            $path = str_replace('{player}', (string) $player->getId(), $path);
        }

        return strtr($path, [
            '{game}' => (string) $this->findGameByPosition(1)?->getId(),
            '{event}' => (string) $event->getId(),
        ]);
    }
}
