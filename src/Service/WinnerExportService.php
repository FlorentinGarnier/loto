<?php

namespace App\Service;

use App\Entity\Event;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WinnerExportService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Génère le contenu CSV des gagnants d'un événement
     */
    public function generateCsvContent(Event $event): string
    {
        $handle = fopen('php://temp', 'r+');

        // En-têtes CSV
        fputcsv($handle, [
            'Partie',
            'Règle',
            'Prix',
            'Ordre de victoire',
            'Référence carton',
            'Joueur',
            'Téléphone',
            'Email',
            'Source',
            'Date création'
        ], escape: '');

        // Récupérer tous les gagnants de l'événement
        foreach ($event->getGames() as $game) {
            foreach ($game->getWinners() as $winner) {
                $card = $winner->getCard();

                // Utiliser la traduction si possible, sinon la valeur brute
                $ruleLabel = $game->getRule()->value;
                $sourceLabel = $winner->getSource()->value;

                try {
                    $ruleLabel = $game->getRule()->trans($this->translator);
                    $sourceLabel = $winner->getSource()->trans($this->translator);
                } catch (\Throwable $e) {
                    // En cas d'erreur (notamment en test), utiliser les valeurs brutes
                }

                fputcsv($handle, [
                    'Partie #' . $game->getPosition(),
                    $ruleLabel,
                    $game->getPrize() ?: '',
                    $winner->getWinningOrderIndex() + 1,
                    $winner->getReference() ?: ($card ? $card->getReference() : ''),
                    $card && $card->getPlayer() ? $card->getPlayer()->getName() : '',
                    $card && $card->getPlayer() ? $card->getPlayer()->getPhone() : '',
                    $card && $card->getPlayer() ? $card->getPlayer()->getEmail() : '',
                    $sourceLabel,
                    $winner->getCreatedAt()->format('Y-m-d H:i:s'),
                ], escape: '');
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Génère le nom du fichier CSV
     */
    public function generateFilename(Event $event): string
    {
        return 'gagnants_' . $event->getId() . '_' . date('Y-m-d') . '.csv';
    }
}
