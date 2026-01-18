# language: fr
Fonctionnalité: Déroulement d'une partie de loto
    Afin d'animer une partie de loto
    En tant qu'administrateur
    Je veux tirer des numéros et suivre l'avancement de la partie

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |
            | 3     | FULL_CARD    | Voiture        |

    Scénario: Tirer un numéro pendant une partie
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tire le numéro 42 pour la partie d'ordre 1
        Alors le numéro 42 doit être marqué comme tiré dans la partie d'ordre 1
        Et la partie d'ordre 1 doit avoir 1 numéro tiré

    Scénario: Tirer plusieurs numéros
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand je tire les numéros suivants pour la partie d'ordre 1:
            | numéro |
            | 5      |
            | 12     |
            | 23     |
            | 45     |
            | 67     |
        Alors la partie d'ordre 1 doit avoir 5 numéros tirés
        Et les numéros "5,12,23,45,67" doivent être marqués comme tirés dans la partie d'ordre 1

    Scénario: Annuler le dernier numéro tiré
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23" ont été tirés pour la partie d'ordre 1
        Quand j'annule le dernier numéro tiré (23) de la partie d'ordre 1
        Alors la partie d'ordre 1 doit avoir 2 numéros tirés
        Et les numéros "5,12" doivent être marqués comme tirés dans la partie d'ordre 1
        Et le numéro 23 ne doit plus être marqué comme tiré

    Scénario: Démarquer tous les numéros d'une partie
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand je démarque tous les numéros de la partie d'ordre 1
        Alors la partie d'ordre 1 ne doit plus avoir de numéros tirés

    Scénario: Réinitialiser tous les tirages d'un événement
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23" ont été tirés pour la partie d'ordre 1
        Et que la partie d'ordre 2 est en statut "PENDING"
        Quand je réinitialise tous les tirages de l'événement "Loto de la kermesse"
        Alors la partie d'ordre 1 ne doit plus avoir de numéros tirés

    Scénario: Transférer les tirages à la partie suivante
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23,45" ont été tirés pour la partie d'ordre 1
        Quand je passe à la partie suivante en conservant les tirages
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"
        Et la partie d'ordre 2 doit avoir 4 numéros tirés
        Et les numéros "5,12,23,45" doivent être marqués comme tirés dans la partie d'ordre 2

    Scénario: Empêcher de tirer un numéro si la partie est gelée
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que la partie d'ordre 1 est gelée (gagnant détecté)
        Quand je tente de tirer le numéro 42 pour la partie d'ordre 1
        Alors le tirage doit être refusé
        Et un message d'erreur doit indiquer que la partie est gelée
