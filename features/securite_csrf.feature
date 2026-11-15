# language: fr
Fonctionnalité: Protection CSRF des actions d'administration
    Afin qu'un site tiers ne puisse pas agir à la place de l'animateur
    En tant qu'administrateur
    Je veux que chaque action qui modifie le loto exige un jeton CSRF

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23" ont été tirés pour la partie d'ordre 1

    Plan du scénario: Refuser une action envoyée sans jeton
        Quand j'envoie "<requête>" sans jeton CSRF
        Alors la requête doit être refusée
        Et la partie d'ordre 1 doit avoir 3 numéros tirés

        Exemples:
            | requête                                        |
            | POST /admin/game/{game}/toggle/42              |
            | POST /admin/game/{game}/start                  |
            | POST /admin/game/{game}/finish                 |
            | POST /admin/game/{game}/next                   |
            | POST /admin/game/{game}/demarque               |
            | POST /admin/game/{game}/unfreeze               |
            | POST /admin/game/reorder                       |
            | POST /admin/event/{event}/reset-all-draws      |
            | POST /admin/event/{event}/reset-all-winners    |
            | POST /admin/event/{event}/unassign-all-players |
            | POST /admin/players/{player}/cards/assign      |
            | DELETE /admin/players/{player}/cards/A001      |

    Scénario: Refuser une action envoyée avec un jeton invalide
        Quand j'envoie "POST /admin/game/{game}/demarque" avec un jeton CSRF invalide
        Alors la requête doit être refusée
        Et la partie d'ordre 1 doit avoir 3 numéros tirés

    Scénario: Tirer un numéro avec le jeton de l'administration
        Quand j'envoie "POST /admin/game/{game}/toggle/42" avec le jeton de l'administration
        Alors la requête doit être acceptée
        Et le numéro 42 doit être marqué comme tiré dans la partie d'ordre 1

    Scénario: Réordonner les parties en JSON avec le jeton de l'administration
        Quand j'envoie en JSON "POST /admin/game/reorder" avec le jeton de l'administration
        Alors la requête doit être acceptée

    Scénario: Attribuer un carton en JSON avec le jeton de l'administration
        Quand j'envoie en JSON "POST /admin/players/{player}/cards/assign" avec le jeton de l'administration
        Alors la requête doit être acceptée

    Scénario: Faire la démarque depuis le tableau de bord
        Quand je clique sur "Démarque (reset)" depuis le tableau de bord
        Alors la partie d'ordre 1 ne doit plus avoir de numéros tirés

    Scénario: Passer à la partie suivante depuis le tableau de bord
        Quand je clique sur "Partie suivante" depuis le tableau de bord
        Alors la partie d'ordre 2 doit avoir 3 numéros tirés

    Scénario: Réinitialiser les tirages depuis la page de l'événement
        Quand je clique sur "Réinitialiser tous les tirages" depuis la page de l'événement
        Alors la partie d'ordre 1 ne doit plus avoir de numéros tirés
