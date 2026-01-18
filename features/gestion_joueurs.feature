# language: fr
Fonctionnalité: Gestion des joueurs
    Afin d'organiser les participants à une soirée de loto
    En tant qu'administrateur
    Je veux gérer les joueurs et leur associer des cartons

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe

    Scénario: Créer un nouveau joueur
        Quand je crée un joueur avec les informations suivantes:
            | nom    | email              | téléphone   |
            | Dupont | dupont@example.com | 0612345678  |
        Alors le joueur "Dupont" doit exister
        Et le joueur "Dupont" doit avoir l'email "dupont@example.com"
        Et le joueur "Dupont" doit avoir le téléphone "0612345678"

    Scénario: Associer un joueur à un événement
        Étant donné qu'un joueur "Martin" existe
        Quand j'associe le joueur "Martin" à l'événement "Loto de la kermesse"
        Alors le joueur "Martin" doit être associé à l'événement "Loto de la kermesse"

    Scénario: Lister les joueurs d'un événement
        Étant donné que les joueurs suivants sont associés à l'événement "Loto de la kermesse":
            | nom     |
            | Dupont  |
            | Martin  |
            | Bernard |
        Quand je liste les joueurs de l'événement "Loto de la kermesse"
        Alors je dois voir 3 joueurs
        Et je dois voir les joueurs "Dupont, Martin, Bernard"

    Scénario: Désassocier tous les joueurs d'un événement
        Étant donné que les joueurs suivants ont des cartons pour l'événement "Loto de la kermesse":
            | nom    | références_cartons |
            | Dupont | A001, A002         |
            | Martin | B001               |
        Quand je désassocie tous les joueurs de leurs cartons pour l'événement "Loto de la kermesse"
        Alors les cartons "A001, A002, B001" ne doivent plus avoir de joueurs associés
