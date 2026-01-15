<?php

namespace App\Controller\Admin;

use App\Entity\Card;
use App\Form\CardType;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cards')]
final class CardCrudController extends AbstractController
{
    public function __construct(
        private readonly CardRepository $cards,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_card_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $this->cards->createQueryBuilder('c')
            ->leftJoin('c.player', 'p')
            ->leftJoin('p.event', 'e');

        if ($search) {
            $qb->where('c.reference LIKE :search')
                ->orWhere('p.name LIKE :search')
                ->orWhere('e.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $qb->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery());
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);

        $matrix = [];
        foreach ($paginator as $card) {
            $matrix[] = [
                'id' => $card->getId(),
                'reference' => $card->getReference(),
                'player' => $card->getPlayer(),
                'event' => $card->getPlayer()?->getEvent(),
                'grid' => $card->getFormattedGrid(),
            ];
        }

        return $this->render('admin/card/index.html.twig', [
            'matrix' => $matrix,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    #[Route('/new', name: 'admin_card_new')]
    public function new(Request $request): Response
    {
        $card = new Card();
        $form = $this->createForm(CardType::class, $card);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $grid = $this->parseGrid($form->get('gridText')->getData());
            if (null === $grid) {
                $this->addFlash('error', 'La grille doit contenir 3 lignes de 5 nombres entre 1 et 90, sans doublons.');
            } else {
                $card->setGrid($grid);
                $this->em->persist($card);
                $this->em->flush();
                $this->addFlash('success', 'Carton créé.');

                return $this->redirectToRoute('admin_card_index');
            }
        }

        return $this->render('admin/card/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_card_edit', requirements: ['id' => '\\d+'])]
    public function edit(Card $card, Request $request): Response
    {
        $form = $this->createForm(CardType::class, $card);
        // pre-fill gridText with current grid
        $form->get('gridText')->setData($this->gridToText($card->getGrid()));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $grid = $this->parseGrid($form->get('gridText')->getData());
            if (null === $grid) {
                $this->addFlash('error', 'La grille doit contenir 3 lignes de 5 nombres entre 1 et 90, sans doublons.');
            } else {
                $card->setGrid($grid);
                $this->em->flush();
                $this->addFlash('success', 'Carton mis à jour.');

                return $this->redirectToRoute('admin_card_index');
            }
        }

        return $this->render('admin/card/edit.html.twig', [
            'form' => $form->createView(),
            'card' => $card,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_card_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Card $card, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_card_'.$card->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $this->em->remove($card);
        $this->em->flush();
        $this->addFlash('success', 'Carton supprimé.');

        return $this->redirectToRoute('admin_card_index');
    }

    private function parseGrid(?string $text): ?array
    {
        if (null === $text) {
            return null;
        }
        $lines = preg_split('/\r?\n/', trim($text));
        if (3 !== count($lines)) {
            return null;
        }
        $grid = [];
        $seen = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (5 !== count($parts)) {
                return null;
            }
            $row = [];
            foreach ($parts as $p) {
                if (!preg_match('/^\d{1,2}$/', $p) && !preg_match('/^90$/', $p)) {
                    return null;
                }
                $n = (int) $p;
                if ($n < 1 || $n > 90) {
                    return null;
                }
                if (isset($seen[$n])) {
                    return null;
                } // unique per card
                $seen[$n] = true;
                $row[] = $n;
            }
            $grid[] = $row;
        }

        return $grid;
    }

    private function gridToText(array $grid): string
    {
        $lines = [];
        for ($i = 0; $i < 3; ++$i) {
            $row = $grid[$i] ?? [];
            $lines[] = implode(' ', $row);
        }

        return implode("\n", $lines);
    }
}
