# Charte graphique

Statut : troisième version, alignée sur la maquette de page d'accueil validée (esprit des grands logiciels en ligne : produit en héros, couleurs franches, titres très gros). Le produit s'appelle **Quinou** (diminutif de « quine »). Les domaines quinou.fr, quinou.com et quinou.app étaient libres le 15 novembre 2026, non réservés ; la marque n'a pas été vérifiée à l'INPI.

Cette charte vaut pour le site vitrine, l'administration et l'écran de salle.

## 1. Positionnement

**Pour qui.** Les associations qui vendent des cartons à des joueurs hors salle : associations de parents d'élèves, coopératives scolaires, clubs de sport. Les comités des fêtes ne sont pas visés au départ.

**Qui décide.** Un parent bénévole du bureau, 30 à 45 ans, qui prépare le loto le soir sur son téléphone.

**Ce que fait l'outil.** Il joue les cartons des joueurs qui ne sont pas dans la salle. L'association vend des cartons à la famille et aux proches qui ne viendront pas ; des bénévoles saisissent ces cartons ; le soir du loto, l'outil repère le gagnant, fige la partie au numéro exact et bloque le carton. Le lendemain, le bureau appelle les gagnants à partir de la liste exportée.

**Promesse.** Un loto plus grand que la salle.

**Ordre de grandeur réel.** Loto du 23 février 2025 : 605 cartons hors salle, 140 joueurs hors salle. À ne publier qu'avec l'accord de l'association. Ces cartons ont été vendus par 67 enfants : ce chiffre reste interne et ne se publie pas.

**Face aux concurrents.** Le plus visible (LotoAsso) est une suite complète, blanche, avec un seul bleu-violet. On s'en distingue par les quatre couleurs de la marque, les statuts multicolores et le produit montré dès le premier écran.

**Ce qu'on ne promet pas.** Génération de cartons, code-barres et scan sont à venir. Le site ne montre que ce qui existe.

## 2. Principe visuel

- **Le produit est le héros.** Le premier écran du site montre l'outil en marche, pas une illustration.
- **La couleur porte une information.** Chaque couleur vive correspond à un état ou à un moment, jamais à un simple décor.
- **Quatre couleurs à égalité** : jaune, bleu, rose, vert. Aucune ne domine ; ce sont les quatre pastilles du logo.
- **Le numéro annoncé est géant** et prend la couleur de la règle en cours (bleu pour la quine).
- **Énergie sur le site, calme dans l'outil.** Le site utilise les grands aplats ; l'administration garde les mêmes couleurs mais seulement dans les pastilles de statut et les boutons.

Interdits : le duo jaune et noir en grand aplat (il évoque une enseigne de location de matériel), bleu-violet, dégradés décoratifs, illustrations 3D, ardoise et craie, dessins d'enfants, emojis.

## 3. Couleurs

| Rôle | Nom | Valeur | Usage |
| --- | --- | --- | --- |
| Fond | Blanc | `#FFFFFF` | Fond des pages |
| Fond secondaire | Brume | `#F4F5FB` | Panneaux, lignes de tableau, cases non tirées |
| Texte | Encre | `#14142B` | Titres, texte, bouton principal, bande sombre |
| Texte secondaire | Encre douce | `#5B5F7A` | Légendes, aides, étiquettes |
| Action, « Avant », quine | Bleu | `#2459E6` | Bouton d'appel principal, numéro annoncé, « il en manque 2 », liens, focus |
| « Pendant » | Jaune | `#FFCB00` | Bloc « Pendant », une pastille du logo. Jamais en grand aplat à côté d'un aplat Encre |
| Statut | Rose | `#FF4F9A` | « Plus qu'un numéro » |
| Statut et « Après » | Vert | `#00B872` | Gain, partie figée |
| Statut neutre | Gris | `#B9BDD1` | « En jeu » |
| Filet | Ligne | `#E3E5F0` | Bordures de 1 px |
| Erreur | Rouge erreur | `#B3261E` | Messages d'erreur, toujours avec un libellé |

Règles de contraste (calculées, niveau AA) :

- Texte **blanc** uniquement sur Encre et sur Bleu.
- Texte **Encre** sur Jaune, Rose, Vert et Gris. Le blanc y est interdit : il descend sous 3,1 pour 1.
- Un statut n'est jamais indiqué par la couleur seule : il porte toujours son libellé.
- Le rose et le vert ne servent jamais de couleur de texte.

## 4. Typographie

| Rôle | Police | Graisses | Usage |
| --- | --- | --- | --- |
| Titres et numéro | Jost | 500, 600, 700 | Titres, numéro annoncé, chiffres clés |
| Texte | Source Sans 3 | 400, 500, 600, 700 | Tout le reste : texte courant, boutons, formulaires, tableaux, références, étiquettes |

Jost est une géométrique de la famille du Futura : ses « o » en cercle et ses traits coupés net reprennent la construction du logo. Deux polices seulement, et aucune police à chasse fixe. Les fichiers sont hébergés dans `assets/fonts` : l'application n'appelle aucun service de polices extérieur.

- Grands titres : graisse 700, interligne 1 à 1,05, approche de -0,03 em. De 40 px sur téléphone à 78 px sur grand écran.
- Texte courant : 17 px sur le site, 16 px dans l'administration, interligne 1,5.
- Étiquettes : Source Sans 3 en 600, 12 px, capitales, approche de 0,1 em.
- Tous les numéros en chiffres alignés (`font-variant-numeric: tabular-nums`), dans la police de texte.
- Trois tailles de texte visibles au maximum par écran.

## 5. Formes et espacements

- Boutons et pastilles d'étiquette : pilule (rayon 999 px).
- Panneaux et blocs : rayon 14 px ; grands blocs de couleur et cadre du produit : 22 px.
- Cellules et pastilles de statut : rayon 8 à 10 px.
- Une seule ombre, large et douce, sous le cadre du produit dans le héros. Aucune autre.
- Espacements sur une échelle de 8 px. Entre deux sections : 88 à 96 px en grand écran, 56 px sur téléphone.
- Conception pour le téléphone d'abord, cibles tactiles de 44 px (le tableau de tirage de la démo du site fait exception).
- Largeur de contenu : 1 120 px au maximum.

## 6. Composants

- **Bouton principal** : pilule, fond Bleu, texte blanc, 700. Dans l'administration, les boutons courants sont en Encre.
- **Bouton secondaire** : pilule, contour Encre de 2 px.
- **Pastille de statut** : pleine largeur de la cellule, libellé en 700. Gris « En jeu », Bleu « Il en manque 2 », Rose « Plus qu'un numéro », Vert « Quine ! ».
- **Ligne de tableau** : fond Brume, coins arrondis, 6 px entre deux lignes. La ligne gagnante passe en vert très clair (`#D9F7EA`).
- **Tableau de tirage** : 10 colonnes, cases Brume ; numéro tiré en Encre ; dernier numéro en Bleu, légèrement agrandi.
- **Numéro annoncé** : Jost 700, blanc sur la couleur de la règle en cours (Bleu pour la quine).
- **Soulignement du titre** : une barre en quatre segments aux couleurs des pastilles, sous les mots clés du titre du héros.
- **Bandeau de gel** : fond Vert, texte Encre, trace en chiffres alignés (« Quine au 11e numéro · carton 0417 »).
- **Champ** : fond blanc, bordure Ligne de 1 px, bordure Bleu de 2 px au focus.
- Focus clavier toujours visible : contour Bleu de 3 px, décalé de 2 px.

## 7. Site vitrine

Une page, dans cet ordre :

1. **Héros** : étiquette de cible, titre, deux boutons, puis le produit en marche (tirage et cartons hors salle), cliquable.
2. **Trois chiffres** d'un vrai loto.
3. **Avant, pendant, après** : trois blocs pleins, Bleu, Jaune, Vert.
4. **Écran de salle** : bande Bleu, numéro géant en blanc.
5. **Prix**, questions fréquentes, appel final.

## 8. Images et données personnelles

- Aucun enfant reconnaissable, ni en photo ni en vidéo.
- Aucune donnée réelle dans les captures et les démos : noms, téléphones et courriels sont inventés.
- L'image principale est toujours le produit. Les photos, s'il y en a, montrent des mains, des cartons, des tables de salle.

## 9. Animations

- Transitions de 300 à 400 ms sur les changements d'état (case tirée, pastille qui change de couleur).
- Un seul moment fort : le numéro tombe, la pastille passe au vert, la partie se fige.
- Version fixe pour `prefers-reduced-motion`.
- L'écran de salle peut garder un effet de célébration au moment du gain.

## 10. Ton

- De parent à parent : phrases courtes, concrètes, verbes d'action.
- Vocabulaire du loto : quine, double quine, carton plein, démarque.
- Vouvoiement (on s'adresse au bureau).
- On ne met pas en avant la vente des cartons par les enfants, ni dans les textes ni dans les images : le sujet est sensible. On écrit que l'association vend des cartons à ceux qui ne peuvent pas venir.
- On ne dit jamais « les absents » : le mot sonne funéraire, surtout à côté d'un grand-parent. On dit « hors salle », « ceux qui ne peuvent pas venir », « de loin ».

## 11. Logo

Quatre pastilles (jaune, bleu, rose, vert, disposées en carré) suivies de « quinou » en minuscules, lettres géométriques très grasses dessinées pour la marque, en Encre. Le nom est doux : le dessin reste franc et adulte pour compenser.

| Fichier | Usage |
| --- | --- |
| `assets/brand/quinou-logo.svg` | Logo sur fond clair |
| `assets/brand/quinou-logo-inverse.svg` | Logo sur fond Encre (lettres blanches, pastilles inchangées) |
| `assets/brand/quinou-icone.svg` | Icône d'application et de navigateur : les quatre pastilles sur carré Encre |

Règles :

- Les pastilles gardent toujours leurs quatre couleurs et leur ordre : jaune et bleu en haut, rose et vert en bas.
- Zone de protection : la largeur de deux pastilles tout autour du logo.
- Largeur minimale du logo : 80 px. En dessous, utiliser l'icône.
- Ne pas recomposer le nom dans une police : utiliser les fichiers.
- Dessin final en vectoriel (SVG).

## 12. Jetons CSS

La charte est appliquée dans `assets/styles/app.css`, chargée par `templates/base.html.twig`. L'application n'utilise plus Tailwind : les gabarits emploient les classes de cette feuille (`btn`, `pill`, `panel`, `table`, `form`, `cell`, `loto-card`, `screen`).

```css
:root {
  --couleur-blanc: #FFFFFF;
  --couleur-brume: #F4F5FB;
  --couleur-encre: #14142B;
  --couleur-encre-douce: #5B5F7A;
  --couleur-jaune: #FFCB00;
  --couleur-bleu: #2459E6;
  --couleur-rose: #FF4F9A;
  --couleur-vert: #00B872;
  --couleur-gris-statut: #B9BDD1;
  --couleur-ligne: #E3E5F0;
  --couleur-erreur: #B3261E;

  --police-titres: "Jost", "Century Gothic", "Trebuchet MS", sans-serif;
  --police-texte: "Source Sans 3", "Source Sans Pro", system-ui, sans-serif;

  --rayon-pilule: 999px;
  --rayon-panneau: 14px;
  --rayon-bloc: 22px;
  --espace: 8px;
}
```
