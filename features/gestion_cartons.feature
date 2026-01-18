# language: fr
Fonctionnalité: Gestion des cartons
    Afin de préparer une soirée de loto
    En tant qu'administrateur
    Je veux gérer les cartons et les associer aux joueurs

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe

    Scénario: Créer un nouveau carton
        Quand je crée un carton avec la référence "A001" et la grille suivante:
            | ligne | numéros           |
            | 1     | 5,15,23,45,67     |
            | 2     | 8,12,34,56,78     |
            | 3     | 2,18,29,43,89     |
        Alors le carton "A001" doit exister
        Et le carton "A001" doit contenir les numéros "5,15,23,45,67" sur la ligne 1
        Et le carton "A001" doit contenir les numéros "8,12,34,56,78" sur la ligne 2
        Et le carton "A001" doit contenir les numéros "2,18,29,43,89" sur la ligne 3

    Scénario: Associer un carton à un joueur
        Étant donné qu'un carton "A001" existe
        Et qu'un joueur "Dupont" existe
        Et que le joueur "Dupont" est associé à l'événement "Loto de la kermesse"
        Quand j'associe le carton "A001" au joueur "Dupont"
        Alors le carton "A001" doit être associé au joueur "Dupont"

    Scénario: Bloquer un carton gagnant
        Étant donné qu'un carton "A001" existe
        Quand je bloque le carton "A001" pour la raison "WINNER_VALIDATED"
        Alors le carton "A001" doit être bloqué
        Et le carton "A001" doit avoir la raison de blocage "WINNER_VALIDATED"

    Scénario: Débloquer un carton
        Étant donné qu'un carton "A001" existe
        Et que le carton "A001" est bloqué pour la raison "WINNER_VALIDATED"
        Quand je débloque le carton "A001"
        Alors le carton "A001" ne doit plus être bloqué

    Scénario: Lister les cartons d'un événement
        Étant donné que les cartons suivants existent pour l'événement "Loto de la kermesse":
            | référence | joueur  |
            | A001      | Dupont  |
            | A002      | Martin  |
            | A003      |         |
        Quand je liste les cartons de l'événement "Loto de la kermesse"
        Alors je dois voir 3 cartons
        Et le carton "A001" doit être associé au joueur "Dupont"
        Et le carton "A002" doit être associé au joueur "Martin"
        Et le carton "A003" ne doit pas avoir de joueur associé
