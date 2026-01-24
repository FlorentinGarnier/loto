# language: fr
Fonctionnalité: Gestion des événements et des parties
    Afin d'organiser une soirée de loto
    En tant qu'administrateur
    Je veux créer un événement et définir les parties (règles + lots) à l'avance

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur

    Scénario: Créer un nouvel événement de loto
        Quand je crée un événement de loto nommé "Loto de la kermesse" pour le "2025-06-21"
        Alors l'événement "Loto de la kermesse" doit exister
        Et la date de l'événement "Loto de la kermesse" doit être "2025-06-21"

    Scénario: Définir une séquence de parties pour un événement
        Étant donné qu'un événement "Loto de la kermesse" existe
        Quand je définis les parties suivantes pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot             |
            | 1     | QUINE        | Machine à café  |
            | 2     | DOUBLE_QUINE | Bon d'achat     |
            | 3     | FULL_CARD    | Voiture         |
        Alors l'événement "Loto de la kermesse" doit avoir 3 parties
        Et la partie d'ordre 1 doit avoir la règle "QUINE" et le lot "Machine à café"
        Et la partie d'ordre 2 doit avoir la règle "DOUBLE_QUINE" et le lot "Bon d'achat"
        Et la partie d'ordre 3 doit avoir la règle "FULL_CARD" et le lot "Voiture"

    Scénario: Lancer la première partie d'un événement
        Étant donné qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot             |
            | 1     | QUINE        | Machine à café  |
            | 2     | DOUBLE_QUINE | Bon d'achat     |
            | 3     | FULL_CARD    | Voiture         |
        Quand je démarre la première partie de l'événement "Loto de la kermesse"
        Alors la partie d'ordre 1 doit être en statut "RUNNING"
        Et la partie d'ordre 2 doit être en statut "PENDING"
        Et la partie d'ordre 3 doit être en statut "PENDING"

    Scénario: Passer à la partie suivante
        Étant donné qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot             |
            | 1     | QUINE        | Machine à café  |
            | 2     | DOUBLE_QUINE | Bon d'achat     |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je termine la partie d'ordre 1 et passe à la suivante
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"

    Scénario: Terminer une partie
        Étant donné qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle    | lot            |
            | 1     | QUINE    | Machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Quand je termine la partie d'ordre 1
        Alors la partie d'ordre 1 doit être en statut "FINISHED"

    Scénario:
        Etant donné qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle | lot |
            | 1     | QUINE | machine à café |
        Et que la partie d'ordre 1 est en statut "RUNNING"
        Et que la partie d'ordre 1 est gelée
        Quand je dégèle la partie
        Alors la partie d'ordre 1 ne doit pas être gelée
