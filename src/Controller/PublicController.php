<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\GameRepository;
use App\Service\GameStateBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly GameRepository $gameRepo,
        private readonly GameStateBuilder $stateBuilder,
    ) {
    }

    /**
     * L'adresse nue : l'organisateur connecté va à l'administration,
     * le visiteur choisit entre suivre le tirage et se connecter.
     */
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('public/home.html.twig', [
            'event' => $this->eventRepo->findLatest(),
        ]);
    }

    #[Route('/public', name: 'public_display')]
    public function display(): Response
    {
        $event = $this->eventRepo->findLatest();
        $current = $event ? $this->gameRepo->findRunningByEvent($event) : null;

        return $this->render('public/display.html.twig', [
            'event' => $event,
            'current' => $current,
        ]);
    }

    /**
     * État de la partie en cours, lu par l'écran de salle à l'ouverture
     * et après une coupure : le flux Mercure ne rejoue pas ce qui a été manqué.
     */
    #[Route('/public/state', name: 'public_state', methods: ['GET'])]
    public function state(): JsonResponse
    {
        $event = $this->eventRepo->findLatest();
        $current = $event ? $this->gameRepo->findRunningByEvent($event) : null;

        $response = new JsonResponse($current ? $this->stateBuilder->build($current) : ['eventId' => $event?->getId(), 'gameId' => null]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
