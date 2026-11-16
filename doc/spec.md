# Cahier des charges – Quinou, loto quine en SaaS

> Version consolidée du 15 novembre 2026, révisée le même jour pour l'architecture retenue : une instance par client ([§6.1](#61-architecture-et-isolation-des-données)).
> Ce document remplace le cahier des charges initial (application mono-association). Il décrit d'abord le produit tel qu'il existe dans le code, puis la cible SaaS, puis le chemin pour y aller. Le volet économique est dans [business-plan.md](business-plan.md), l'identité dans [charte-graphique.md](charte-graphique.md).

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

Aujourd'hui, une instance sert une seule association, avec un seul compte administrateur et un seul événement affiché. La cible est un **service en ligne** où chaque association ouvre un compte, prépare ses lotos, les anime depuis un navigateur, et paie selon son usage.

L'application reste mono-association. Le SaaS est obtenu en donnant à chaque client **sa propre instance**, à sa propre adresse (`<client>.quinou.fr`), créée et pilotée par un second logiciel, le **portail** (site, espace client, facturation, gestion des clients). Le détail est au [§6.1](#61-architecture-et-isolation-des-données).

### 1.3 Proposition de valeur

Les logiciels de loto existants se limitent presque tous à l'affichage du tirage. Le produit se distingue sur quatre points, déjà présents dans le code :

* **Détection automatique des cartons gagnants** parmi tous les cartons vendus, dès le numéro qui fait gagner.
* **Gel du gain** : la partie se bloque à l'instant exact du gain, ce qui tranche les égalités et les contestations.
* **Registre joueurs ↔ cartons** : on sait à qui appartient le carton gagnant et comment le joindre.
* **Traçabilité** : gagnants horodatés, export CSV pour la remise des lots et le bilan.

S'y ajoute, en SaaS, l'absence d'installation : un navigateur et une connexion suffisent.

Le message commercial tient en une phrase : l'organisateur enregistre ses parties, ses lots et ses propres cartons, et le jour J tout se déroule sur une mer d'huile. Le produit accepte donc tout carton de loto traditionnel, quelle que soit sa marque, à une seule condition : une référence et une grille propres à chaque carton (CAR-1, CAR-4, CAR-7).

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
* la vente de cartons en ligne en amont de l'événement (évoquée au [lot 5](#8-lotissement)) : des plateformes la proposent, mais sa compatibilité avec le cadre du loto traditionnel n'est pas confirmée par une source officielle ;
* la responsabilité de l'éditeur si un client organise un loto hors cadre ;
* les règles de vente en ligne applicables à une association cliente (rétractation, mentions du récapitulatif de commande) ;
* les cartons vendus à des personnes qui ne viennent pas et joués pour elles en salle : c'est le positionnement de la [charte](charte-graphique.md), et il est à confronter à la condition de jeu en présentiel du tableau ci-dessus.

Le produit traite par ailleurs des données personnelles de joueurs (nom, téléphone, e-mail) : voir [§6.4](#64-données-personnelles-rgpd).

---

## 3. Utilisateurs

| Rôle | Existant | Cible SaaS |
| --- | --- | --- |
| **Propriétaire d'organisation** | — | Crée le compte de l'association sur le portail, gère l'abonnement et invite des membres dans son instance. |
| **Animateur** | Compte `admin` unique | Prépare l'événement, effectue le tirage, valide les gagnants, gère la démarque. |
| **Assistant de salle** | — | Enregistre les joueurs et attribue les cartons à l'entrée ; ne touche pas au tirage. |
| **Public / joueurs** | Lecture seule sur `/public` | Lecture seule sur l'écran de l'événement, sans compte. |
| **Exploitant de la plateforme** | — | Crée et supervise les instances, les abonnements et le support, depuis le portail. |

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
* Toute action qui modifie des données exige un jeton CSRF : formulaires, suppressions, validation des gagnants et actions d'animation (tirage, enchaînement des parties, démarque, resets, dégel, réordonnancement, attribution de cartons).

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

Symfony 8.1, PHP ≥ 8.4, PostgreSQL 16, Mercure, Twig + Stimulus + Turbo (AssetMapper), Docker, déploiement par GitHub Actions. Tests : PHPSpec (unitaires) et Behat (fonctionnels).

---

## 5. Cible SaaS : exigences fonctionnelles

Priorités : **M** = indispensable à l'ouverture commerciale, **S** = à livrer ensuite, **C** = optionnel.

La colonne « Où » dit quel logiciel porte l'exigence : **A** pour l'application (une instance par client, ce dépôt), **P** pour le portail (dépôt à créer). Les sections sans cette colonne concernent l'application seule.

### 5.1 Organisations et comptes

| Réf. | Exigence | Où | Priorité |
| --- | --- | --- | --- |
| ORG-1 | Une association crée son organisation en libre-service sur le portail (nom, e-mail, mot de passe), avec vérification de l'e-mail. Tant que le portail n'existe pas, l'exploitant crée l'instance à la main. | P | M |
| ORG-2 | Chaque organisation a sa propre instance et sa propre base : ses données (événements, parties, joueurs, cartons, gagnants) sont invisibles des autres par construction. | A, P | M |
| ORG-3 | Dans l'instance, connexion par e-mail et mot de passe, réinitialisation du mot de passe par e-mail. Le premier compte est créé avec l'instance. | A | M |
| ORG-4 | Le propriétaire invite des membres dans son instance avec un rôle : animateur ou assistant de salle. | A | S |
| ORG-5 | Un même compte du portail donne accès à plusieurs instances (animateur intervenant pour plusieurs associations). | P | C |
| ORG-6 | À la demande du propriétaire, l'instance est sauvegardée puis supprimée avec sa base, après un délai. | P | M |
| ORG-7 | Chaque organisation a un identifiant de sous-domaine, choisi à l'inscription : lettres minuscules, chiffres et tirets, unique. Une liste de noms réservés est refusée (`www`, `staging`, `app`, `admin`, `api`, `mail`…). | P | M |
| ORG-8 | Une organisation peut utiliser son propre nom de domaine à la place du sous-domaine. | P | C |

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
| CAR-1 | La référence d'un carton est unique **par organisation**. Acquis sans développement : la référence est unique dans la base, et chaque organisation a la sienne. | M |
| CAR-2 | Import CSV des cartons depuis l'interface, avec rapport d'erreurs ligne par ligne. | M |
| CAR-3 | Les cartons d'une organisation sont réutilisables d'un événement à l'autre. | M |
| CAR-4 | Contrôle de validité d'une grille à la saisie : 3 lignes de 5 numéros entre 1 et 90, sans doublon. | M |
| CAR-5 | Génération de planches de cartons en PDF à imprimer, pour les associations sans stock de cartons. | S |
| CAR-6 | Bibliothèque des séries de cartons du commerce, pour éviter la saisie. | C |
| CAR-7 | La grille d'un carton est unique **par organisation** : un carton dont la grille existe déjà sous une autre référence est refusé, à la saisie comme à l'import, avec la référence du carton en double. | M |
| CAR-8 | Aucune marque ni série de cartons n'est imposée : tout carton conforme à CAR-4 est accepté. | M |

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

Les formules et les prix sont détaillés dans le [business plan](business-plan.md#5-offre-et-prix). La facturation est dans le portail et passe par Mollie, en paiement intégré : le formulaire de carte est affiché dans le tunnel du portail, sans passage par la page de paiement de Mollie ([§5.9](#59-tunnel-de-vente)). Le portail ne voit ni ne conserve aucun numéro de carte. Les factures et l'espace client (formule en cours, historique, résiliation) sont à la charge du portail.

**À trancher avant ce lot** : paiement par événement ou abonnement. Un loto est ponctuel ; avec un pass par événement, l'instance est créée avant le loto et archivée après, alors qu'un abonnement la garde en service toute l'année. Le choix change FAC-7 et le coût d'hébergement par client.

| Réf. | Exigence | Où | Priorité |
| --- | --- | --- | --- |
| FAC-1 | Formule gratuite limitée, sans carte bancaire. | P | M |
| FAC-2 | Achat d'un pass pour un événement et d'un abonnement annuel, par carte bancaire via Mollie. | P | M |
| FAC-3 | Les limites de la formule sont appliquées par l'instance (nombre de cartons, export, personnalisation). Le portail les lui transmet à la création et à chaque changement de formule. | A, P | M |
| FAC-4 | Facture téléchargeable, au nom de l'association. | P | M |
| FAC-5 | Un événement en cours n'est jamais interrompu par une échéance d'abonnement ni par une suspension. | A, P | M |
| FAC-6 | Paiement par virement ou mandat administratif pour les comités des fêtes et collectivités. | P | C |
| FAC-7 | Les notifications de paiement de Mollie (webhooks) pilotent l'instance : payé, elle est créée ; impayé, elle est suspendue ; résilié, elle est sauvegardée puis supprimée après un délai. | P | M |

### 5.8 Exploitation de la plateforme

| Réf. | Exigence | Où | Priorité |
| --- | --- | --- | --- |
| EXP-1 | Console d'exploitation : liste des instances, formule, état, version déployée, dernière activité. | P | M |
| EXP-2 | Prise en main d'un compte client pour le support, tracée. | P, A | S |
| EXP-3 | Indicateurs : inscriptions, activation (premier événement animé), conversion, attrition. | P | S |
| EXP-4 | Création d'une instance par une seule commande (`make provision CLIENT=x`) : base, premier compte, secrets, routage, sauvegarde. Le portail appelle ensuite cette même commande. | A | M |
| EXP-5 | Chaque instance a un état visible : en attente, en cours de création, active, suspendue, en erreur. Une création qui échoue se relance sans dégât. | P | M |
| EXP-6 | Mise à jour de toutes les instances par une seule commande, instance par instance, avec arrêt à la première erreur. Une instance dont un événement est en cours n'est pas mise à jour. | A | M |

### 5.9 Tunnel de vente

Le tunnel est le chemin qui mène d'un visiteur du site à une instance en service. Il est dans le portail, en Symfony, avec la bibliothèque PHP de Mollie et ses champs de carte intégrés (Mollie Components). Sylius n'est pas retenu : il n'y a ni catalogue, ni panier, ni livraison, et l'abonnement n'y est pas natif. Le choix est à revoir si le service vend un jour des produits expédiés (impression et envoi de cartons).

```
Formule → Sous-domaine → Compte → Paiement (dans le portail) → Création de l'instance → Instance prête
                                   └ formule gratuite : étape sautée
```

**Parcours**

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| TUN-1 | Le tunnel enchaîne quatre étapes : choix de la formule, choix du sous-domaine, création du compte, paiement. Une étape par écran, utilisable d'une main sur un téléphone. | M |
| TUN-2 | Le sous-domaine est contrôlé dès la saisie (format, noms réservés, disponibilité, voir ORG-7) et l'adresse complète est affichée telle qu'elle apparaîtra en salle. | M |
| TUN-3 | Le sous-domaine choisi est réservé 30 minutes, le temps de finir le tunnel. Sans paiement abouti, il est libéré. | M |
| TUN-4 | Le compte est créé avec un e-mail et un mot de passe ; l'e-mail est vérifié avant toute création d'instance. | M |
| TUN-5 | La formule gratuite suit le même tunnel sans l'étape de paiement : l'instance est demandée dès l'e-mail vérifié. | M |
| TUN-6 | L'écran de paiement affiche le récapitulatif (formule, prix toutes taxes comprises, adresse de l'instance) et le formulaire de carte. Les conditions générales de vente sont acceptées sur cet écran. | M |

**Paiement et création de l'instance**

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| TUN-7 | La commande est enregistrée avant l'envoi du paiement à Mollie, à l'état « en attente de paiement », avec la formule et le montant figés. | M |
| TUN-18 | Le paiement est intégré au portail : pas de redirection vers la page de paiement de Mollie. Les champs de carte (numéro, date, cryptogramme) sont servis par Mollie à l'intérieur de la page et habillés selon la charte ; le portail ne reçoit qu'un jeton à usage unique, qu'il transmet à Mollie avec la commande. Aucun numéro de carte ne passe par le serveur du portail ni par ses journaux. | M |
| TUN-19 | Quand la banque demande une authentification forte (3-D Secure), le client est envoyé sur la page de sa banque puis revient dans le tunnel. C'est la seule sortie du portail pendant le paiement. | M |
| TUN-20 | Une carte refusée ou une saisie invalide est signalée sur l'écran de paiement, champ par champ, sans perdre la commande ni recharger le tunnel. | M |
| TUN-21 | À l'ouverture, le seul moyen de paiement est la carte bancaire. Les moyens qui imposent une redirection (virement, prélèvement) sont hors du tunnel, voir FAC-6. | M |
| TUN-8 | Seule la notification de Mollie (webhook) fait foi. À sa réception, le portail relit l'état du paiement auprès de Mollie avant d'agir. L'écran affiché après le paiement ou au retour de la banque n'active rien : il affiche l'état lu en base. | M |
| TUN-9 | Au paiement confirmé, une seule transaction marque la commande payée, émet la facture, écrit l'état voulu de l'instance et envoie la demande de création au worker ([§6.1](#61-architecture-et-isolation-des-données)). | M |
| TUN-10 | Une notification reçue plusieurs fois ne crée qu'une instance et qu'une facture. | M |
| TUN-11 | Après le paiement, une page montre l'avancement de la création, puis le lien vers l'instance. Un e-mail est envoyé quand elle est prête, avec son adresse et un lien pour définir le mot de passe du premier compte. Aucun mot de passe n'est envoyé par e-mail. | M |

**Cas d'échec**

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| TUN-12 | Paiement abandonné, refusé ou expiré : la commande est annulée, le sous-domaine libéré, et le client peut reprendre au paiement sans ressaisir ses choix tant que la réservation court. | M |
| TUN-13 | Sous-domaine pris par un autre entre la réservation expirée et le paiement : le client garde son paiement et choisit un autre sous-domaine avant la création. | M |
| TUN-14 | Création en erreur après un paiement confirmé : le client voit que son instance est en préparation, l'exploitant est alerté et relance la création (EXP-5). Si elle n'aboutit pas, le remboursement est fait à la main depuis Mollie. | M |
| TUN-15 | Notification de Mollie jamais reçue : une tâche périodique relit auprès de Mollie les commandes restées en attente de paiement. | M |

**Après l'achat**

| Réf. | Exigence | Priorité |
| --- | --- | --- |
| TUN-16 | Depuis l'espace client, passer de la formule gratuite à une formule payante reprend le tunnel à l'étape du paiement. L'instance n'est pas recréée : elle reçoit ses nouvelles limites (FAC-3). | M |
| TUN-17 | Code de réduction saisi au récapitulatif, pour l'offre de lancement et les préventes. | S |

Le renouvellement et la résiliation dépendent du modèle de prix à trancher ([§5.7](#57-abonnement-et-facturation)) et ne sont pas décrits ici. Les règles de rétractation applicables à une association cliente sont à faire préciser par le juriste ([§2](#2-cadre-réglementaire)).

---

## 6. Cible SaaS : exigences techniques

### 6.1 Architecture et isolation des données

**Décision (15 novembre 2026)** : une instance Docker par client, plutôt qu'une application partagée entre toutes les organisations. Le code est mono-association de bout en bout ; le garder ainsi évite une refonte, supprime le risque de fuite entre clients et limite une panne à un seul loto. En contrepartie, chaque mise à jour est à rejouer sur toutes les instances et chaque client consomme quelques conteneurs.

```
Portail (site, espace client, facturation, gestion des clients)
        │ écrit l'état voulu : actif / suspendu / supprimé
        ▼
Worker de provisionnement (non exposé au web)
        │ crée la base, lance les conteneurs, pose le routage
        ▼
Instances : client1.quinou.fr, client2.quinou.fr…
```

**Deux logiciels**

* **L'application** (ce dépôt) : une seule image, identique pour tous les clients. Elle ne connaît ni les autres clients ni la facturation.
* **Le portail** (dépôt séparé, à créer) : une seule application Symfony pour le site vitrine, l'inscription, l'espace client, la facturation et la console d'exploitation. La page d'attente et la charte y déménagent à sa création ; ce dépôt devient alors la référence de la charte et du logo.

**Instances**

* Un projet Compose par client, avec son sous-domaine, son premier compte, ses secrets et ses limites de formule en variables.
* Un seul serveur PostgreSQL, une base et un utilisateur PostgreSQL par client.
* À la création, l'instance reçoit son premier compte, joue ses migrations au démarrage et expose `/healthz` pour que le worker sache quand elle est prête.

**Adresses et routage**

| Usage | Production | Recette |
| --- | --- | --- |
| Site vitrine, inscription, espace client | `quinou.fr` | `staging.quinou.fr` |
| Instance d'un client | `<client>.quinou.fr` | `<client>.staging.quinou.fr` |

* Un enregistrement DNS générique (`*.quinou.fr`, `*.staging.quinou.fr`) et un certificat générique par nom, obtenus par validation DNS chez OVH. Aucun appel à l'API DNS à la création d'un client.
* Traefik découvre chaque instance par ses labels. Les noms explicites (`www`, `staging`) restent prioritaires sur le générique.
* Le jeton OVH donné à Traefik est limité à la zone `quinou.fr`.

**Provisionnement**

* Le portail ne touche jamais Docker : donner le socket Docker à une application web publique reviendrait à lui donner le serveur. Il écrit seulement l'état voulu en base.
* Un worker Symfony Messenger, non exposé au web, lit ces demandes et exécute la commande de création (EXP-4).
* Transport Doctrine, pas de RabbitMQ : quelques messages par jour, et le message part dans la même transaction que l'écriture de l'état voulu. RabbitMQ ne se justifie que si les instances sont réparties sur plusieurs serveurs.
* Le worker a un utilisateur PostgreSQL limité aux tables des messages et des instances. Le transport `failed` est configuré : un provisionnement raté reste visible et relançable.

**Isolation**

* Aucune donnée d'un client ne vit dans la base d'un autre, ni dans celle du portail au-delà du compte et de la facturation.
* Un test automatisé crée deux instances et vérifie qu'un compte de l'une est refusé par l'autre.

**Quand revoir ce choix** : si le nombre d'instances rend les mises à jour ou la mémoire du serveur pénibles. Le seuil est estimé entre 10 et 15 clients sur le serveur actuel ; c'est une estimation, à mesurer avec les premières instances.

### 6.2 Sécurité

* Comptes en base dans chaque instance, mots de passe hachés, limitation des tentatives de connexion.
* Protection CSRF sur toutes les actions qui modifient des données, sans exception.
* Canaux temps réel non devinables et réservés aux abonnés autorisés pour l'écran d'animation.
* Aucun secret par défaut ni partagé : clé applicative, clé Mercure et mot de passe de base sont générés pour chaque instance à sa création.
* Le portail et le worker n'ont pas les mêmes droits (voir [§6.1](#61-architecture-et-isolation-des-données)).
* Sur l'écran de paiement, la politique de sécurité du contenu n'autorise que les scripts du portail et celui de Mollie. Aucun script tiers (mesure d'audience, polices distantes) n'y est chargé.

### 6.3 Disponibilité et performance

* Un loto dure une soirée : une panne pendant l'événement est l'incident le plus grave. Objectif de disponibilité de 99,5 % sur les créneaux de soirée et de week-end.
* Une panne ou une mise à jour ratée d'une instance ne touche pas les autres. Une panne du portail n'empêche pas d'animer un loto.
* Cochage d'un numéro visible sur l'écran public en moins d'une seconde.
* Détection des gagnants en moins d'une seconde pour 5 000 cartons en jeu.
* Sauvegarde quotidienne de chaque base client et de celle du portail (`pg_dump` par base), restauration testée.
* Supervision et alerte en cas d'indisponibilité, par instance.
* La mémoire consommée par une instance est mesurée, pour connaître le nombre de clients que le serveur peut porter.

### 6.4 Données personnelles (RGPD)

* L'association est responsable du traitement des données de ses joueurs ; l'éditeur est sous-traitant. Un accord de sous-traitance est intégré aux conditions générales.
* Données minimales : seul le nom du joueur est utile au jeu ; téléphone et e-mail restent facultatifs.
* Durée de conservation : les données de joueurs d'un événement sont supprimées ou anonymisées après un délai configurable (12 mois par défaut).
* Export et suppression des données d'une organisation à la demande : export de sa base, puis suppression de l'instance et de ses sauvegardes.
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
| Comptes | Un utilisateur `admin` en mémoire, mot de passe en variable d'environnement | Utilisateurs en base dans l'instance, premier compte créé avec elle, rôles |
| Cloisonnement | Une instance = une association | Conservé : une instance et une base par client |
| Création d'une instance | Script d'installation lancé à la main sur le serveur, pour staging et production | Commande de création par client, puis worker piloté par le portail |
| Adresse | Un seul nom par instance (`Host(APP_HOST)`), certificat par nom | DNS et certificat génériques, routage par sous-domaine |
| Portail | Une page d'attente statique sur `quinou.fr` | Site, inscription, espace client, facturation, console |
| Mise à jour | Déploiement d'une instance par la CI | Une commande pour toutes les instances |
| Sauvegarde | Non vérifiée | `pg_dump` quotidien par base, restauration testée |
| Événement courant | Le dernier événement créé, pour l'admin comme pour le public | Choix de l'événement animé ; adresse publique par événement |
| Import de cartons | Ligne de commande | Interface web |
| Unicité des cartons | Référence unique ; deux cartons peuvent avoir la même grille | Référence et grille uniques (CAR-7) |
| Temps réel | Sujets Mercure construits sur l'identifiant de l'événement | Sujets non devinables, abonnements autorisés |
| Journal des actions | Annoncé dans l'ancien cahier des charges, absent du code | À créer (ANI-2) |
| Facturation | Aucune | Formules, paiement Mollie, factures, cycle de vie des instances |

---

## 8. Lotissement

| Lot | Contenu | Résultat |
| --- | --- | --- |
| **0 – Assainissement** | Corrections qui ne dépendent pas du SaaS : gel, resets, repository, jeton CSRF obligatoire sur toutes les actions (fait) | Socle fiable |
| **1 – Instances créées à la main** | DNS et certificat génériques, routage par sous-domaine, EXP-4, EXP-6, sauvegarde par base, ORG-2, ORG-3, EVT-1, PUB-1, PUB-2, §6.2 | L'exploitant crée une instance par association pilote, en accès gratuit |
| **2 – Portail** | Dépôt du portail, site vitrine, ORG-1, ORG-6, ORG-7, EXP-1, EXP-5, worker de provisionnement, tunnel sans paiement (TUN-1 à TUN-5, TUN-11), EVT-4, CAR-2, CAR-4, CAR-7, CAR-8, JOU-1, JOU-2, ANI-1 | Une association s'inscrit seule et reçoit son instance |
| **3 – Facturation et ouverture** | FAC-1 à FAC-5, FAC-7, tunnel avec paiement (TUN-6 à TUN-10, TUN-12 à TUN-16, TUN-18 à TUN-21), §6.3 à §6.5 | Paiement en ligne, ouverture commerciale |
| **4 – Confort et fidélisation** | ORG-4, EVT-2, EVT-3, CAR-5, JOU-3, ANI-2, ANI-3, PUB-3, PUB-4, EXP-2, EXP-3, TUN-17 | Raisons de s'abonner à l'année |
| **5 – Extensions** | ORG-5, ORG-8, CAR-6, ANI-4, PUB-5, FAC-6, vente de cartons en amont (sous réserve juridique) | Nouvelles sources de revenu |

Le lot 1 doit être validé par deux ou trois associations pilotes sur de vrais lotos avant d'engager le lot 2. Le modèle de prix (voir [§5.7](#57-abonnement-et-facturation)) est à trancher avant le lot 3.

Tant que le lot 3 n'est pas livré, seule la recette est déployée (`staging.quinou.fr`) ; `quinou.fr` affiche la page d'attente. Les instances pilotes du lot 1 sont les premières à tourner en production.

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

1. une association inconnue s'inscrit, reçoit son instance à son adresse, importe ses cartons et anime une partie de démonstration en moins de 15 minutes, sans aide ;
2. deux organisations animent un loto le même soir, chacune sur son instance, et l'arrêt de l'une ne perturbe pas l'autre ; un test automatisé vérifie qu'un compte de l'une est refusé par l'autre ;
3. un événement réel de 300 joueurs et 1 500 cartons se déroule sans incident ni ralentissement ;
4. un paiement, sa facture, la création de l'instance qui en découle et l'application des limites de formule fonctionnent de bout en bout ; un paiement abandonné et une notification reçue deux fois ne laissent ni instance ni facture en trop ;
5. la sauvegarde d'une instance a été restaurée avec succès, et une création d'instance interrompue a été relancée jusqu'au bout ;
6. les documents légaux sont publiés et le cadre réglementaire a été relu par un juriste.
