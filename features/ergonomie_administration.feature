# language: fr
Fonctionnalité: Administration lisible pour un bénévole
    Afin de ne pas avoir à deviner ce que fait l'outil
    En tant qu'animateur
    Je veux des mots clairs, les seules actions utiles et une recherche par référence

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |

    Scénario: Le tableau de bord parle le vocabulaire du loto, pas celui du code
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand j'ouvre la page "/admin"
        Alors la page doit contenir "En cours"
        Et la page doit contenir "Quine"
        Et la page ne doit pas contenir "RUNNING"
        Et la page ne doit pas contenir "PENDING"

    Scénario: Une partie en attente ne propose que son démarrage
        Quand j'ouvre la page "/admin"
        Alors la page doit proposer le bouton "Démarrer la partie"
        Et la page ne doit pas proposer le bouton "Terminer"
        Et la page ne doit pas proposer le bouton "Démarque"

    Scénario: Une partie en cours ne propose plus de la démarrer
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand j'ouvre la page "/admin"
        Alors la page ne doit pas proposer le bouton "Démarrer la partie"
        Et la page doit proposer le bouton "Partie suivante"
        Et la page doit proposer le bouton "Terminer"
        Et la page doit proposer le bouton "Démarque"

    Scénario: La dernière partie ne propose pas de partie suivante
        Étant donné que la partie d'ordre 2 est en statut "RUNNING"
        Quand j'ouvre la page "/admin"
        Alors la page ne doit pas proposer le bouton "Partie suivante"
        Et la page doit proposer le bouton "Terminer"

    Scénario: Déclarer un gagnant en salle avec la référence de son carton
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et un carton "A001" avec joueur "Alice" existe avec la grille suivante:
            | ligne | numéros        |
            | 1     | 1,12,23,34,45  |
            | 2     | 6,17,28,39,50  |
            | 3     | 11,22,33,44,55 |
        Quand je déclare en salle le gagnant "Table 4" avec le carton "a001"
        Alors la page doit contenir "Gagnant en salle enregistré"
        Et un gagnant de source "OFFLINE" doit être enregistré pour la partie d'ordre 1
        Et le gagnant doit référencer le carton "A001"

    Scénario: Refuser un gagnant en salle dont le carton est inconnu
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Quand je déclare en salle le gagnant "Table 4" avec le carton "ZZZ"
        Alors la page doit contenir "Aucun carton « ZZZ »"
        Et aucun gagnant ne doit être enregistré pour la partie d'ordre 1
        Et la partie d'ordre 1 ne doit pas être gelée

    Scénario: Le lien Parties du menu mène aux parties de l'événement courant
        Quand j'ouvre la page "/admin/games"
        Alors la page doit contenir "Parties – Loto de la kermesse"

    Scénario: Chercher un joueur par son nom
        Étant donné un carton "A001" avec joueur "Alice" existe avec la grille suivante:
            | ligne | numéros        |
            | 1     | 1,12,23,34,45  |
            | 2     | 6,17,28,39,50  |
            | 3     | 11,22,33,44,55 |
        Et un carton "B001" avec joueur "Bernard" existe avec la grille suivante:
            | ligne | numéros        |
            | 1     | 2,13,24,35,46  |
            | 2     | 7,18,29,40,51  |
            | 3     | 10,21,32,43,54 |
        Quand j'ouvre la page "/admin/players?search=ali"
        Alors la page doit contenir "Alice"
        Et la page ne doit pas contenir "Bernard"
        Et la page doit contenir "1 joueur"
