# Cahier des charges – Loto Quine en SaaS

> Version consolidée du 15 novembre 2026.
> Ce document remplace le cahier des charges initial (application mono-association). Il décrit d'abord le produit tel qu'il existe dans le code, puis la cible SaaS, puis le chemin pour y aller. Le volet économique est dans [business-plan.md](business-plan.md).

## Sommaire

1. [Vision et périmètre](#1-vision-et-périmètre)
2. [Cadre réglementaire](#2-cadre-réglementaire)
3. [Utilisateurs](#3-utilisateurs)
4. [Socle métier existant](#4-socle-métier-existant)
5. [Cible SaaS : exigences fonctionnelles](#5-cible-saas--exigences-fonctionnelles)
6. [Cible SaaS : exigences techniques](#6-cible-saas--exigences-techniques)
7. [Écarts entre l'existant et la cible](#7-écarts-entre-lexistant-et-la-cible)
8. [Lotissement](#8-lotissement)
9. [Hors périmètre](#9-hors-périmètre)
10. [Critères d'acceptation](#10-critères-dacceptation)

---

## 1. Vision et périmètre

### 1.1 Ce que fait le produit

L'application est un **outil d'assistance au loto quine traditionnel en salle** : cartons papier, joueurs présents, animateur humain. Elle ne remplace pas le jeu réel.

* Le tirage reste manuel : l'animateur coche les numéros sortis du boulier.
* La validation des gagnants reste humaine.
* L'application garantit la cohérence, la traçabilité et l'affichage public.

### 1.2 Ce qui change avec le SaaS

Aujourd'hui, une instance sert une seule association, avec un seul compte administrateur et un seul événement affiché. La cible est un **service en ligne multi-organisations** : chaque association ouvre un compte, prépare ses lotos, les anime depuis un navigateur, et paie selon son usage.

### 1.3 Proposition de valeur

Les logiciels de loto existants se limitent presque tous à l'affichage du tirage. Le produit se distingue sur quatre points, déjà présents dans le code :

* **Détection automatique des cartons gagnants** parmi tous les cartons vendus, dès le numéro qui fait gagner.
* **Gel du gain** : la partie se bloque à l'instant exact du gain, ce qui tranche les égalités et les contestations.
* **Registre joueurs ↔ cartons** : on sait à qui appartient le carton gagnant et comment le joindre.
* **Traçabilité** : gagnants horodatés, export CSV pour la remise des lots et le bilan.

S'y ajoute, en SaaS, l'absence d'installation : un navigateur et une connexion suffisent.

---

## 2. Cadre réglementaire

Le produit doit rester un outil d'animation et ne jamais devenir un opérateur de jeu. Le loto traditionnel est une exception à l'interdiction des loteries (article L322-4 du code de la sécurité intérieure). Les conditions à respecter par l'organisateur, et que le produit ne doit pas aider à contourner :

| Condition | Conséquence pour le produit |
| --- | --- |
| Cercle restreint, but social, culturel, scientifique, éducatif, sportif ou d'animation sociale | Les conditions d'utilisation réservent le service à ce cadre. |
| Mises de faible valeur, inférieures à 20 € | Si un prix de carton est saisi, l'application avertit au-delà de ce seuil. |
| Lots ni en argent ni remboursables | Le champ « lot » est un texte libre décrivant un bien ou un bon d'achat ; l'aide en ligne rappelle la règle. |
| Jeu en présentiel | Pas de participation à distance, pas de tirage diffusé pour des joueurs hors salle. |

Points à faire valider par un juriste avant ouverture commerciale :

* la version en vigueur des textes (un plafond de valeur des lots a existé puis a été abrogé en 2021) ;
* la vente de cartons en ligne en amont de l'événement (évoquée au [lot 4](#8-lotissement)) : des plateformes la proposent, mais sa compatibilité avec le cadre du loto traditionnel n'est pas confirmée par une source officielle ;
* la responsabilité de l'éditeur si un client organise un loto hors cadre.

Le produit traite par ailleurs des données personnelles de joueurs (nom, téléphone, e-mail) : voir [§6.4](#64-données-personnelles-rgpd).

---

## 3. Utilisateurs

| Rôle | Existant | Cible SaaS |
| --- | --- | --- |
| **Propriétaire d'organisation** | — | Crée le compte de l'association, gère l'abonnement et invite des membres. |
| **Animateur** | Compte `admin` unique | Prépare l'événement, effectue le tirage, valide les gagnants, gère la démarque. |
| **Assistant de salle** | — | Enregistre les joueurs et attribue les cartons à l'entrée ; ne touche pas au tirage. |
| **Public / joueurs** | Lecture seule sur `/public` | Lecture seule sur l'écran de l'événement, sans compte. |
| **Exploitant de la plateforme** | — | Supervise les organisations, les abonnements et le support. |

---

## 4. Socle métier existant

Cette partie décrit le comportement réellement implémenté. Elle est couverte par les specs PHPSpec (`spec/`) et les scénarios Behat (`features/`), et doit être conservée à l'identique dans la version SaaS.

### 4.1 Terminologie

| Terme | Définition |
| --- | --- |
| **Quine** | 1 ligne complète (5 numéros) |
| **Double quine** | 2 lignes complètes (10 numéros) |
| **Carton plein** | 3 lignes complètes (15 numéros) |
| **Démarque** | Remise à zéro manuelle du tirage d'une partie |
| **Gel du gain** | Blocage de la partie au moment exact du gain |
| **Gagnant système** | Carton détecté par l'application puis validé par l'animateur |
| **Gagnant salle** | Gagnant saisi à la main (carton non enregistré ou partie « salle uniquement ») |

### 4.2 Modèle de données

**Événement (`Event`)** : nom, date ; contient des parties et des joueurs.

**Partie (`Game`)** : un lot et une règle.

* `position` (ordre de passage, réordonnable par glisser-déposer)
* `rule` : `QUINE`, `DOUBLE_QUINE`, `FULL_CARD`
* `prize` (texte libre)
* `status` : `PENDING`, `RUNNING`, `FINISHED`
* `isFrozen`, `freezeOrderIndex`
* `hallOnly` : mode « salle uniquement », sans détection automatique
* tirages et gagnants

**Joueur (`Player`)** : nom, e-mail, téléphone, notes ; rattaché à un événement ; possède des cartons.

**Carton (`Card`)** :

* `reference` (numéro imprimé, unique)
* `grid` (JSON : 3 lignes × 5 numéros)
* `player` (optionnel) ; l'événement du carton est celui de son joueur
* blocage : `isBlocked`, `blockedAt`, `blockedReason` (`WINNER`, `WINNER_VALIDATED`, `WINNER_OFFLINE`)

**Tirage (`Draw`)** : numéro (1–90), `orderIndex`, horodatage ; un numéro ne sort qu'une fois par partie.

**Gagnant (`Winner`)** : partie, carton (optionnel), `source` (`SYSTEM` ou `OFFLINE`), référence libre, `winningOrderIndex`, horodatage.

### 4.3 Tirage

* Le tirage est manuel : un clic coche un numéro, un second le décoche.
* Il n'est autorisé que si la partie est `RUNNING` et non gelée.
* Les numéros valides vont de 1 à 90.
* Décocher un numéro renumérote les tirages suivants sans trou.

### 4.4 Détection des gagnants

Après chaque numéro coché, l'application calcule les gagnants potentiels parmi les cartons de l'événement.

* Quine : au moins 1 ligne complète. Double quine : au moins 2. Carton plein : 3.
* Une ligne ne compte que si elle contient exactement 5 numéros.
* Les cartons bloqués sont ignorés.
* Si la partie est gelée, seuls les tirages jusqu'à `freezeOrderIndex` comptent.
* En mode « salle uniquement », aucune détection n'a lieu.

### 4.5 Gel du gain

Le gel intervient automatiquement dès qu'un gagnant potentiel est détecté, ou lorsque l'animateur saisit un gagnant salle.

* Le tirage s'arrête.
* La liste des gagnants potentiels est figée à l'instant du gain.
* Plusieurs cartons complétés sur le même numéro sont tous gagnants (égalité).
* L'animateur peut dégeler la partie à la main, par exemple après une fausse annonce.

### 4.6 Validation des gagnants

**Gagnant système** : choisi parmi les gagnants potentiels, validé à la main. Contrôles : partie gelée, carton non bloqué, carton du même événement, règle respectée au moment du gel, pas de doublon. Effet : création du gagnant et blocage du carton.

**Gagnant salle** : référence libre, carton associé facultatif. Gèle la partie si elle ne l'est pas. Si un carton est associé, il est bloqué. Un carton déjà bloqué est refusé sans geler la partie.

Un carton bloqué n'apparaît plus dans les gagnants potentiels et ne peut plus gagner jusqu'à la démarque ou un reset global.

### 4.7 Enchaînement des parties

* Une seule partie est `RUNNING` à la fois.
* Passer à la partie suivante termine la partie courante, démarre la suivante et **lui transfère les numéros déjà tirés** : la grille reste allumée (quine, puis double quine, puis carton plein sur les mêmes numéros).

### 4.8 Démarque et resets

* **Démarque d'une partie** (partie en cours uniquement) : supprime ses tirages, la dégèle, supprime ses gagnants et débloque leurs cartons.
* **Resets de l'événement** : tous les tirages (les parties sont dégelées), tous les gagnants, ou désassociation de tous les joueurs.
* Les actions destructrices demandent une confirmation.

### 4.9 Préparation de l'événement

* Gestion des événements, parties, joueurs et cartons depuis l'administration.
* Attribution des cartons à un joueur par recherche de référence.
* Import de cartons par fichier CSV (`cardRef,line1,line2,line3`), aujourd'hui en ligne de commande seulement.
* Jeu de données de démonstration en ligne de commande.

### 4.10 Affichage public

* Grille 1–90 mise à jour en temps réel (Mercure).
* Dernier numéro mis en évidence.
* Partie en cours : règle, lot, statut.
* Gagnants validés.

### 4.11 Export

Export CSV des gagnants d'un événement : partie, règle, lot, ordre de victoire, référence du carton, joueur, téléphone, e-mail, source, date.

### 4.12 Pile technique

Symfony 7.4, PHP ≥ 8.2, PostgreSQL 16, Mercure, Twig + Stimulus + Turbo (AssetMapper), Docker, déploiement par GitHub Actions. Tests : PHPSpec (unitaires) et Behat (fonctionnels).

---

## 5. Cible SaaS : exigences fonctionnelles

Priorités : **M** = indispensable à l'ouverture commerciale, **S** = à livrer ensuite, **C** = optionnel.

### 5.1 Organisations et comptes

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| ORG-1 | Une association crée son organisation en libre-service (nom, e-mail, mot de passe), avec vérification de l'e-mail. | M |
| ORG-2 | Toutes les données (événements, parties, joueurs, cartons, gagnants) appartiennent à une organisation et sont invisibles des autres. | M |
| ORG-3 | Connexion par e-mail et mot de passe, réinitialisation du mot de passe par e-mail. | M |
| ORG-4 | Le propriétaire invite des membres avec un rôle : animateur ou assistant de salle. | S |
| ORG-5 | Un utilisateur peut appartenir à plusieurs organisations (animateur intervenant pour plusieurs associations). | C |
| ORG-6 | Suppression du compte et de ses données à la demande du propriétaire. | M |

### 5.2 Événements

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| EVT-1 | Une organisation gère plusieurs événements ; l'animateur choisit celui qu'il anime (aujourd'hui : le dernier créé). | M |
| EVT-2 | Dupliquer un événement passé (parties, lots) pour préparer le suivant. | S |
| EVT-3 | Archiver un événement terminé : lecture seule, export toujours possible. | S |
| EVT-4 | Mode démonstration : un événement d'exemple prêt à jouer dès l'inscription. | M |

### 5.3 Cartons

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| CAR-1 | La référence d'un carton est unique **par organisation** (aujourd'hui : unique sur toute la base). | M |
| CAR-2 | Import CSV des cartons depuis l'interface, avec rapport d'erreurs ligne par ligne. | M |
| CAR-3 | Les cartons d'une organisation sont réutilisables d'un événement à l'autre. | M |
| CAR-4 | Contrôle de validité d'une grille à la saisie : 3 lignes de 5 numéros entre 1 et 90, sans doublon. | M |
| CAR-5 | Génération de planches de cartons en PDF à imprimer, pour les associations sans stock de cartons. | S |
| CAR-6 | Bibliothèque des séries de cartons du commerce, pour éviter la saisie. | C |

### 5.4 Joueurs

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| JOU-1 | Saisie rapide à l'entrée de la salle : nom, puis cartons scannés ou saisis à la suite. | M |
| JOU-2 | La saisie du joueur est facultative : un carton peut être mis en jeu sans joueur nominatif. | M |
| JOU-3 | Recherche d'un joueur par nom ou par référence de carton. | S |

### 5.5 Animation

Le comportement décrit au [§4](#4-socle-métier-existant) est repris sans changement, avec ces ajouts :

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| ANI-1 | Tolérance aux coupures réseau : l'écran d'animation signale la perte de connexion et se resynchronise sans perte de tirage. | M |
| ANI-2 | Historique des actions de l'animateur sur l'événement (tirages, gels, validations, démarques), horodaté. | S |
| ANI-3 | Annulation de la dernière validation de gagnant sans démarque complète. | S |
| ANI-4 | Raccourcis clavier pour le tirage. | C |

### 5.6 Affichage public

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| PUB-1 | Chaque événement a sa propre adresse d'affichage, non devinable. | M |
| PUB-2 | L'affichage ne montre jamais de données personnelles au-delà de ce que l'animateur choisit (référence du carton, nom du gagnant en option). | M |
| PUB-3 | Personnalisation : nom et logo de l'association. | S |
| PUB-4 | Écran d'attente entre deux parties : lot suivant, messages de l'association, sponsors. | S |
| PUB-5 | QR code menant à une vue « téléphone » du tirage pour les joueurs en salle. | C |

### 5.7 Abonnement et facturation

Les formules et les prix sont détaillés dans le [business plan](business-plan.md#5-offre-et-prix).

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| FAC-1 | Formule gratuite limitée, sans carte bancaire. | M |
| FAC-2 | Achat d'un pass pour un événement et d'un abonnement annuel, par carte bancaire via un prestataire de paiement. | M |
| FAC-3 | Les limites de la formule sont appliquées par l'application (nombre de cartons, export, personnalisation). | M |
| FAC-4 | Facture téléchargeable, au nom de l'association. | M |
| FAC-5 | Un événement en cours n'est jamais interrompu par une échéance d'abonnement. | M |
| FAC-6 | Paiement par virement ou mandat administratif pour les comités des fêtes et collectivités. | C |

### 5.8 Exploitation de la plateforme

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| EXP-1 | Console d'exploitation : liste des organisations, formule, dernière activité. | M |
| EXP-2 | Prise en main d'un compte client pour le support, tracée. | S |
| EXP-3 | Indicateurs : inscriptions, activation (premier événement animé), conversion, attrition. | S |

---

## 6. Cible SaaS : exigences techniques

### 6.1 Isolation des données

* Chaque entité métier porte une référence à son organisation.
* Le filtrage par organisation est appliqué de façon systématique (filtre Doctrine ou équivalent), pas contrôleur par contrôleur.
* Les accès par identifiant vérifient l'appartenance à l'organisation de l'utilisateur connecté.
* Des tests automatisés prouvent qu'une organisation ne peut ni lire ni modifier les données d'une autre.

### 6.2 Sécurité

* Comptes en base, mots de passe hachés, limitation des tentatives de connexion.
* Protection CSRF sur toutes les actions qui modifient des données, sans exception.
* Canaux temps réel non devinables et réservés aux abonnés autorisés pour l'écran d'animation.
* Aucun secret par défaut en production (clé applicative, clé Mercure, mot de passe de base).

### 6.3 Disponibilité et performance

* Un loto dure une soirée : une panne pendant l'événement est l'incident le plus grave. Objectif de disponibilité de 99,5 % sur les créneaux de soirée et de week-end.
* Cochage d'un numéro visible sur l'écran public en moins d'une seconde.
* Détection des gagnants en moins d'une seconde pour 5 000 cartons en jeu.
* Sauvegarde quotidienne de la base, restauration testée.
* Supervision et alerte en cas d'indisponibilité.

### 6.4 Données personnelles (RGPD)

* L'association est responsable du traitement des données de ses joueurs ; l'éditeur est sous-traitant. Un accord de sous-traitance est intégré aux conditions générales.
* Données minimales : seul le nom du joueur est utile au jeu ; téléphone et e-mail restent facultatifs.
* Durée de conservation : les données de joueurs d'un événement sont supprimées ou anonymisées après un délai configurable (12 mois par défaut).
* Export et suppression des données d'une organisation à la demande.
* Hébergement dans l'Union européenne.

### 6.5 Documents légaux

Mentions légales, conditions générales de vente et d'utilisation, politique de confidentialité, rappel du cadre légal du loto traditionnel.

### 6.6 Qualité

* Les specs PHPSpec et les scénarios Behat existants restent verts à chaque étape.
* Toute nouvelle règle métier est décrite par une spec avant d'être codée.

---

## 7. Écarts entre l'existant et la cible

| Sujet | Existant | À faire |
| --- | --- | --- |
| Comptes | Un utilisateur `admin` en mémoire, mot de passe en variable d'environnement | Utilisateurs et organisations en base, inscription, rôles |
| Cloisonnement | Aucun : une instance = une association | Rattachement de toutes les entités à une organisation |
| Événement courant | Le dernier événement créé, pour l'admin comme pour le public | Choix de l'événement animé ; adresse publique par événement |
| Référence de carton | Unique sur toute la base | Unique par organisation |
| Import de cartons | Ligne de commande | Interface web |
| Temps réel | Sujets Mercure construits sur l'identifiant de l'événement | Sujets non devinables, abonnements autorisés |
| CSRF | Jeton vérifié sur les formulaires, les suppressions et la validation d'un gagnant ; absent des actions d'animation (tirage, démarrage et enchaînement des parties, démarque, resets, dégel, réordonnancement, attribution de cartons) | Jeton obligatoire partout |
| Journal des actions | Annoncé dans l'ancien cahier des charges, absent du code | À créer (ANI-2) |
| Facturation | Aucune | Formules, paiement, factures |

---

## 8. Lotissement

| Lot | Contenu | Résultat |
| --- | --- | --- |
| **0 – Assainissement** | Corrections du tableau des écarts qui ne dépendent pas du SaaS (jeton CSRF sur les actions d'animation) | Socle fiable |
| **1 – Multi-organisation** | ORG-1 à ORG-3, ORG-6, EVT-1, CAR-1, CAR-3, PUB-1, PUB-2, §6.1, §6.2 | Plusieurs associations sur une même instance, en accès gratuit sur invitation |
| **2 – Ouverture commerciale** | EVT-4, CAR-2, CAR-4, JOU-1, JOU-2, ANI-1, FAC-1 à FAC-5, EXP-1, §6.3 à §6.5 | Inscription libre et paiement en ligne |
| **3 – Confort et fidélisation** | ORG-4, EVT-2, EVT-3, CAR-5, JOU-3, ANI-2, ANI-3, PUB-3, PUB-4, EXP-2, EXP-3 | Raisons de s'abonner à l'année |
| **4 – Extensions** | ORG-5, CAR-6, ANI-4, PUB-5, FAC-6, vente de cartons en amont (sous réserve juridique) | Nouvelles sources de revenu |

Le lot 1 doit être validé par deux ou trois associations pilotes sur de vrais lotos avant d'engager le lot 2.

---

## 9. Hors périmètre

* Tirage automatique ou aléatoire décidant du résultat : le boulier reste la source du tirage.
* Participation à distance, loto en ligne, diffusion vidéo.
* Lots en argent, encaissement des mises pour le compte de l'association.
* Bingo à 75 numéros et autres variantes de grille.
* Comptabilité de l'association, gestion des adhérents.

---

## 10. Critères d'acceptation

Le SaaS est prêt à ouvrir commercialement quand :

1. une association inconnue s'inscrit, importe ses cartons et anime une partie de démonstration en moins de 15 minutes, sans aide ;
2. deux organisations utilisent le service le même soir sans jamais voir les données l'une de l'autre, tests automatisés à l'appui ;
3. un événement réel de 300 joueurs et 1 500 cartons se déroule sans incident ni ralentissement ;
4. un paiement, sa facture et l'application des limites de formule fonctionnent de bout en bout ;
5. une sauvegarde a été restaurée avec succès ;
6. les documents légaux sont publiés et le cadre réglementaire a été relu par un juriste.
