# language: fr
Fonctionnalité: Export des gagnants en CSV
    Afin d'avoir un rapport des gagnants
    En tant qu'administrateur
    Je veux exporter la liste des gagnants d'un événement au format CSV

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |
            | 3     | FULL_CARD    | Voiture        |

    Scénario: Exporter les gagnants d'un événement en CSV
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" avec joueur "Dupont" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Et que la détection automatique s'exécute
        Et que je valide le carton "A001" comme gagnant
        Et que la partie d'ordre 2 est en statut "RUNNING"
        Et qu'un carton "B002" avec joueur "Martin" existe avec la grille suivante:
            | ligne | numéros           |
            | 1     | 1,11,21,31,41     |
            | 2     | 2,12,22,32,42     |
            | 3     | 3,13,23,33,43     |
        Et que les numéros "1,11,21,31,41,2,12,22,32,42" ont été tirés pour la partie d'ordre 2
        Et que la détection automatique s'exécute
        Et que je valide le carton "B002" comme gagnant
        Quand j'exporte les gagnants de l'événement "Loto de la kermesse"
        Alors le fichier CSV doit contenir 2 lignes de données
        Et la première ligne doit contenir "A001"
        Et la première ligne doit contenir "Dupont"
        Et la deuxième ligne doit contenir "B002"
        Et la deuxième ligne doit contenir "Martin"

    Scénario: Exporter un événement sans gagnants
        Quand j'exporte les gagnants de l'événement "Loto de la kermesse"
        Alors le fichier CSV ne doit contenir aucune ligne de données

    Scénario: Exporter avec gagnants offline
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" avec joueur "Dupont" existe avec la grille suivante:
            | ligne | numéros       |
            | 1     | 5,15,23,45,67 |
            | 2     | 8,12,34,56,78 |
            | 3     | 2,18,29,43,89 |
        Quand j'ajoute manuellement le carton "A001" comme gagnant offline
        Et que j'exporte les gagnants de l'événement "Loto de la kermesse"
        Alors le fichier CSV doit contenir 1 ligne de données
        Et la première ligne doit contenir "En salle"
