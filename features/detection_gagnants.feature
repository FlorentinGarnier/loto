# language: fr
Fonctionnalité: Détection et validation des gagnants
    Afin de désigner les gagnants d'une partie de loto
    En tant qu'administrateur
    Je veux détecter automatiquement les gagnants potentiels et les valider

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |
            | 3     | FULL_CARD    | Voiture        |

    Scénario: Détecter automatiquement un gagnant potentiel (QUINE)
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand la détection automatique s'exécute
        Alors le carton "A001" doit être détecté comme gagnant potentiel
        Et la partie d'ordre 1 doit être gelée
        Et l'index de gel doit correspondre au numéro 67

    Scénario: Détecter un gagnant potentiel (DOUBLE_QUINE)
        Étant donné que la partie d'ordre 2 est en statut "RUNNING"
        Et qu'un carton "B001" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Et que les numéros "5,15,23,45,67,8,12,34,56,78" ont été tirés pour la partie d'ordre 2
        Quand la détection automatique s'exécute
        Alors le carton "B001" doit être détecté comme gagnant potentiel
        Et la partie d'ordre 2 doit être gelée

    Scénario: Détecter un gagnant potentiel (FULL_CARD - Carton plein)
        Étant donné que la partie d'ordre 3 est en statut "RUNNING"
        Et qu'un carton "C001" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Et que tous les numéros du carton "C001" ont été tirés
        Quand la détection automatique s'exécute
        Alors le carton "C001" doit être détecté comme gagnant potentiel
        Et la partie d'ordre 3 doit être gelée

    Scénario: Valider un gagnant système
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" est détecté comme gagnant potentiel
        Et que la partie d'ordre 1 est gelée
        Quand je valide le carton "A001" comme gagnant
        Alors un gagnant de source "SYSTEM" doit être enregistré pour la partie d'ordre 1
        Et le gagnant doit référencer le carton "A001"
        Et le carton "A001" doit être bloqué avec la raison "WINNER_VALIDATED"

    Scénario: Ajouter un gagnant offline (sans détection automatique)
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe
        Quand j'ajoute manuellement le carton "A001" comme gagnant offline
        Alors un gagnant de source "OFFLINE" doit être enregistré pour la partie d'ordre 1
        Et le gagnant doit référencer le carton "A001"
        Et la partie d'ordre 1 doit être gelée
        Et le carton "A001" doit être bloqué avec la raison "WINNER_OFFLINE"

    Scénario: Ajouter un gagnant offline avec référence uniquement
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand j'ajoute manuellement la référence "Z999" comme gagnant offline sans carton
        Alors un gagnant de source "OFFLINE" doit être enregistré pour la partie d'ordre 1
        Et le gagnant doit avoir la référence "Z999"
        Et la partie d'ordre 1 doit être gelée

    Scénario: Plusieurs cartons gagnants potentiels en même temps
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les cartons suivants existent:
            | référence | ligne_1_numéros   |
            | A001      | 5,15,23,45,67     |
            | A002      | 5,15,23,45,89     |
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand la détection automatique s'exécute
        Alors 1 carton gagnant potentiel doit être détecté
        Et le carton "A001" doit être dans la liste des gagnants potentiels
        Et le carton "A002" ne doit pas être dans la liste des gagnants potentiels

    Scénario: Réinitialiser tous les gagnants d'un événement
        Étant donné que la partie d'ordre 1 a un gagnant validé
        Et que la partie d'ordre 2 a un gagnant validé
        Quand je réinitialise tous les gagnants de l'événement "Loto de la kermesse"
        Alors la partie d'ordre 1 ne doit plus avoir de gagnants
        Et la partie d'ordre 2 ne doit plus avoir de gagnants
        Et tous les cartons bloqués doivent être débloqués

    Scénario: Afficher automatiquement le carton du gagnant potentiel
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" avec joueur "Dupont" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand la détection automatique s'exécute et publie l'état via Mercure
        Alors l'état publié doit contenir les informations du carton détecté:
            | champ     | valeur |
            | reference | A001   |
            | player    | Dupont |
        Et l'état publié doit contenir la grille formatée du carton
