<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Security\AdminCsrf;
use App\Service\CardService;
use App\Service\DrawService;
use App\Service\GameStateBuilder;
use App\Service\GameStatePublisher;
use App\Service\WinnerDetectionService;
use App\Service\WinnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

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
        private readonly GameStateBuilder $stateBuilder,
        private readonly GameStatePublisher $publisher,
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

    /**
     * Lien « Parties » du menu quand aucun événement n'est affiché : parties de l'événement courant.
     */
    #[Route('/games', name: 'admin_game_current')]
    public function currentGames(): RedirectResponse
    {
        $event = $this->eventRepo->findLatest();
        if (!$event) {
            $this->addFlash('info', 'Créez d\'abord un événement : les parties se définissent par événement.');

            return $this->redirectToRoute('admin_event_index');
        }

        return $this->redirectToRoute('admin_game_index', ['id' => $event->getId()]);
    }

    #[Route('/game/{id}/toggle/{number}', name: 'admin_toggle_number', requirements: ['number' => '\\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function toggleNumber(Request $request, Game $game, int $number): Response
    {
        $refusal = match (true) {
            GameStatus::RUNNING !== $game->getStatus() => ['not_running', 'La partie n\'est pas en cours : le numéro n\'a pas été tiré.'],
            $game->isFrozen() => ['frozen', 'La partie est gelée (gagnant détecté) : le numéro n\'a pas été tiré.'],
            default => null,
        };

        if (null !== $refusal) {
            [$error, $message] = $refusal;
            if ($request->isXmlHttpRequest()) {
                // L'état joint permet à l'écran de se remettre d'accord avec le serveur
                return new JsonResponse(['error' => $error, 'message' => $message, 'state' => $this->stateBuilder->build($game)], Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', $message);

            return $this->redirectToRoute('admin_dashboard');
        }

        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $result = $this->drawService->toggleNumber($game, $number, $cards);
        $state = $this->publishGameUpdate($game);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($result + ['state' => $state]);
        }

        if ($result['frozen']) {
            $this->addFlash('success', 'Gagnant détecté ! La partie est gelée.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/state', name: 'admin_game_state', methods: ['GET'])]
    public function gameState(Game $game): JsonResponse
    {
        return new JsonResponse($this->stateBuilder->build($game));
    }

    #[Route('/game/{id}/start', name: 'admin_game_start', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
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
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function finishGame(Game $game): RedirectResponse
    {
        $game->setStatus(GameStatus::FINISHED);
        $this->em->flush();
        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/next', name: 'admin_game_next', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
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
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function demarque(Game $game): RedirectResponse
    {
        if (GameStatus::RUNNING !== $game->getStatus()) {
            $this->addFlash('error', 'Démarque impossible : la partie n\'est pas en cours.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $this->drawService->clearAll($game);
        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/reset-all-draws', name: 'admin_event_reset_all_draws', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function resetAllDraws(Event $event): RedirectResponse
    {
        $this->drawService->clearAllForEvent($event);
        $this->addFlash('success', 'Tous les tirages de l\'événement ont été remis à zéro.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/reset-all-winners', name: 'admin_event_reset_all_winners', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function resetAllWinners(Event $event): RedirectResponse
    {
        $this->winnerManagementService->clearAllForEvent($event);
        $this->addFlash('success', 'Tous les gagnants de l\'événement ont été supprimés.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/event/{id}/unassign-all-players', name: 'admin_event_unassign_all_players', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function unassignAllPlayers(Event $event): RedirectResponse
    {
        $this->cardService->unassignAllPlayersForEvent($event);
        $this->addFlash('success', 'Tous les joueurs ont été retirés de l\'événement.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/reorder', name: 'admin_game_reorder', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
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

    #[Route('/game/{id}/unfreeze', name: 'admin_game_unfreeze', methods: ['POST'])]
    #[IsCsrfTokenValid(AdminCsrf::TOKEN_ID)]
    public function unfreezeGame(Request $request, Game $game): JsonResponse|RedirectResponse
    {
        $game->setIsFrozen(false);
        $this->em->persist($game);
        $this->em->flush();
        $this->publishGameUpdate($game);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['frozen' => false]);
        }

        $this->addFlash('success', 'La partie a été dégelée.');

        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Construit l'état de la partie et le met en attente d'envoi aux écrans :
     * la publication part après la réponse, pour ne jamais faire attendre l'animateur.
     *
     * @return array<string, mixed> l'état de la partie
     */
    private function publishGameUpdate(Game $game): array
    {
        $state = $this->stateBuilder->build($game);
        $this->publisher->queue($game, $state);

        return $state;
    }

    #[Route('/game/{id}/winner/offline', name: 'admin_winner_offline', methods: ['POST'])]
    public function addOfflineWinner(Request $request, Game $game): RedirectResponse
    {
        $form = $this->createForm(\App\Form\WinnerOfflineType::class, null, ['event' => $game->getEvent()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Indiquez le nom ou le repère du gagnant en salle.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $data = $form->getData();
        $reference = $data['reference'] ?? null;
        $cardReference = trim((string) ($data['cardReference'] ?? ''));
        $card = null;
        if ('' !== $cardReference) {
            $card = $this->cardRepo->findOneByReferenceForEvent($cardReference, $game->getEvent());
            if (!$card) {
                $this->addFlash('error', sprintf('Aucun carton « %s » parmi les cartons attribués de cet événement. Le gagnant n\'a pas été enregistré.', $cardReference));

                return $this->redirectToRoute('admin_dashboard');
            }
            if ($card->isBlocked()) {
                $this->addFlash('error', sprintf('Le carton « %s » a déjà gagné : il est bloqué. Le gagnant n\'a pas été enregistré.', $card->getReference()));

                return $this->redirectToRoute('admin_dashboard');
            }
        }

        try {
            $this->winnerManagementService->validateOfflineWinner($game, $reference, $card);
            $this->addFlash('success', 'Gagnant en salle enregistré. La partie est gelée.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->winnerRefusal($e));
        }

        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/winner/validate', name: 'admin_winner_validate', methods: ['POST'])]
    public function validateSystemWinner(Request $request, Game $game): RedirectResponse
    {
        $cardId = (int) ($request->request->get('card_id') ?? 0);
        if ($cardId <= 0) {
            $this->addFlash('error', 'Carton introuvable. Rechargez la page, puis recommencez.');

            return $this->redirectToRoute('admin_dashboard');
        }
        // Jeton CSRF obligatoire : une requête sans jeton est refusée
        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('validate_winner_'.$game->getId().'_'.$cardId, $token)) {
            $this->addFlash('error', 'La page a expiré. Rechargez-la, puis recommencez.');

            return $this->redirectToRoute('admin_dashboard');
        }
        $card = $this->cardRepo->find($cardId);
        if (!$card || $card->getPlayer()?->getEvent()?->getId() !== $game->getEvent()?->getId()) {
            $this->addFlash('error', 'Ce carton n\'appartient pas à cet événement.');

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
            $this->addFlash('error', 'Ce carton ne remplit plus les conditions de la règle.');

            return $this->redirectToRoute('admin_dashboard');
        }

        try {
            $this->winnerManagementService->validateSystemWinner($game, $card);
            $this->addFlash('success', 'Gagnant validé et carton bloqué.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->winnerRefusal($e));
        }

        $this->publishGameUpdate($game);

        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Raison d'un refus de validation, dans les mots de l'animateur :
     * les messages des services sont techniques et en anglais.
     */
    private function winnerRefusal(\RuntimeException $e): string
    {
        return match ($e->getMessage()) {
            'Cannot validate winner on non-frozen game' => 'La partie n\'est pas gelée : aucun gagnant ne peut être validé.',
            'Cannot validate blocked card' => 'Ce carton a déjà gagné : il est bloqué.',
            'Card already validated as winner for this game' => 'Ce carton est déjà validé comme gagnant de cette partie.',
            default => 'Le gagnant n\'a pas pu être enregistré.',
        };
    }
}
