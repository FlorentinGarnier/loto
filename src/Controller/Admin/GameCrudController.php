<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Game;
use App\Form\GameType;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/events/{id}/games', requirements: ['id' => '\\d+'])]
final class GameCrudController extends AbstractController
{
    public function __construct(
        private readonly GameRepository $gameRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_game_index')]
    public function index(Event $event): Response
    {
        $games = $this->gameRepo->findBy(['event' => $event], ['position' => 'ASC']);

        return $this->render('admin/game/index.html.twig', [
            'event' => $event,
            'games' => $games,
        ]);
    }

    #[Route('/new', name: 'admin_game_new')]
    public function new(Event $event, Request $request): Response
    {
        $game = (new Game())->setEvent($event);
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($game);
            $this->em->flush();
            $this->addFlash('success', 'Partie créée');

            return $this->redirectToRoute('admin_game_index', ['id' => $event->getId()]);
        }

        return $this->render('admin/game/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{gameId}/edit', name: 'admin_game_edit', requirements: ['gameId' => '\\d+'])]
    public function edit(Event $event, int $gameId, Request $request): Response
    {
        $game = $this->gameRepo->find($gameId);
        if (!$game || $game->getEvent()?->getId() !== $event->getId()) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Partie mise à jour');

            return $this->redirectToRoute('admin_game_index', ['id' => $event->getId()]);
        }

        return $this->render('admin/game/edit.html.twig', [
            'event' => $event,
            'form' => $form,
            'game' => $game,
        ]);
    }

    #[Route('/{gameId}/delete', name: 'admin_game_delete', methods: ['POST'], requirements: ['gameId' => '\\d+'])]
    public function delete(Event $event, int $gameId, Request $request): Response
    {
        $game = $this->gameRepo->find($gameId);
        if (!$game || $game->getEvent()?->getId() !== $event->getId()) {
            throw $this->createNotFoundException();
        }
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_game_'.$game->getId(), $token)) {
            $this->em->remove($game);
            $this->em->flush();
            $this->addFlash('success', 'Partie supprimée');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide');
        }

        return $this->redirectToRoute('admin_game_index', ['id' => $event->getId()]);
    }
}
