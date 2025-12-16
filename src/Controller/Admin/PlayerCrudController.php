<?php

namespace App\Controller\Admin;

use App\Entity\Player;
use App\Form\PlayerType;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/players')]
final class PlayerCrudController extends AbstractController
{
    public function __construct(
        private readonly PlayerRepository $players,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_player_index')]
    public function index(): Response
    {
        return $this->render('admin/player/index.html.twig', [
            'players' => $this->players->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_player_new')]
    public function new(Request $request): Response
    {
        $player = new Player();
        $form = $this->createForm(PlayerType::class, $player);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($player);
            $this->em->flush();
            $this->addFlash('success', 'Joueur créé.');

            return $this->redirectToRoute('admin_player_index');
        }

        return $this->render('admin/player/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_player_show', requirements: ['id' => '\\d+'])]
    public function show(Player $player): Response
    {
        return $this->render('admin/player/show.html.twig', [
            'player' => $player,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_player_edit', requirements: ['id' => '\\d+'])]
    public function edit(Player $player, Request $request): Response
    {
        $form = $this->createForm(PlayerType::class, $player);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Joueur mis à jour.');

            return $this->redirectToRoute('admin_player_index');
        }

        return $this->render('admin/player/edit.html.twig', [
            'form' => $form->createView(),
            'player' => $player,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_player_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Player $player, Request $request): Response
    {
        $this->validateCsrf($request, 'delete_player_'.$player->getId());
        $this->em->remove($player);
        $this->em->flush();
        $this->addFlash('success', 'Joueur supprimé.');

        return $this->redirectToRoute('admin_player_index');
    }

    private function validateCsrf(Request $request, string $id): void
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
