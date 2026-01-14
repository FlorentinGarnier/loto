<?php

namespace App\Controller;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Winner;
use App\Enum\GameStatus;
use App\Enum\WinnerSource;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Service\CardService;
use App\Service\DrawService;
use App\Service\WinnerDetectionService;
use App\Service\WinnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly GameRepository $gameRepo,
        private readonly CardRepository $cardRepo,
        private readonly DrawService $drawService,
        private readonly WinnerDetectionService $winnerService,
        private readonly WinnerService $winnerManagementService,
        private readonly CardService $cardService,
        private readonly HubInterface $hub,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_dashboard')]
    #[Route('/', name: 'admin_dashboard_slash')]
    public function dashboard(Request $request): Response
    {
        $event = $this->eventRepo->findLatest();

        if (!$event) {
            // No event yet: render dashboard with safe defaults
            return $this->render('admin/dashboard.html.twig', [
                'event' => null,
                'games' => [],
                'current' => null,
                'potentials' => [],
                'winner_form' => null,
                'cards_count' => 0,
            ]);
        }

        $games = $this->gameRepo->findByEventOrdered($event);
        $current = $this->gameRepo->findRunningByEvent($event) ?? ($games[0] ?? null);

        $cards = $this->cardRepo->findByEvent($event);
        $potentials = $current ? $this->winnerService->findPotentialWinners($current, $cards) : [];

        $winnerFormView = null;
        if ($current) {
            $winnerFormView = $this->createForm(\App\Form\WinnerOfflineType::class, null, ['event' => $event])->createView();
        }

        return $this->render('admin/dashboard.html.twig', [
            'event' => $event,
            'games' => $games,
            'current' => $current,
            'potentials' => $potentials,
            'winner_form' => $winnerFormView,
            'cards_count' => \is_array($cards) ? \count($cards) : 0,
        ]);
    }

    #[Route('/game/{id}/toggle/{number}', name: 'admin_toggle_number', requirements: ['number' => '\\d+'], methods: ['POST'])]
    public function toggleNumber(Game $game, int $number): Response
    {
        if (GameStatus::RUNNING !== $game->getStatus()) {
            if ($this->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Game not running'], 400);
            }
            $this->addFlash('error', 'Impossible de cocher: la partie n\'est pas en cours.');

            return $this->redirectToRoute('admin_dashboard');
        }

        // Vérifier si la partie est gelée
        if ($game->isFrozen()) {
            if ($this->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Game is frozen'], 400);
            }
            $this->addFlash('error', 'Impossible de cocher: la partie est gelée (gagnant détecté).');

            return $this->redirectToRoute('admin_dashboard');
        }

        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $result = $this->drawService->toggleNumber($game, $number, $cards);
        $this->publishGameUpdate($game);

        if ($this->isXmlHttpRequest()) {
            return new JsonResponse($result);
        }

        if ($result['frozen']) {
            $this->addFlash('success', 'Gagnant détecté ! La partie est gelée.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/start', name: 'admin_game_start', methods: ['POST'])]
    public function startGame(Game $game): RedirectResponse
    {
        $event = $game->getEvent();
        // set running only this one
        $previousRunningGame = $this->gameRepo->findRunningByEvent($event);
        foreach ($this->gameRepo->findByEventOrdered($event) as $g) {
            $g->setStatus($g === $game ? GameStatus::RUNNING : (GameStatus::RUNNING === $g->getStatus() ? GameStatus::PENDING : $g->getStatus()));
        }
        $this->em->flush();
        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/finish', name: 'admin_game_finish', methods: ['POST'])]
    public function finishGame(Game $game): RedirectResponse
    {
        $game->setStatus(GameStatus::FINISHED);
        $this->em->flush();
        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/next', name: 'admin_game_next', methods: ['POST'])]
    public function nextGame(Game $game): RedirectResponse
    {
        $event = $game->getEvent();
        $games = $this->gameRepo->findByEventOrdered($event);
        $next = null;
        foreach ($games as $g) {
            if ($g->getPosition() > $game->getPosition()) {
                $next = $g;
                break;
            }
        }
        if ($next) {
            $game->setStatus(GameStatus::FINISHED);
            $next->setStatus(GameStatus::RUNNING);
            if (!$next->getDraws()->isEmpty()) {
                $draws = $next->getDraws();
                $draws->clear();
            }
            $gameDraws = $game->getDraws();
            foreach ($gameDraws as $draw) {
                $next->addDraw($draw);
            }
            $this->em->flush();
            $this->publishGameUpdate($next);
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/demarque', name: 'admin_game_demarque', methods: ['POST'])]
    public function demarque(Game $game): RedirectResponse
    {
        if (GameStatus::RUNNING !== $game->getStatus()) {
            $this->addFlash('error', 'Démarque impossible: la partie doit être en cours.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $this->drawService->clearAll($game);
        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/reset-all-draws', name: 'admin_event_reset_all_draws', methods: ['POST'])]
    public function resetAllDraws(Event $event): RedirectResponse
    {
        $this->drawService->clearAllForEvent($event);
        $this->addFlash('success', 'Tous les tirages de l\'événement ont été remis à zéro.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/reset-all-winners', name: 'admin_event_reset_all_winners', methods: ['POST'])]
    public function resetAllWinners(Event $event): RedirectResponse
    {
        $this->winnerManagementService->clearAllForEvent($event);
        $this->addFlash('success', 'Tous les gagnants de l\'événement ont été supprimés.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/unassign-all-players', name: 'admin_event_unassign_all_players', methods: ['POST'])]
    public function unassignAllPlayers(Event $event): RedirectResponse
    {
        $this->cardService->unassignAllPlayersForEvent($event);
        $this->addFlash('success', 'Tous les joueurs ont été désassociés des cartons.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/reorder', name: 'admin_game_reorder', methods: ['POST'])]
    public function reorderGames(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['order'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $order = $data['order']; // Array of game IDs in the new order
        foreach ($order as $index => $gameId) {
            $game = $this->gameRepo->find($gameId);
            if ($game) {
                $game->setPosition($index + 1);
            }
        }

        $this->em->flush();

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/game/{id}/potentials/fragment', name: 'admin_game_potentials_fragment', methods: ['GET'])]
    public function potentialsFragment(Game $game): Response
    {
        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $potentials = $this->winnerService->findPotentialWinners($game, $cards);

        return $this->render('admin/_potentials_list.html.twig', [
            'current' => $game,
            'potentials' => $potentials,
        ]);
    }

    private function publishGameUpdate(Game $game): void
    {
        $eventId = $game->getEvent()->getId();
        $gameId = $game->getId();
        $topic = "/events/{$eventId}/games/{$gameId}/state";

        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $potentials = $this->winnerService->findPotentialWinners($game, $cards);

        // Déterminer si on a un gagnant déclaré (SYSTEM)
        $hasSystemWinner = false;
        foreach ($game->getWinners() as $winner) {
            if ($winner->getSource() === WinnerSource::SYSTEM) {
                $hasSystemWinner = true;
                break;
            }
        }

        // Afficher le carton quand un gagnant potentiel est détecté (partie gelée)
        $detectedCard = null;
        if ($game->isFrozen() && \count($potentials) > 0) {
            // Prendre le premier carton potentiel détecté
            $firstPotential = $potentials[0];
            $card = $firstPotential['card'];
            $detectedCard = [
                'reference' => $card->getReference(),
                'grid' => $card->getFormattedGrid(),
                'player' => $card->getPlayer() ? $card->getPlayer()->getName() : null,
            ];
        }

        $payload = [
            'gameId' => $gameId,
            'position' => $game->getPosition(),
            'rule' => $game->getRule()->value,
            'prize' => $game->getPrize(),
            'status' => $game->getStatus()->value,
            'draws' => array_map(fn ($d) => $d->getNumber(), $game->getDraws()->toArray()),
            'potentialsCount' => \count($potentials),
            'isFrozen' => $game->isFrozen(),
            'freezeOrderIndex' => $game->getFreezeOrderIndex(),
            'hasSystemWinner' => $hasSystemWinner,
            'detectedCard' => $detectedCard,
        ];
        $update = new Update($topic, json_encode($payload));
        $this->hub->publish($update);
        // Also publish a general event topic for public
        $publicTopic = "/events/{$eventId}/public";
        $this->hub->publish(new Update($publicTopic, json_encode($payload)));
    }

    #[Route('/game/{id}/winner/offline', name: 'admin_winner_offline', methods: ['POST'])]
    public function addOfflineWinner(Request $request, Game $game): RedirectResponse
    {
        $form = $this->createForm(\App\Form\WinnerOfflineType::class, null, ['event' => $game->getEvent()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Formulaire invalide pour le gagnant.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $data = $form->getData();
        $reference = $data['reference'] ?? null;
        $card = ($data['card'] ?? null) instanceof Card ? $data['card'] : null;

        try {
            $this->winnerManagementService->validateOfflineWinner($game, $reference, $card);
            $this->addFlash('success', 'Gagnant OFFLINE enregistré et partie gelée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/winner/validate', name: 'admin_winner_validate', methods: ['POST'])]
    public function validateSystemWinner(Request $request, Game $game): RedirectResponse
    {
        $cardId = (int) ($request->request->get('card_id') ?? 0);
        if ($cardId <= 0) {
            $this->addFlash('error', 'Carte invalide.');

            return $this->redirectToRoute('admin_dashboard');
        }
        // Optional CSRF check
        $token = $request->request->get('_token');
        if ($token && !$this->isCsrfTokenValid('validate_winner_'.$game->getId().'_'.$cardId, $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $card = $this->cardRepo->find($cardId);
        if (!$card || $card->getPlayer()?->getEvent()?->getId() !== $game->getEvent()?->getId()) {
            $this->addFlash('error', 'La carte ne correspond pas a l\'evenement.');

            return $this->redirectToRoute('admin_dashboard');
        }

        // Ensure the card is still a potential winner for the current rule
        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $potentials = $this->winnerService->findPotentialWinners($game, $cards);
        $isPotential = false;
        foreach ($potentials as $p) {
            if ($p['card']->getId() === $cardId) {
                $isPotential = true;
                break;
            }
        }
        if (!$isPotential) {
            $this->addFlash('error', 'La carte ne remplit pas les conditions de la règle.');

            return $this->redirectToRoute('admin_dashboard');
        }

        try {
            $this->winnerManagementService->validateSystemWinner($game, $card);
            $this->addFlash('success', 'Gagnant validé et carton bloqué.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    private function isXmlHttpRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
}
