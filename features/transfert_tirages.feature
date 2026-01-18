# language: fr
Fonctionnalité: Transfert des numéros tirés entre parties
    Afin de conserver l'historique des tirages lors des transitions
    En tant qu'administrateur
    Je veux que les numéros tirés soient automatiquement transférés d'une partie à l'autre

    Contexte:
        Étant donné que je suis connecté en tant qu'administrateur
        Et qu'un événement "Loto de la kermesse" existe
        Et que les parties suivantes sont définies pour l'événement "Loto de la kermesse":
            | ordre | règle        | lot            |
            | 1     | QUINE        | Machine à café |
            | 2     | DOUBLE_QUINE | Bon d'achat    |
            | 3     | FULL_CARD    | Voiture        |

    Scénario: Transférer les tirages lors de la validation d'un gagnant système
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et qu'un carton "A001" existe avec une ligne complète sur les numéros "5,15,23,45,67"
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Et que le carton "A001" a été détecté comme gagnant potentiel
        Et que la partie d'ordre 1 est gelée
        Quand je valide le carton "A001" comme gagnant et passe à la partie suivante
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"
        Et la partie d'ordre 2 doit avoir 5 numéros tirés
        Et les numéros "5,15,23,45,67" doivent être marqués comme tirés dans la partie d'ordre 2

    Scénario: Transférer les tirages lors de l'ajout d'un gagnant offline
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "8,12,23,34,56" ont été tirés pour la partie d'ordre 1
        Et qu'un carton "B001" existe
        Quand j'ajoute manuellement le carton "B001" comme gagnant offline et passe à la partie suivante
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"
        Et la partie d'ordre 2 doit avoir 5 numéros tirés
        Et les numéros "8,12,23,34,56" doivent être marqués comme tirés dans la partie d'ordre 2

    Scénario: Transférer les tirages lors du passage manuel à la partie suivante
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "2,18,29,43,67,89" ont été tirés pour la partie d'ordre 1
        Quand je termine la partie d'ordre 1 et passe à la suivante en conservant les tirages
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"
        Et la partie d'ordre 2 doit avoir 6 numéros tirés
        Et les numéros "2,18,29,43,67,89" doivent être marqués comme tirés dans la partie d'ordre 2

    Scénario: Transférer les tirages avec plusieurs transitions successives
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23" ont été tirés pour la partie d'ordre 1
        Quand je termine la partie d'ordre 1 et passe à la suivante en conservant les tirages
        Alors la partie d'ordre 2 doit avoir 3 numéros tirés
        Et les numéros "5,12,23" doivent être marqués comme tirés dans la partie d'ordre 2
        Étant donné que je tire les numéros "45,67" pour la partie d'ordre 2
        Et qu'un carton "C001" avec deux lignes complètes est détecté comme gagnant
        Quand je valide le carton "C001" et passe à la partie suivante
        Alors la partie d'ordre 3 doit être en statut "RUNNING"
        Et la partie d'ordre 3 doit avoir 5 numéros tirés
        Et les numéros "5,12,23,45,67" doivent être marqués comme tirés dans la partie d'ordre 3

    Scénario: Effacer tous les tirages en démarquant avant de passer à la partie suivante
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand je démarque tous les numéros de la partie d'ordre 1
        Et que je termine la partie d'ordre 1 et passe à la suivante
        Alors la partie d'ordre 1 doit être en statut "FINISHED"
        Et la partie d'ordre 2 doit être en statut "RUNNING"
        Et la partie d'ordre 2 ne doit pas avoir de numéros tirés

    Scénario: Vérifier l'ordre des tirages après transfert
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros suivants ont été tirés dans cet ordre pour la partie d'ordre 1:
            | numéro | ordre |
            | 5      | 1     |
            | 12     | 2     |
            | 23     | 3     |
            | 45     | 4     |
        Quand je termine la partie d'ordre 1 et passe à la suivante en conservant les tirages
        Alors la partie d'ordre 2 doit avoir les numéros tirés dans l'ordre suivant:
            | numéro | ordre |
            | 5      | 1     |
            | 12     | 2     |
            | 23     | 3     |
            | 45     | 4     |

    Scénario: Transférer les tirages après dégel et refus du gagnant
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,15,23,45,67" ont été tirés pour la partie d'ordre 1
        Et qu'un carton "D001" a été détecté comme gagnant potentiel
        Et que la partie d'ordre 1 est gelée
        Quand je refuse le gagnant et dégèle la partie d'ordre 1
        Et que je tire les numéros "8,12" pour la partie d'ordre 1
        Et qu'un autre carton "D002" est détecté comme gagnant
        Et que je valide le carton "D002" et passe à la partie suivante
        Alors la partie d'ordre 2 doit avoir 7 numéros tirés
        Et les numéros "5,15,23,45,67,8,12" doivent être marqués comme tirés dans la partie d'ordre 2

    Scénario: Transférer les tirages vers la dernière partie
        Étant donné que la partie d'ordre 2 est en statut "RUNNING"
        Et que les numéros "1,2,3,4,5,6,7,8,9,10" ont été tirés pour la partie d'ordre 2
        Et qu'un carton gagnant est validé pour la partie d'ordre 2
        Quand je passe à la partie d'ordre 3 en conservant les tirages
        Alors la partie d'ordre 3 doit être en statut "RUNNING"
        Et la partie d'ordre 3 doit avoir 10 numéros tirés
        Et les numéros "1,2,3,4,5,6,7,8,9,10" doivent être marqués comme tirés dans la partie d'ordre 3

    Scénario: Empêcher le transfert de tirages invalides (numéros hors limites)
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23" ont été tirés pour la partie d'ordre 1
        Quand je termine la partie d'ordre 1 et passe à la suivante en conservant les tirages
        Alors seuls les numéros valides (entre 1 et 90) doivent être transférés à la partie d'ordre 2

    Scénario: Transférer les tirages sans doublon
        Étant donné que la partie d'ordre 1 est en statut "RUNNING"
        Et que les numéros "5,12,23,45,67" ont été tirés pour la partie d'ordre 1
        Quand je termine la partie d'ordre 1 et passe à la suivante en conservant les tirages
        Et que la partie d'ordre 2 a déjà le numéro 23 tiré (cas improbable)
        Alors la partie d'ordre 2 doit avoir exactement 5 numéros tirés sans doublon
        Et les numéros "5,12,23,45,67" doivent être marqués comme tirés dans la partie d'ordre 2
