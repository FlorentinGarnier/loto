# language: fr
Fonctionnalité: Jeu de données de recette
    Afin de tester l'application sur le staging sans tout ressaisir
    En tant que testeur
    Je veux retrouver à chaque chargement la même soirée de loto en cours

    Scénario: Le jeu de données décrit une soirée en cours
        Quand je charge le jeu de données de recette
        Alors l'événement le plus récent doit être "Grand loto du comité des fêtes"
        Et l'événement le plus récent doit compter 12 parties et 40 joueurs
        Et les 3 premières parties de la soirée doivent être terminées avec un gagnant validé
        Et la partie d'ordre 4 de la soirée doit être en cours et non gelée
        Et 41 cartons doivent rester libres

    Scénario: Le numéro annoncé fait gagner le carton annoncé
        Étant donné que je charge le jeu de données de recette
        Quand je tire le numéro gagnant annoncé par le jeu de données
        Alors la partie en cours de la soirée doit être gelée
        Et les cartons annoncés par le jeu de données doivent être les gagnants potentiels

    Scénario: Deux chargements donnent les mêmes données
        Étant donné que je charge le jeu de données de recette
        Quand je recharge le jeu de données de recette
        Alors les cartons, les joueurs et les tirages doivent être identiques au premier chargement

    Scénario: La commande est refusée sans autorisation
        Étant donné qu'un événement "Loto de la kermesse" existe
        Quand je lance la commande du jeu de données de recette sans autorisation
        Alors la commande du jeu de données doit être refusée
        Et l'événement "Loto de la kermesse" doit exister

    Scénario: La commande autorisée remplace les données existantes
        Étant donné qu'un événement "Loto de la kermesse" existe
        Quand je lance la commande du jeu de données de recette avec autorisation
        Alors la commande du jeu de données doit réussir
        Et l'événement le plus récent doit être "Grand loto du comité des fêtes"
        Et il doit y avoir 2 événements
