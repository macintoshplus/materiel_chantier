# Géométrie

## Repères

- **Repère pièce** : 3D, origine à un coin du sol, X/Y dans le plan horizontal, Z vertical vers le haut.
- **Repère surface** : 2D `(u, v)` dans le plan de la surface. Pour un mur, `u` = horizontal le long du mur
  (de gauche à droite en regardant le mur depuis l'intérieur de la pièce), `v` = vertical depuis le sol.
  Pour un pan de plafond, `u` est le long de l'égout (ligne basse), `v` suit la pente.
- Une surface a donc : un **polygone 2D** (sommets dans son repère) et une **transformation** vers le repère
  pièce (origine 3D + vecteurs `u`, `v`). La normale pointe vers l'intérieur de la pièce.

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
Pour les calculs d'ossature, on travaille **dans le repère `(u, v)` de la surface**, pas en projection
horizontale : à chaque position d'élément (montant, fourrure, latte) on coupe le polygone par une droite
dans la direction de l'ossature ; la longueur de l'élément est celle de l'intersection, ouvertures
retranchées. Les montants ont donc des longueurs différentes sur un pignon, et les fourrures d'un pan de
plafond incliné ont leur **longueur réelle** (suivant la pente), sans cas particulier pour les plafonds.

## Plafonds composés

Un plafond est un **ensemble de pans**, chacun étant une surface plane :

| Cas | Pans |
|---|---|
| plafond plat | 1 pan horizontal |
| toit double pente avec plafond sous rampant | 2 pans inclinés (+ éventuellement un plat de comble aménagé en plus) |
| comble aménagé | pans rampants + mur de genou + plafond plat central |
| mansarde | jusqu'à 4 pans |

L'angle de pente se déduit des coordonnées 3D. La longueur réelle d'un montant/fourrure sur un rampant
est la longueur suivant la pente, pas la projection horizontale.

## Ouvertures

Une ouverture est un polygone 2D (le plus souvent un rectangle ; ou cintrée/triangulaire) **défini dans le
repère de sa surface** : `position (u, v)` du coin bas-gauche + `largeur`, `hauteur` (ou polygone).
Paramètres complémentaires : type (porte, fenêtre, baie, trappe, velux), type de pose (tunnel, applique…),
contour (plaque, retour d'isolant…), refoncement. Règles de validité :

- entièrement incluse dans le polygone de la surface ;
- sans chevauchement avec une autre ouverture de la même surface ;
- distance minimale à une arête (ex. ≥ 1 épaisseur de montant) pour pouvoir poser l'ossature.

## Précision

Millimètres entiers pour les positions et dimensions ; les angles et pentes sont calculés en flottant
puis arrondis au mm. Comparaisons géométriques avec une tolérance (par ex. 1 mm). Les flottants en mètres
du code actuel (`0.001` dans les boucles) sont source d'écarts cumulés.
