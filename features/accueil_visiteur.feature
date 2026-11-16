# language: fr
Fonctionnalité: Accueil d'un visiteur
    Afin de suivre le tirage depuis mon téléphone
    En tant que joueur dans la salle
    Je veux trouver le tirage en direct dès l'adresse du site

    Contexte:
        Étant donné qu'un événement "Loto de la kermesse" existe

    Scénario: L'accueil propose de suivre le tirage sans se connecter
        Quand j'ouvre la page "/"
        Alors la page doit contenir "Loto de la kermesse"
        Et la page doit proposer le lien "Suivre le tirage en direct" vers "/public"
        Et la page doit proposer le lien "Espace organisateur" vers "/login"

    Scénario: La page de connexion ramène au tirage en direct
        Quand j'ouvre la page "/login"
        Alors la page doit proposer le lien "Suivre le tirage en direct" vers "/public"

    Scénario: Le tirage s'ouvre sans connexion
        Quand j'ouvre la page "/public"
        Alors l'écran de salle doit contenir la grille des 90 numéros
