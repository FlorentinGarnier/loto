<?php

namespace App\Service;

use App\Entity\Game;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Envoie l'état des parties aux écrans (animation et salle) par le hub Mercure,
 * une fois la réponse rendue à l'animateur.
 *
 * Publier pendant la requête ferait attendre chaque tirage quand le hub ne répond
 * plus. Sous PHP-FPM, « kernel.terminate » s'exécute après l'envoi de la réponse :
 * le tirage est confirmé tout de suite, la publication suit.
 */
final class GameStatePublisher
{
    /** @var array<int, Update> dernière mise à jour en attente, par partie */
    private array $pending = [];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Met l'état d'une partie en attente d'envoi. Un état plus récent de la même partie remplace le précédent.
     *
     * @param array<string, mixed> $state état construit par GameStateBuilder
     */
    public function queue(Game $game, array $state): void
    {
        $eventId = $game->getEvent()?->getId();
        $gameId = $game->getId();
        if (null === $eventId || null === $gameId) {
            return;
        }

        // Une seule publication pour l'écran d'animation et l'écran de salle
        $this->pending[$gameId] = new Update(
            ["/events/{$eventId}/games/{$gameId}/state", "/events/{$eventId}/public"],
            json_encode($state, \JSON_THROW_ON_ERROR),
        );
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function flush(): void
    {
        $updates = $this->pending;
        $this->pending = [];

        foreach ($updates as $update) {
            try {
                $this->hub->publish($update);
            } catch (\Throwable $e) {
                // Les écrans se resynchronisent seuls (/public/state, /admin/game/{id}/state)
                $this->logger->warning('Publication Mercure impossible : {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            }
        }
    }
}
