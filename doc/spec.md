# Cahier des charges – Application de gestion de loto quine (traditionnel)

## 1. Contexte

Cette application est un **outil d’assistance au loto quine traditionnel en salle**, avec cartons papier, joueurs physiquement présents et animateur humain.

Elle ne remplace pas le jeu réel :

* le tirage reste manuel,
* la validation des gagnants reste humaine,
* l’application garantit **la cohérence, la traçabilité et l’affichage public**.

---

## 2. Objectifs

* Assister l’animateur dans la gestion du tirage.
* Afficher une grille de tirage claire (admin + public).
* Détecter automatiquement les **gagnants potentiels**.
* Gérer des **gagnants système** et des **gagnants salle (offline)**.
* Enchaîner plusieurs parties **sans remise à zéro des tirages**.
* Geler une partie dès qu’un gain est détecté.
* Bloquer un carton gagnant jusqu’au prochain reset (démarque).
* Garantir un affichage public fiable et synchronisé.

---

## 3. Terminologie officielle loto

| Terme            | Définition                                   |
| ---------------- | -------------------------------------------- |
| **Quine**        | 1 ligne complète (5 numéros)                 |
| **Double quine** | 2 lignes complètes (10 numéros)              |
| **Carton plein** | 3 lignes complètes (15 numéros)              |
| **Démarque**     | Réinitialisation manuelle du tirage (reset)  |
| **Gel du gain**  | Blocage de la partie au moment exact du gain |

---

## 4. Utilisateurs

### 4.1 Administrateur (animateur)

* Gère l’événement et les parties.
* Effectue le tirage.
* Valide les gagnants.
* Gère les transitions et la démarque.

### 4.2 Public / joueurs

* Accès en lecture seule.
* Visualisation du tirage, de la partie et des gagnants.

---

## 5. Modèle de données

### 5.1 Événement (Event)

* id
* name
* date
* relations :

    * games (OneToMany)
    * players (OneToMany)
    * cards (OneToMany)

---

### 5.2 Partie (Game)

Représente **un lot** avec une règle précise.

* id
* event (ManyToOne)
* position (ordre d’exécution)
* rule (enum `GameRule`)

    * QUINE
    * DOUBLE_QUINE
    * FULL_CARD
* prize (texte libre)
* status (enum `GameStatus`)

    * PENDING
    * RUNNING
    * FINISHED
* isFrozen (bool)
* freezeOrderIndex (int|null)

Relations :

* draws (OneToMany)
* winners (OneToMany)

---

### 5.3 Joueur (Player)

* id
* name
* email (nullable)
* phone (nullable)
* notes (nullable)
* event (ManyToOne)
* cards (OneToMany)

---

### 5.4 Carton (Card)

* id
* reference (numéro imprimé)
* grid (JSON – 3 lignes × 5 numéros)
* event (ManyToOne, nullable)
* player (ManyToOne, nullable)

Blocage :

* isBlocked (bool)
* blockedAt (DateTimeImmutable|null)
* blockedReason (enum : WINNER)

Un carton bloqué **ne peut plus gagner** tant qu’une démarque n’a pas été effectuée.

---

### 5.5 Tirage (Draw)

* id
* game (ManyToOne)
* number (1–90)
* orderIndex (ordre du tirage)
* createdAt

Contrainte unique : `(game_id, number)`

---

### 5.6 Gagnant (Winner)

* id
* game (ManyToOne)
* card (ManyToOne, nullable)
* source (enum `WinnerSource`)

    * SYSTEM
    * OFFLINE
* reference (texte libre)
* createdAt
* winningOrderIndex (index du tirage déclencheur)

---

## 6. Règles de tirage

* Le tirage est **manuel** (cochage/décochage).
* Autorisé uniquement si :

    * partie RUNNING
    * partie non gelée (`isFrozen = false`)
* Chaque numéro ne peut être tiré qu’une seule fois par partie.

---

## 7. Détection des gagnants

### 7.1 Règles appliquées

Selon la règle de la partie :

#### Quine

* Au moins **1 ligne complète**.

#### Double quine

* Au moins **2 lignes complètes**.

#### Carton plein

* **3 lignes complètes**.

---

### 7.2 Détection automatique

Après chaque tirage :

* le système calcule les gagnants potentiels,
* **si au moins un gagnant est détecté** :

    * la partie est **gelée immédiatement**,
    * `freezeOrderIndex` est enregistré,
    * aucun nouveau tirage n’est autorisé.

---

## 8. Gel du gain (règle fondamentale)

Le gel du gain intervient :

* automatiquement si le système détecte un gagnant,
* manuellement si l’admin valide un gagnant salle (offline).

Effets :

* arrêt du tirage,
* la liste des gagnants potentiels est figée **à l’instant du gain**,
* seuls les cartons gagnants **au même numéro** peuvent être validés.

Gestion des égalités :

* plusieurs gagnants possibles si complétés sur le même tirage.

---

## 9. Validation des gagnants

### 9.1 Gagnant système

* sélection dans les gagnants potentiels,
* validation manuelle obligatoire,
* contrôles :

    * carton non bloqué,
    * règle respectée à `freezeOrderIndex`,
    * absence de doublon.

Effets :

* création d’un `Winner`,
* **blocage du carton** (`isBlocked = true`).

---

### 9.2 Gagnant salle (offline)

* saisie manuelle (référence libre),
* carton associé optionnel,
* **gèle immédiatement la partie** si ce n’est pas déjà fait,
* si carton associé :

    * il est bloqué comme un gagnant système.

---

## 10. Blocage des cartons

* Un carton bloqué :

    * n’apparaît plus dans les gagnants potentiels,
    * ne peut pas gagner d’autres lots.
* Le déblocage ne peut se faire que par :

    * une **démarque**,
    * ou une action globale de reset.

---

## 11. Enchaînement des parties (Option A)

### 11.1 Principe

* Les tirages sont **conservés** entre les parties.
* Exemple :

    * Partie 1 : Quine
    * Partie 2 : Double quine
    * Partie 3 : Carton plein
* La grille reste allumée.

---

### 11.2 Transition vers la partie suivante

* Partie courante → FINISHED
* Partie suivante → RUNNING
* Tirages conservés
* Nouvelle règle et nouveau lot affichés

---

## 12. Démarque (reset)

### 12.1 Démarque d’une partie

* suppression de tous les tirages,
* déblocage des cartons gagnants,
* suppression des gagnants de la partie,
* partie repasse en état RUNNING.

### 12.2 Actions globales événement

* reset de tous les tirages,
* reset de tous les gagnants,
* désassociation des joueurs/cartons.

---

## 13. Affichage public

* Grille 1–90 en temps réel,
* Dernier numéro mis en évidence,
* Partie en cours (règle, lot, statut),
* Gagnants validés affichés,
* Grille **conservée entre les parties** (Option A).

---

## 14. Sécurité et traçabilité

* Validation manuelle obligatoire des gains,
* Horodatage de tous les événements critiques,
* Logs des actions admin,
* Protection CSRF complète.

---

## 15. Résumé métier

✔ Respect strict du loto quine traditionnel
✔ Gel immédiat au gain
✔ Égalités gérées proprement
✔ Cartons gagnants bloqués
✔ Tirage continu entre parties
✔ Contrôle humain permanent
