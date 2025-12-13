<?php
namespace App\Controller;

use App\Entity\Event;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\CardRepository;
use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Service\DrawService;
use App\Service\WinnerDetectionService;
use App\Entity\Winner;
use App\Entity\Card;
use App\Enum\WinnerSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly GameRepository $gameRepo,
        private readonly CardRepository $cardRepo,
        private readonly DrawService $drawService,
        private readonly WinnerDetectionService $winnerService,
        private readonly HubInterface $hub,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $em,
    ) {}

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
        if ($game->getStatus() !== GameStatus::RUNNING) {
            if ($this->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Game not running'], 400);
            }
            $this->addFlash('error', 'Impossible de cocher: la partie n\'est pas en cours.');
            return $this->redirectToRoute('admin_dashboard');
        }
        $numbers = $this->drawService->toggleNumber($game, $number);
        $this->publishGameUpdate($game);
        if ($this->isXmlHttpRequest()) {
            return new JsonResponse(['numbers' => $numbers]);
        }
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/start', name: 'admin_game_start', methods: ['POST'])]
    public function startGame(Game $game): RedirectResponse
    {
        $event = $game->getEvent();
        // set running only this one
        foreach ($this->gameRepo->findByEventOrdered($event) as $g) {
            $g->setStatus($g === $game ? GameStatus::RUNNING : ($g->getStatus() === GameStatus::RUNNING ? GameStatus::PENDING : $g->getStatus()));
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
            if ($g->getPosition() > $game->getPosition()) { $next = $g; break; }
        }
        if ($next) {
            $game->setStatus(GameStatus::FINISHED);
            $next->setStatus(GameStatus::RUNNING);
            $this->em->flush();
            $this->publishGameUpdate($next);
        }
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/demarque', name: 'admin_game_demarque', methods: ['POST'])]
    public function demarque(Game $game): RedirectResponse
    {
        if ($game->getStatus() !== GameStatus::RUNNING) {
            $this->addFlash('error', 'Démarque impossible: la partie doit être en cours.');
            return $this->redirectToRoute('admin_dashboard');
        }
        $this->drawService->clearAll($game);
        $this->publishGameUpdate($game);
        $this->addFlash('success', 'Tirages réinitialisés pour la partie courante.');
        return $this->redirectToRoute('admin_dashboard');
    }

    private function publishGameUpdate(Game $game): void
    {
        $eventId = $game->getEvent()->getId();
        $gameId = $game->getId();
        $topic = "/events/{$eventId}/games/{$gameId}/state";
        $payload = [
            'gameId' => $gameId,
            'status' => $game->getStatus()->value,
            'draws' => array_map(fn($d) => $d->getNumber(), $game->getDraws()->toArray()),
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
        $winner = (new Winner())
            ->setGame($game)
            ->setSource(WinnerSource::OFFLINE)
            ->setReference($data['reference'] ?? null);
        if (($data['card'] ?? null) instanceof Card) {
            $winner->setCard($data['card']);
        }
        $this->em->persist($winner);
        $this->em->flush();
        $this->addFlash('success', 'Gagnant OFFLINE enregistré.');
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/game/{id}/winner/validate', name: 'admin_winner_validate', methods: ['POST'])]
    public function validateSystemWinner(Request $request, Game $game): RedirectResponse
    {
        $cardId = (int)($request->request->get('card_id') ?? 0);
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
        if (!$card || $card->getEvent()?->getId() !== $game->getEvent()?->getId()) {
            $this->addFlash('error', 'La carte ne correspond pas à l’événement.');
            return $this->redirectToRoute('admin_dashboard');
        }
        // Prevent duplicates
        foreach ($game->getWinners() as $w) {
            if ($w->getCard() && $w->getCard()->getId() === $cardId) {
                $this->addFlash('warning', 'Ce gagnant est déjà validé.');
                return $this->redirectToRoute('admin_dashboard');
            }
        }
        // Ensure the card is still a potential winner for the current rule
        $cards = $this->cardRepo->findByEvent($game->getEvent());
        $potentials = $this->winnerService->findPotentialWinners($game, $cards);
        $isPotential = false;
        foreach ($potentials as $p) {
            if ($p['card']->getId() === $cardId) { $isPotential = true; break; }
        }
        if (!$isPotential) {
            $this->addFlash('error', 'La carte ne remplit pas les conditions de la règle.');
            return $this->redirectToRoute('admin_dashboard');
        }
        $winner = (new Winner())
            ->setGame($game)
            ->setSource(WinnerSource::SYSTEM)
            ->setCard($card)
            ->setReference($card->getReference());
        $this->em->persist($winner);
        $this->em->flush();
        $this->addFlash('success', 'Gagnant validé.');
        return $this->redirectToRoute('admin_dashboard');
    }

    private function isXmlHttpRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
