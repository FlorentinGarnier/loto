# language: fr
Fonctionnalité: Écrans fidèles à l'état du serveur
    Afin de ne jamais annoncer un numéro que la salle ne voit pas
    En tant qu'animateur
    Je veux que l'écran d'animation et l'écran de salle suivent l'état réel de la partie

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |

    Scénario: Un tirage accepté renvoie l'état de la partie
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23" ont été tirés pour la partie d'ordre 1
        Quand l'écran d'animation tire le numéro 42
        Alors la requête doit être acceptée
        Et l'état renvoyé doit compter 4 numéros tirés
        Et l'état renvoyé ne doit pas indiquer une partie gelée

    Scénario: Un tirage sur une partie gelée est refusé avec sa raison
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23" ont été tirés pour la partie d'ordre 1
        Et que la partie d'ordre 1 est gelée (gagnant détecté)
        Quand l'écran d'animation tire le numéro 42
        Alors la requête doit être refusée
        Et la réponse doit donner la raison "gelée"
        Et l'état renvoyé doit compter 3 numéros tirés
        Et l'état renvoyé doit indiquer une partie gelée
        Et la partie d'ordre 1 doit avoir 3 numéros tirés

    Scénario: Un tirage sur une partie qui n'est pas en cours est refusé avec sa raison
        Quand l'écran d'animation tire le numéro 42
        Alors la requête doit être refusée
        Et la réponse doit donner la raison "n'est pas en cours"
        Et la partie d'ordre 1 ne doit plus avoir de numéros tirés

    Scénario: L'écran d'animation relit l'état après une coupure
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23" ont été tirés pour la partie d'ordre 1
        Quand l'écran d'animation relit l'état de la partie
        Alors la requête doit être acceptée
        Et l'état renvoyé doit compter 3 numéros tirés

    Scénario: L'écran de salle ouvert avant le début attend la partie sans rechargement
        Quand j'ouvre l'écran de salle
        Alors l'écran de salle doit être en attente
        Et l'écran de salle doit contenir la grille des 90 numéros

    Scénario: L'écran de salle relit la partie en cours
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23" ont été tirés pour la partie d'ordre 1
        Quand l'écran de salle relit l'état
        Alors la requête doit être acceptée
        Et l'état renvoyé doit compter 3 numéros tirés

    Scénario: L'écran de salle ne reçoit aucune partie tant qu'aucune n'est en cours
        Quand l'écran de salle relit l'état
        Alors la requête doit être acceptée
        Et l'état renvoyé ne doit désigner aucune partie
