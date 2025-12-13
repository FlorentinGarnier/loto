<?php
namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly GameRepository $gameRepo,
    ) {}

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->redirectToRoute('public_display');
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
}
