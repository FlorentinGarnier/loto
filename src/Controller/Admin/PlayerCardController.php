<?php

namespace App\Controller\Admin;

use App\Entity\Card;
use App\Entity\Player;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class PlayerCardController extends AbstractController
{
    public function __construct(
        private readonly CardRepository $cards,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/cards/lookup', name: 'admin_card_lookup', methods: ['GET'])]
    public function lookup(Request $request): JsonResponse
    {
        $ref = trim((string) $request->query->get('ref'));
        if ('' === $ref) {
            return $this->json(['message' => 'Référence manquante'], 422);
        }

        $card = $this->cards->findOneBy(['reference' => $ref]);
        if (!$card) {
            return $this->json(['exists' => false], 404);
        }

        $assignedTo = $card->getPlayer();

        return $this->json([
            'exists' => true,
            'assignedTo' => $assignedTo ? [
                'id' => $assignedTo->getId(),
                'name' => $assignedTo->getName(),
            ] : null,
        ]);
    }

    #[Route('/players/{id}/cards/assign', name: 'admin_player_assign_card', methods: ['POST'])]
    public function assign(Player $player, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true) ?: [];
        $ref = trim((string) ($data['ref'] ?? ''));
        $createIfMissing = (bool) ($data['createIfMissing'] ?? false);
        if ('' === $ref) {
            return $this->json(['message' => 'Référence manquante'], 422);
        }

        $card = $this->cards->findOneBy(['reference' => $ref]);
        if (!$card) {
            if (!$createIfMissing) {
                return $this->json(['message' => 'Inexistant'], 404);
            }
            $card = new Card();
            $card->setReference($ref);
            // si la grille est obligatoire, on garde un tableau vide par défaut
            $card->setGrid([]);
            $card->setPlayer($player);
            $this->em->persist($card);
            $this->em->flush();

            return $this->json(['ref' => $ref, 'created' => true], 201);
        }

        $owner = $card->getPlayer();
        if ($owner && $owner->getId() !== $player->getId()) {
            return $this->json(['message' => 'Déjà attribué à '.$owner->getName()], 409);
        }

        if ($owner && $owner->getId() === $player->getId()) {
            return $this->json(['ref' => $ref, 'created' => false, 'message' => 'Déjà dans la liste'], 200);
        }

        $card->setPlayer($player);
        $this->em->flush();

        return $this->json(['ref' => $ref, 'created' => false], 200);
    }

    #[Route('/players/{id}/cards/{ref}', name: 'admin_player_remove_card', methods: ['DELETE'])]
    public function remove(Player $player, string $ref): JsonResponse
    {
        $card = $this->cards->findOneBy(['reference' => $ref, 'player' => $player]);
        if (!$card) {
            return $this->json(['message' => 'Introuvable'], 404);
        }
        $card->setPlayer(null);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }
}
