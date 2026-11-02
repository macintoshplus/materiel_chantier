# Géométrie

## Repères

- **Repère pièce** : 3D, origine à un coin du sol, X/Y dans le plan horizontal, Z vertical vers le haut.
- **Repère surface** : 2D `(u, v)` dans le plan de la surface. Pour un mur, `u` = horizontal le long du mur
  (de gauche à droite en regardant le mur depuis l'intérieur de la pièce), `v` = vertical depuis le sol.
  Pour un pan de plafond, `u` est le long de l'égout (ligne basse), `v` suit la pente.
- Une surface a donc : un **polygone 2D** (sommets dans son repère) et une **transformation** vers le repère
  pièce (origine 3D + vecteurs `u`, `v`). La normale pointe vers l'intérieur de la pièce.
- **Sens de `u` (décision)** : `u` croît de gauche à droite **vue du côté d'où l'on regarde la surface**.
  Pour un mur de pièce (sol anti-horaire, intérieur à gauche du parcours), `u` va donc du sommet `fin` vers
  le sommet `début` de l'arête. Règle unique, testée ; l'aperçu n'applique aucun miroir.

## Forme au sol

Polygone simple (non auto-sécant), sommets dans l'ordre anti-horaire. Cas à gérer :

- rectangle (cas de base), forme en L ou en T (angles rentrants), pans coupés. **Les murs courbes ne sont pas pris en charge** (pas d'approximation par segments) ;
- chaque arête du polygone génère un mur : sa largeur est la longueur de l'arête.

Calculs utiles : aire (formule du lacet), périmètre, orientation, validation de simplicité.

## Arêtes et jonctions

Chaque arête d'une surface peut être **reliée** à l'arête d'une autre surface :

- mur ↔ mur (angle saillant ou rentrant, jonction verticale) ;
- mur ↔ sol (arête basse), mur ↔ plafond (arête haute) ;
- pan de plafond ↔ pan de plafond (faîtage, noue, arêtier) ;
- pan de plafond ↔ mur (sablière, ou mur sous rampant / genou).

Cette relation sert à :
1. calculer les **longueurs de rails périphériques** et de bandes de joint aux angles ;
2. tenir compte des **épaisseurs de revêtement aux angles** (retour de doublage, cotes utiles) ;
3. dessiner la pièce dépliée ou en vue 3D ;
4. propager les cotes (la hauteur d'un mur est contrainte par le plafond qui le coiffe).

Modèle conceptuel : `Arete { surface, indexSommetDebut, indexSommetFin }` et
`Jonction { areteA, areteB, type }`. Deux arêtes jointes ont la même longueur 3D (tolérance de quelques mm).

## Murs non rectangulaires

Un mur est un polygone 2D quelconque : rectangle, trapèze (mur sous rampant), pentagone (pignon).
Une surface est une **forme plane dont les sommets sont construits** : chaque sommet est placé par une
**distance** depuis un autre sommet ou par un **angle**, dans le repère de la surface ; il n'y a pas de
hauteur unique imposée. Un mur, un pan de plafond incliné (un **mur incliné**) et un plafond plat (un **mur
horizontal**) sont donc la même chose : une surface plane. Un même système de revêtement s'applique aux
trois, **seuls les matériaux changent**. Il n'y a pas de description de toit paramétrique : le toit est un
ensemble de surfaces reliées par des jonctions.
Pour les calculs d'ossature, on travaille **dans le repère `(u, v)` de la surface**, pas en projection
horizontale : à chaque position d'élément (montant, fourrure, latte) on coupe le polygone par une droite
dans la direction de l'ossature ; la longueur de l'élément est celle de l'intersection, ouvertures
retranchées. Les montants ont donc des longueurs différentes sur un pignon, et les fourrures d'un pan de
plafond incliné ont leur **longueur réelle** (suivant la pente), sans cas particulier pour les plafonds.

## Plafonds composés

Un plafond est un **ensemble de pans**, chacun étant une surface plane (un pan incliné est un mur incliné,
un plafond plat un mur horizontal : même modèle, mêmes systèmes) :

| Cas | Pans |
|---|---|
| plafond plat | 1 pan horizontal |
| toit double pente avec plafond sous rampant | 2 pans inclinés (+ éventuellement un plat de comble aménagé en plus) |
| comble aménagé | pans rampants + mur de genou + plafond plat central |
| mansarde | jusqu'à 4 pans |

L'angle de pente se déduit des coordonnées 3D. La longueur réelle d'un montant/fourrure sur un rampant
est la longueur suivant la pente, pas la projection horizontale.

## Cloisons

Une cloison est une paroi plane verticale posée **dans** une pièce (la pièce reste le volume physique).

- **Tracé** : un segment au sol `(début, fin)` en mm, qui représente l'**axe** de la cloison ; son épaisseur
  (ossature + parements de chaque côté) la déporte de part et d'autre. Le tracé est **modifiable** : déplacer
  une extrémité, changer la longueur, l'angle, ou la distance à un mur de référence.
- **Faces** : le côté **A** est à gauche du tracé en allant du début à la fin, le côté **B** à droite. Chaque
  face est une surface plane qui a son repère `(u, v)` : `u` croît de gauche à droite **vue du côté de la
  face** (le repère de la face A est donc inversé par rapport au tracé, celui de la face B est dans le sens du tracé), `v` vertical depuis le sol.
- **Hauteur** : déduite du plafond de la pièce à chaque position du tracé. Sous un plafond plat, c'est la
  hauteur sous plafond ; sous un rampant, la face est un trapèze ou un polygone dont le bord haut suit la
  pente (voir « Murs non rectangulaires »).
- **Ancrages** : chaque extrémité est ancrée sur un **mur existant**, une **autre cloison**, un **doublage
  de mur**, ou **libre** (extrémité sans appui, rare). L'ancrage détermine les rails/montants de rive et les
  montants supplémentaires de raccordement (voir `04-platrerie.md`).
- **Jonctions** de la cloison : avec le sol, le plafond (ou les pans), et l'ancrage de chaque extrémité.
  Ces jonctions donnent les longueurs de rails périphériques et la position des montants de raccordement.
- Les extrémités doivent rester dans le volume de la pièce : une extrémité hors du volume est une erreur.

## Ouvertures

Une ouverture est un polygone 2D (le plus souvent un rectangle ; ou cintrée/triangulaire) **défini dans le
repère de sa surface** (pour une cloison : le repère du tracé, partagé par les deux faces ; elle traverse
la cloison et apparaît sur les deux faces) : `position (u, v)` du coin bas-gauche + `largeur`, `hauteur` (ou polygone).
Paramètres complémentaires : type (porte, fenêtre, baie, trappe, velux), **mode de pose**, contour,
refoncement. Règles de validité :

- entièrement incluse dans le polygone de la surface ;
- sans chevauchement avec une autre ouverture de la même surface ;
- distance minimale à une arête (ex. ≥ 1 épaisseur de montant) pour pouvoir poser l'ossature.

## Mode de pose d'une ouverture

| Mode | Définition | Conséquence sur le revêtement |
|---|---|---|
| **Applique** (surface-mounted) | Le dormant est posé contre le nu du mur existant | Le parement s'arrête au contour de l'ouverture : pas de retour |
| **Tunnel** | Le dormant est en retrait dans l'épaisseur du mur (embrasure) | Le parement fait un **retour vers l'ouverture** : bandes de parement sur le tour de l'ouverture |

En tunnel, la profondeur du retour dépend de l'épaisseur du complexe posé devant le support (isolant,
ossature, peaux de parement) et du refoncement du dormant. Les côtés concernés : linteau et deux
montants pour une porte, plus l'appui (tablette) pour une fenêtre. Voir `04-platrerie.md`.

## Précision

Millimètres entiers pour les positions et dimensions ; les angles et pentes sont calculés en flottant
puis arrondis au mm. Comparaisons géométriques avec une tolérance (par ex. 1 mm). Les flottants en mètres
du code actuel (`0.001` dans les boucles) sont source d'écarts cumulés.
