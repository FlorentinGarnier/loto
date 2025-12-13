# Cahier des charges – Application de gestion de loto quine / bingo

## 1. Contexte

L’organisation de loto quine nécessite un contrôle rigoureux du tirage, des gagnants et de l’enchaînement des parties. L’objectif est de créer une application qui facilite la gestion administrative des tirages en salle, tout en permettant un affichage public clair du déroulé du jeu.

L’application répond à un fonctionnement traditionnel, avec des cartons papier, des joueurs présents physiquement, et des gagnants potentiels non enregistrés dans le système. Elle ne remplace pas le jeu physique, mais assiste l’animateur dans la gestion en temps réel.

---

## 2. Objectifs

* Assister l’animateur dans les tirages.
* Afficher une grille de tirage ergonomique pour l’admin.
* Calculer automatiquement les gagnants potentiels parmi les cartons enregistrés.
* Permettre à l’admin de déclarer manuellement des gagnants hors système.
* Gérer une séquence de parties (lignes, double ligne, carton plein) pré-configurées.
* Associer un lot à chaque partie.
* Ne pas automatiser la fin de partie ni la démarque : actions manuelles uniquement.
* Afficher une grille publique avec mise en évidence du dernier numéro.

---

## 3. Utilisateurs du système

### 3.1 Administrateur

* Gère les paramètres de l’événement.
* Dispose d’une interface de tirage.
* Contrôle l’enchaînement des parties.
* Déclare les gagnants.
* Démarque manuellement.

### 3.2 Public / joueurs

* Accès à un affichage en temps réel, sans interaction.
* Visualisation du dernier numéro tiré.

---

## 4. Modèle de données

### 4.1 Événement (Event)

* Identifiant
* Nom
* Date
* Liste des parties

### 4.2 Partie (Game)

* Identifiant
* Event_id
* Ordre
* Type de règle: LINE, DOUBLE_LINE, FULL_CARD
* Lot associé: texte
* Statut: PENDING, RUNNING, FINISHED

### 4.3 Joueur (Player)

* Identifiant
* Nom / prénom (ou pseudo)
* Coordonnées (optionnel : téléphone, email)
* Remarques (optionnel)

### 4.4 Carton (Card)

* Identifiant
* Event_id
* Numéro de référence (imprimé sur le carton)
* Grille (3 lignes de 5 numéros)
* Player_id (optionnel) : joueur à qui le carton a été vendu

> Un joueur peut posséder plusieurs cartons. Tous les cartons n’ont pas forcément un joueur associé (cartons non vendus ou non enregistrés).

### 4.5 Tirage (Draw)

* Identifiant
* Game_id
* Numéro tiré (1–90)
* Ordre
* Timestamp

### 4.6 Gagnant (Winner)

* Identifiant
* Game_id
* Card_id (optionnel)
* Source: SYSTEM, OFFLINE
* Référence libre (ex : numéro de carton ou description papier)
* Timestamp

---

## 5. Fonctionnalités côté administrateur

L’interface d’administration accessible via `/admin` permet de contrôler l’ensemble du logiciel. Elle s’ouvre par défaut sur un **Dashboard central** offrant une vue d’ensemble de l’événement en cours et des accès rapides aux modules clés.

### 5.1 Dashboard (Page d’accueil de l’admin)

Le dashboard doit fournir une vision claire de l’état du système et permettre au personnel d’accéder rapidement aux actions de gestion.

#### Informations affichées

* Nom de l’événement actif
* Date de l’événement
* Partie en cours (règle, lot, statut)
* Nombre total de parties, joueurs, cartons vendus, gagnants enregistrés
* Dernier numéro tiré et historiques récents (si partie en cours)

#### Actions rapides

* Bouton : **Accéder au contrôle du tirage**
* Bouton : **Passer à la partie suivante** (si applicable)
* Bouton : **Démarquer la partie en cours**
* Bouton : **Voir les gagnants**
* Bouton : **Créer un joueur**
* Bouton : **Enregistrer un carton**

#### État du système

* Alertes sur les données manquantes :

    * aucune partie active
    * aucun carton enregistré
    * aucun gagnant validé alors que des gagnants potentiels existent
* Indicateur de connexion Mercure (ok / hors ligne)

#### Tableau de bord visuel

* Graphique simple ou compteurs en tuiles :

    * nombre de tirages effectués
    * progression de la partie (ex : 37/90 numéros tirés)
    * nombre de gagnants potentiels détectés

---

### 5.2 Gestion des événements

...

L’interface d’administration accessible via `/admin` permet de contrôler l’ensemble du logiciel. Elle est structurée en plusieurs sections principales :

### 5.1 Dashboard

* Vue synthétique de l’événement en cours
* Indicateurs clés : nombre de parties, cartons vendus, joueurs, gagnants
* Accès rapide au contrôle du tirage

### 5.2 Gestion des événements

* Liste des événements
* Création et édition d’un événement (nom, date)
* Association des parties à l’événement

### 5.3 Gestion des parties (Games)

* Création, édition et suppression de parties pour un événement
* Configuration de chaque partie :

    * ordre d’enchaînement
    * type de règle (1 ligne, 2 lignes, carton plein)
    * lot associé (libellé du lot, ex. "Machine à café")
* Modification du lot d’une partie avant ou pendant l’événement (si nécessaire)
* Mise à jour manuelle du statut d’une partie : PENDING, RUNNING, FINISHED
* Possibilité de réordonner les parties (drag & drop ou formulaires)

### 5.4 Gestion des lots

Dans ce système, chaque partie possède un **lot unique** qui lui est directement associé.

* Gestion des lots via l’édition des parties
* Pour chaque partie, l’admin saisit ou modifie :

    * le libellé du lot (obligatoire)
    * une description optionnelle (détail du lot, valeur indicative)
* Visualisation en tableau des parties avec leurs lots pour l’événement courant

### 5.5 Gestion des cartons

* Liste filtrable par référence, joueur, statut (assigné / non assigné)
* Création d’un carton pour l’événement :

    * saisie de la référence (numéro imprimé)
    * saisie de la grille (3 lignes × 5 numéros)
* Enregistrement des cartons en amont de l’événement ou en cours de vente
* Association d’un carton à un joueur lors de l’achat :

    * sélection d’un joueur existant
    * ou création rapide d’un nouveau joueur puis association immédiate
* Possibilité de détacher ou réassigner un carton (en cas d’erreur)

### 5.6 Gestion des joueurs

* Liste filtrable par référence, joueur, statut
* Création d’un carton (référence, grille)
* Association d’un carton à un joueur lors de la vente

### 5.6 Gestion des joueurs

* Liste des joueurs avec contact et nombre de cartons
* Création / édition d’un joueur
* Association de plusieurs cartons à un joueur

### 5.7 Contrôle du tirage (cockpit principal)

Page dédiée au pilotage en temps réel d’une partie :

#### Affichages

* Partie en cours : règle, lot, statut
* Dernier numéro tiré et historique récent

#### Grille de tirage 1–90

* Cases à cocher/décocher
* Interaction en temps réel avec Mercure
* Mise à jour automatique des gagnants potentiels

#### Actions principales

* **Déclarer un gagnant en salle** (hors système)
* **Valider un gagnant système**
* **Passer à la partie suivante** (transition manuelle)
* **Démarquer** (réinitialisation des tirages de la partie courante)

#### Gagnants potentiels

* Liste de cartons remplissant les conditions de la règle
* Option de validation manuelle

#### Gagnants validés

* Liste officielle des gagnants (SYSTEM / OFFLINE)
* Lot implicite selon partie

### 5.8 Journal / Logs

* Historique des actions importantes : tirages, validations, transitions, démarques
* Filtres par événement / partie / type d’action

### 5.9 Paramètres

* Configuration Mercure (lecture seule ou édition)
* Réglages techniques optionnels

### 5.1 Grille de tirage

* Affichage d’une grille 1–90 sous forme de cases à cocher.
* Coche = enregistrement d’un tirage.
* Décoche = suppression d’un tirage.
* Mise à jour automatique des gagnants potentiels.

### 5.2 Informations affichées

* Partie en cours, règle, lot.
* Dernier numéro tiré.
* Liste des gagnants potentiels.

### 5.3 Actions administratives

* Déclarer un gagnant hors système.
* Passer à la partie suivante (ne démarque pas).
* Démarquer (réinitialisation manuelle des tirages de la partie).

### 5.4 Gestion des gagnants

* Stockage des gagnants validés.
* Association implicite au lot de la partie.

---

## 6. Fonctionnalités côté public

L’application propose un affichage public dédié, accessible depuis une URL publique, permettant aux joueurs en salle de suivre le déroulé du loto en temps réel.

### 6.1 Affichage de la grille publique

* Grille 1–90 affichée sous forme de tableau
* Numéros non tirés en style neutre
* Numéros tirés en style mis en évidence
* Dernier numéro tiré visuellement accentué
* Affichage automatique du dernier tirage grâce à Mercure

### 6.2 Informations complémentaires affichées

* Nom de l’événement
* Nom ou numéro de la partie en cours
* Type de règle en cours : 1 ligne, 2 lignes, carton plein
* Lot associé à la partie
* Liste des derniers numéros tirés (5 derniers maximum)

### 6.3 Mise à jour en temps réel

* Mise à jour automatique via Mercure sans rechargement
* Réception des nouveaux tirages dès qu’ils sont générés côté administrateur
* Changement automatique de partie lorsque l’admin effectue la transition

### 6.4 État de la partie

* Lorsque la partie se termine, affichage : "Partie terminée"
* Lors du passage à la prochaine partie, réinitialisation visuelle automatique de la grille

### 6.5 Mode plein écran

* Option d’affichage pleine page pour projection sur écran géant

### 6.6 Aucune interaction utilisateur

* Le public ne peut pas cliquer ni interagir
* Aucun formulaire ni bouton sur cet écran

---

## 7. Enchaînement des parties

* Les parties sont configurées avant l’événement.
* L’admin lance la première partie.
* Au clic “Passer à la partie suivante” :

    * Partie courante = FINISHED
    * Partie suivante = RUNNING
* La démarque ne se fait que si l’admin clique sur le bouton dédié.

---

## 8. Règles de détection de gagnants

En fonction de la règle associée à la partie :

* LINE : au moins une ligne complète
* DOUBLE_LINE : au moins deux lignes complètes
* FULL_CARD : trois lignes complètes

La détection ne valide pas automatiquement :

* Affichage en tant que “gagnant potentiel” uniquement.
* Validation manuelle.

---

## 9. Contraintes techniques et UX

### 9.1 Technologies

* Framework : Symfony (dernière version stable)

* Architecture : application web avec back-office administrateur et affichage public en temps réel

* Temps réel : **Symfony Mercure** pour la diffusion des événements en temps réel côté administrateur et côté public

* Stockage : base de données relationnelle (ex : MySQL/PostgreSQL) avec un schéma cohérent et normalisé, aligné sur le modèle de données décrit (Event, Game, Player, Card, Draw, Winner)

* API interne (HTTP + événements Mercure) pour la gestion des tirages, parties, joueurs et cartons

* Temps réel entre interface admin et public.

* Tolérance aux erreurs (cochage/décoche).

* Pas de suppression automatique des données.

* Interface simple, lisible, accessible.

---

## 10. Sécurité et intégrité

* Les données doivent persister entre sessions.
* Audit minimal: horodatage des tirages et gagnants.
* Pas d’authentification publique.

---

## 11. Évolutions possibles

* Multi-écran pour le public.
* Statistiques.
* Système de QR codes pour cartons enregistrés.
* Enregistrement / relecture de parties.

---

## 12. Résumé

Le système est un outil d’assistance au loto quine:

* Tirage manuel assisté.
* Calcul automatique.
* Contrôle humain final.
* Affichage public en temps réel.

Il respecte le fonctionnement traditionnel tout en modernisant l’organisation et le suivi des parties.
