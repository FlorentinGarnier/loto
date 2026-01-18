# language: fr
Fonctionnalité: Gestion des cas d'erreur et edge cases
    Afin de garantir la robustesse de l'application
    En tant qu'administrateur
    Je veux que l'application gère correctement les cas d'erreur

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe

    # ========== ÉVÉNEMENTS ET PARTIES ==========

    Scénario: Créer un événement avec une date invalide
        Quand je tente de créer un événement avec la date invalide "invalid-date"
        Alors une erreur doit être levée
        Et le message doit contenir "Invalid date format"

    Scénario: Démarrer une partie qui n'existe pas
        Quand je tente de démarrer la partie d'ordre 999
        Alors une erreur doit être levée
        Et le message doit contenir "Aucune partie trouvée"

    Scénario: Démarrer une partie déjà terminée
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "FINISHED"
        Quand je tente de démarrer la partie d'ordre 1
        Alors la partie d'ordre 1 doit rester en statut "FINISHED"

    Scénario: Créer une partie avec une règle invalide
        Quand je tente de créer une partie avec la règle invalide "INVALID_RULE"
        Alors une erreur doit être levée

    # ========== JOUEURS ==========

    Scénario: Créer un joueur avec un email invalide
        Quand je tente de créer un joueur avec l'email invalide "not-an-email"
        Alors une erreur doit être levée

    Scénario: Associer un joueur à un événement inexistant
        Étant donné qu'un joueur "Dupont" existe
        Quand je tente d'associer le joueur "Dupont" à l'événement inexistant "Événement Fantôme"
        Alors une erreur doit être levée
        Et le message doit contenir "n'existe pas"

    # ========== CARTONS ==========

    Scénario: Créer un carton avec une grille invalide (moins de 3 lignes)
        Quand je tente de créer un carton avec seulement 2 lignes
        Alors une erreur doit être levée

    Scénario: Créer un carton avec une grille invalide (pas 5 numéros par ligne)
        Quand je tente de créer un carton avec 3 numéros sur une ligne
        Alors une erreur doit être levée

    Scénario: Bloquer un carton déjà bloqué
        Étant donné qu'un carton "A001" existe
        Et que le carton "A001" est bloqué pour la raison "WINNER"
        Quand je tente de bloquer à nouveau le carton "A001"
        Alors le carton "A001" doit rester bloqué
        Et la raison de blocage doit toujours être "WINNER"

    Scénario: Créer un carton avec une référence déjà existante
        Étant donné qu'un carton "A001" existe
        Quand je tente de créer un autre carton avec la référence "A001"
        Alors une erreur doit être levée
        Et le message doit contenir "already exists"

    Scénario: Associer un carton à un joueur inexistant
        Étant donné qu'un carton "A001" existe
        Quand je tente d'associer le carton "A001" au joueur inexistant "Fantôme"
        Alors une erreur doit être levée

    # ========== TIRAGES ==========

    Scénario: Tirer un numéro hors limites (< 1)
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tente de tirer le numéro 0 pour la partie d'ordre 1
        Alors une erreur doit être levée
        Et le message doit contenir "between 1 and 90"

    Scénario: Tirer un numéro hors limites (> 90)
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tente de tirer le numéro 91 pour la partie d'ordre 1
        Alors une erreur doit être levée
        Et le message doit contenir "between 1 and 90"

    Scénario: Tirer un numéro pour une partie qui n'est pas en cours
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "PENDING"
        Quand je tente de tirer le numéro 42 pour la partie d'ordre 1
        Alors le numéro 42 ne doit pas être marqué comme tiré dans la partie d'ordre 1

    Scénario: Tirer le même numéro deux fois
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et que le numéro 42 a été tiré pour la partie d'ordre 1
        Quand je tente de tirer à nouveau le numéro 42 pour la partie d'ordre 1
        Alors la partie d'ordre 1 doit toujours avoir 1 numéro tiré

    Scénario: Annuler un tirage quand aucun numéro n'a été tiré
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tente d'annuler le dernier numéro tiré
        Alors une erreur doit être levée
        Et le message doit contenir "Aucun tirage"

    # ========== GAGNANTS ==========

    Scénario: Valider un gagnant système sans que la partie soit gelée
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe
        Quand je tente de valider le carton "A001" comme gagnant système
        Alors une erreur doit être levée
        Et le message doit contenir "non-frozen game"

    Scénario: Valider un carton déjà bloqué comme gagnant
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe
        Et que le carton "A001" est bloqué pour la raison "WINNER"
        Et que la partie d'ordre 1 est gelée (gagnant détecté)
        Quand je tente de valider le carton "A001" comme gagnant système
        Alors une erreur doit être levée
        Et le message doit contenir "blocked card"

    Scénario: Valider deux fois le même carton comme gagnant
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe avec la grille suivante:
            | ligne | numéros       |
            | 1     | 5,15,23,45,67 |
            | 2     | 8,12,34,56,78 |
            | 3     | 2,18,29,43,89 |
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Et que la détection automatique s'exécute
        Et que le carton "A001" est validé comme gagnant
        Quand je tente de valider à nouveau le carton "A001" comme gagnant
        Alors une erreur doit être levée
        Et le message doit contenir "blocked card"

    Scénario: Ajouter un gagnant offline avec un carton bloqué
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe
        Et que le carton "A001" est bloqué pour la raison "WINNER"
        Quand je tente d'ajouter le carton "A001" comme gagnant offline
        Alors une erreur doit être levée
        Et le message doit contenir "blocked card"

    # ========== EDGE CASES ==========

    Scénario: Événement sans parties
        Étant donné qu'un événement "Loto vide" existe
        Quand je tente de démarrer la première partie de l'événement "Loto vide"
        Alors une erreur doit être levée
        Et le message doit contenir "aucune partie"

    Scénario: Passer à la partie suivante quand il n'y en a pas
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tente de passer à la partie suivante
        Alors une erreur doit être levée
        Et le message doit contenir "Aucune partie suivante"

    Scénario: Carton sans numéros dans la grille
        Quand je tente de créer un carton avec une grille vide
        Alors une erreur doit être levée

    Scénario: Détecter des gagnants sans cartons dans l'événement
        Étant donné que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot            |
            | 1     | QUINE | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand la détection automatique s'exécute
        Alors aucun gagnant ne doit être détecté
        Et la partie d'ordre 1 ne doit pas être gelée
