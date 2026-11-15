# Business plan – Loto Quine en SaaS

> Version du 15 novembre 2026. Complète le [cahier des charges](spec.md).
> Les chiffres de marché viennent de sources publiques citées en fin de document. Les chiffres de vente, de coûts et de parts de marché sont des **hypothèses de travail** : elles sont signalées comme telles et doivent être confrontées au terrain avant tout engagement.

## En bref

* **Le produit** : un service en ligne qui assiste l'animateur d'un loto associatif, détecte les cartons gagnants et affiche le tirage au public.
* **Le marché** : des dizaines de milliers d'associations organisent des lotos pour se financer ; elles sont peu équipées, sensibles au prix et habituées à des outils gratuits ou à moins de 50 € par an.
* **Le modèle** : une formule gratuite pour essayer, un pass à 19 € par événement, un abonnement à 59 € par an, une formule pro à 190 € par an.
* **Le verdict** : le projet est rentable dès une centaine de clients parce que ses coûts sont très bas, mais il ne finance un salaire qu'autour de 1 000 clients payants. C'est un bon complément d'activité ; pour en faire une activité principale, il faut les extensions du lot 4 ou la clientèle des animateurs professionnels.
* **La prochaine étape** : trois associations pilotes et une prévente, avant de développer la facturation.

---

## 1. Problème et solution

Un loto associatif repose sur des bénévoles. Trois moments posent problème :

1. **La vérification du carton gagnant.** Un bénévole relit les numéros à voix haute devant la salle. C'est lent, et source d'erreurs et de contestations, surtout en cas d'égalité.
2. **Le suivi du tirage.** Un tableau papier ou un logiciel d'affichage installé sur le PC d'un bénévole, qui n'est plus disponible le jour où ce bénévole s'en va.
3. **La remise des lots et le bilan.** Qui a gagné quoi, avec quel carton, comment le recontacter.

Le produit répond aux trois : détection automatique du carton gagnant à l'instant du gain, affichage public en temps réel depuis un simple navigateur, registre des gagnants exportable.

---

## 2. Marché

### 2.1 Ce que l'on sait

* La France compte entre 1,3 et 1,6 million d'associations actives selon les sources, et environ 13 millions de bénévoles.
* Le loto traditionnel est un moyen de financement courant des associations sportives, scolaires, culturelles et des comités des fêtes.
* Les associations sont sous tension financière : elles cherchent des recettes, mais regardent chaque dépense.
* L'exonération de TVA couvre six manifestations par an : une association organise donc rarement plus de quelques lotos par saison.

### 2.2 Ce que l'on ne sait pas

**Il n'existe pas de chiffre public du nombre de lotos organisés chaque année.** C'est la principale inconnue du plan.

Hypothèse de travail : 2 à 4 % des associations actives organisent au moins un loto par an, soit **30 000 à 60 000 organisateurs** sur une base de 1,5 million. Cette fourchette est une estimation personnelle, à vérifier (agendas de lotos en ligne, vendeurs de matériel, fédérations).

### 2.3 Cibles

| Segment | Besoin | Capacité à payer |
| --- | --- | --- |
| **Associations organisant 1 à 2 lotos par an** (parents d'élèves, clubs sportifs) | Simplicité, zéro installation | Faible : paiement à l'événement |
| **Organisateurs réguliers** (comités des fêtes, amicales, clubs organisant 3 à 6 lotos) | Fiabilité, réutilisation des cartons et des joueurs | Moyenne : abonnement annuel |
| **Animateurs et loueurs de matériel** qui animent pour le compte d'associations | Outil professionnel, plusieurs clients, image de marque | La plus élevée : formule pro |

Le troisième segment est le plus petit en nombre mais le plus intéressant : un animateur fait plusieurs dizaines de lotos par an et amène le produit dans autant d'associations.

---

## 3. Concurrence

| Solution | Type | Prix public relevé | Détection des cartons gagnants |
| --- | --- | --- | --- |
| Affichage Loto | Logiciel Windows d'affichage | Gratuit | Non |
| Loto Associatif | Application d'affichage | Gratuit | Non |
| OpenLoto | Logiciel libre : tirage, contrôle des cartons, écran public, suivi sur téléphone | Gratuit | Oui (contrôle) |
| FandeLoto | Logiciel sous licence | 19,90 € la semaine, 49,90 € par an, 79,90 € pour deux ans, par appareil | Non relevé |
| Winloto | Logiciel sous licence | 169 € TTC par ordinateur | Liée aux marques de cartons, en option |
| Loto Quine | Application pour joueurs | Gratuite, reconnaissance photo payante | Côté joueur uniquement |
| Donoor, HelloAsso | Billetterie associative | Sans abonnement | Hors sujet (vente de places) |

Les prix viennent des sites des éditeurs ou de leurs revendeurs et peuvent avoir changé.

**Ce que cela implique :**

* Le prix de référence du marché est bas : gratuit, ou une cinquantaine d'euros par an.
* L'affichage du tirage seul ne se vend pas : il est gratuit partout.
* La valeur se trouve dans ce que les autres ne font pas ou font mal : registre joueurs ↔ cartons, gel du gain, traçabilité, travail à plusieurs (entrée de salle et animation), aucun PC à préparer.
* OpenLoto est le concurrent le plus proche sur le plan fonctionnel ; il faut l'essayer en détail avant de fixer le positionnement.

---

## 4. Positionnement

> Le loto sans contestation : chaque carton gagnant est repéré à l'instant du gain, devant toute la salle.

Trois promesses, dans cet ordre : **fiable** le soir de l'événement, **prêt en un quart d'heure**, **moins cher qu'un lot de consolation**.

---

## 5. Offre et prix

Prix affichés TTC : une association ne récupère pas la TVA. Tant que le chiffre d'affaires reste sous le seuil de la franchise en base, aucune TVA n'est facturée.

| Formule | Prix | Contenu | Cible |
| --- | --- | --- | --- |
| **Découverte** | Gratuit | 1 événement, 100 cartons, écran public avec la mention du service, sans export | Essai, petits lotos |
| **Pass événement** | 19 € par événement | Cartons illimités, export des gagnants, logo de l'association | 1 à 2 lotos par an |
| **Saison** | 59 € par an | Événements illimités, réutilisation des cartons et joueurs, membres invités | Organisateurs réguliers |
| **Pro** | 190 € par an | Plusieurs associations clientes, écran aux couleurs de l'animateur, support prioritaire | Animateurs, loueurs |

Repères : le pass coûte le prix de quatre à dix cartons vendus ; l'abonnement Saison se situe juste au-dessus de FandeLoto et très en dessous de Winloto, pour un service sans installation.

Revenu moyen par client payant retenu pour la suite : **57 € par an**. Il suppose 50 % de clients au pass (1,5 événement en moyenne), 40 % en Saison et 10 % en Pro.

---

## 6. Acquisition

Le budget est faible ; la stratégie repose sur des canaux gratuits ou peu coûteux.

1. **L'écran public comme vitrine.** Chaque loto en formule gratuite montre le nom du service à 100 à 300 personnes, dont des bénévoles d'autres associations. C'est le premier canal, et il ne coûte rien.
2. **Référencement naturel.** Guides pratiques sur les recherches que font les organisateurs : organiser un loto, réglementation, modèle de règlement, combien de cartons prévoir.
3. **Générateur de cartons gratuit.** Un outil en libre accès qui produit des planches à imprimer et amène vers le service (exigence CAR-5).
4. **Animateurs et loueurs de matériel.** Formule Pro et parrainage : un prescripteur équipe des dizaines d'associations.
5. **Communautés.** Groupes de lotos sur les réseaux sociaux, agendas de lotos, comités des fêtes, mairies.
6. **Saisonnalité.** La saison des lotos court surtout d'octobre à mars : la communication démarre en septembre.

---

## 7. Prévisions financières

### 7.1 Hypothèses de ventes

Nombre de clients payants sur l'année.

| Scénario | Année 1 | Année 2 | Année 3 |
| --- | --- | --- | --- |
| Prudent | 80 | 250 | 600 |
| Central | 150 | 500 | 1 200 |

Le scénario central en année 3 représente 2 à 4 % des organisateurs estimés au §2.2.

### 7.2 Charges annuelles (hypothèses)

| Poste | Année 1 | Année 2 | Année 3 |
| --- | --- | --- | --- |
| Hébergement, sauvegardes, supervision | 600 € | 900 € | 1 500 € |
| Nom de domaine, e-mails, outils | 300 € | 300 € | 300 € |
| Assurance, comptabilité, banque | 900 € | 900 € | 900 € |
| Juridique (conditions générales, relecture du cadre légal) | 1 500 € | 300 € | 300 € |
| Acquisition | 1 500 € | 3 000 € | 6 000 € |
| **Total charges fixes** | **4 800 €** | **5 400 €** | **9 000 €** |
| Frais de paiement | 3 % du chiffre d'affaires | idem | idem |

Ces charges n'incluent ni la rémunération du fondateur, ni les cotisations sociales, ni l'impôt.

### 7.3 Résultat avant rémunération

| | Année 1 | Année 2 | Année 3 |
| --- | --- | --- | --- |
| **Scénario prudent** | | | |
| Chiffre d'affaires | 4 560 € | 14 250 € | 34 200 € |
| Charges fixes | 4 800 € | 5 400 € | 9 000 € |
| Frais de paiement | 137 € | 428 € | 1 026 € |
| **Résultat** | **− 377 €** | **8 422 €** | **24 174 €** |
| **Scénario central** | | | |
| Chiffre d'affaires | 8 550 € | 28 500 € | 68 400 € |
| Charges fixes | 4 800 € | 5 400 € | 9 000 € |
| Frais de paiement | 257 € | 855 € | 2 052 € |
| **Résultat** | **3 493 €** | **22 245 €** | **57 348 €** |

### 7.4 Seuils

* **Couvrir les charges de l'année 1** : 87 clients payants.
* **Financer un temps plein** (coût total supposé de 45 000 € par an, avec les charges de l'année 3) : environ 980 clients payants.

### 7.5 Investissement initial

L'essentiel de l'investissement est du temps de développement. Estimation pour atteindre l'ouverture commerciale (lots 0 à 2 du cahier des charges) :

| Lot | Charge estimée |
| --- | --- |
| 0 – Assainissement | 3 jours |
| 1 – Multi-organisation | 15 jours |
| 2 – Ouverture commerciale | 30 jours |
| **Total** | **48 jours** |

Si ce temps est fourni par le fondateur, le besoin de trésorerie se limite aux charges de l'année 1, soit environ 5 000 €. Sous-traité à 450 € par jour, il représenterait 21 600 €, ce que les résultats prévus ne remboursent pas avant l'année 3 dans le scénario prudent.

---

## 8. Lecture du plan

**Ce qui joue en faveur du projet**

* Le produit existe et fonctionne ; son cœur métier est testé.
* Les coûts fixes sont très bas : la rentabilité arrive vite et le risque financier est limité.
* Le besoin est réel et la différenciation (détection, gel, registre) est tangible face à des outils d'affichage.

**Ce qui le limite**

* Le panier est petit : il faut beaucoup de clients pour un revenu significatif.
* Une association qui organise un seul loto par an paie 19 € et peut ne pas revenir.
* Les concurrents gratuits fixent une attente de prix proche de zéro.

**Conséquence**

Tel quel, le projet est un **complément d'activité rentable**, pas une entreprise qui fait vivre son fondateur avant plusieurs années. Trois leviers peuvent changer d'échelle, par ordre de crédibilité :

1. **Les animateurs professionnels et loueurs de matériel** : peu nombreux, mais à forte valeur et prescripteurs. Une formule Pro mieux dotée (et plus chère) est la piste la plus directe.
2. **Les services autour de l'événement** : impression et envoi de cartons, vente de cartons en amont avec commission (sous réserve de validation juridique), partenariats avec des vendeurs de matériel.
3. **L'élargissement** à d'autres animations associatives payantes (tombola, bingo, quiz) sur la même base de clients.

---

## 9. Risques

| Risque | Gravité | Parade |
| --- | --- | --- |
| Panne ou coupure réseau en pleine soirée | Élevée | Resynchronisation automatique (ANI-1), consigne de partage de connexion mobile, supervision les soirs d'événement |
| Faible consentement à payer | Élevée | Formule gratuite utile, prévente avant de développer la facturation |
| Cadre légal mal compris par un client | Moyenne | Conditions d'utilisation, rappels dans l'interface, relecture par un juriste |
| Taille de marché surestimée | Moyenne | Vérifier l'hypothèse du §2.2 pendant la phase pilote |
| Charge de support le samedi soir | Moyenne | Aide en ligne, mode démonstration, support prioritaire réservé à la formule Pro |
| Fuite de données de joueurs | Élevée | Cloisonnement testé, données minimales, suppression automatique |
| Saisonnalité des revenus | Faible | Abonnement annuel, trésorerie lissée |

---

## 10. Feuille de route

| Étape | Contenu | Condition pour passer à la suivante |
| --- | --- | --- |
| **1. Pilote** (3 mois) | Lots 0 et 1, trois associations pilotes sur de vrais lotos, entretiens avec deux animateurs professionnels | Trois lotos animés sans incident ; au moins deux pilotes prêts à payer |
| **2. Prévente** (1 mois) | Page de présentation, liste d'attente, offre de lancement | 30 préinscriptions ou 10 préventes |
| **3. Ouverture** (3 mois) | Lot 2, lancement en septembre pour la saison | 80 clients payants sur la première saison |
| **4. Fidélisation** | Lot 3 | Taux de réabonnement supérieur à 60 % |
| **5. Changement d'échelle** | Lot 4 et leviers du §8 | Décision selon les résultats |

Si l'étape 1 ou 2 échoue, le projet s'arrête ou reste un outil interne, pour un coût limité au temps du pilote.

### Structure juridique

Démarrage en micro-entreprise : formalités réduites et franchise de TVA adaptées à un chiffre d'affaires de quelques milliers d'euros. Passage en société si le scénario central se confirme en année 2.

---

## Sources

* Cadre légal du loto traditionnel : [fiche service-public sur les loteries, tombolas et lotos d'association](https://www.service-public.gouv.fr/associations/vosdroits/F21565) ; [réponse ministérielle sur l'article L322-4 et l'abrogation du plafond des lots](https://questions.assemblee-nationale.fr/dyn/15/questions/QANR5L15QE41292.pdf).
* Tissu associatif : [Recherches & Solidarités, La France associative en mouvement, octobre 2025](https://recherches-solidarites.org/wp-content/uploads/2025/10/La-France-associative-16-10-2025.pdf) ; [Maire-info sur les difficultés financières des associations](https://www.maire-info.com/associations/le-monde-associatif-toujours-touche-par-de-graves-difficultes-financieres-article-30495).
* Concurrents et prix : [Affichage Loto](https://affichageloto.alwaysdata.net/), [Loto Associatif](https://florian-mollin.github.io/loto-associatif/), [OpenLoto](https://github.com/SebastienFRA/openloto), [FandeLoto](https://www.fandeloto.com/), [Winloto au Palais du Loto](https://www.lepalaisduloto.fr/tirage-de-loto-electronique/66-logiciel-de-tirage-de-loto-winloto.html), [Loto Quine](https://www.lotoquine.app/), [Donoor](https://donoor.org/projets/loto), [guide HelloAsso sur l'organisation d'un loto](https://www.helloasso.com/blog/?p=25326).
