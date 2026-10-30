# Plâtrerie : cloisons, doublages, plafonds

Références : DTU 25.41 (ouvrages en plaques de plâtre), DTU 25.42 (doublages et habillages), DTU 25.51
(plafonds), **à vérifier** avant de figer les règles.

## Matériel courant

| Élément | Remarques |
|---|---|
| Plaque de plâtre (BA13) | 1,20 m de large, hauteurs 2,00 à 3,00 m ; types : standard, hydro, feu, haute dureté, acoustique |
| Rail (R48/R70…) | Fixé sol/plafond ; 3 m en général |
| Montant (M48/M70…) | Entraxe 0,60 m (0,40 m pour certains parements) |
| Fourrure | Ossature de doublage / plafond (F530), entraxe ≈ 0,60 m |
| Appui intermédiaire | Fixe l'ossature de doublage au mur, répartition ≈ tous les 1,20 m |
| Suspente / tige | Plafond suspendu : ≈ 1 suspente par 0,60–1,20 m² selon charge |
| Bande à joint | Papier/armée, rouleau de 30 ou 75 m |
| Enduit, visserie | Au m² de plaque : visserie ≈ 15 vis/m² ; enduit ≈ 0,3–0,5 kg/m² |

## Systèmes

### Cloison (sur ossature)
Rails haut et bas, montants à l'entraxe, parement 1 ou 2 peaux de chaque côté, isolant entre montants
(optionnel). Autour de chaque ouverture : montants doublés, **linteau** (rail horizontal) avec
sur-longueur de fixation (le code ajoute 0,20 m).

### Doublage (sur ossature, contre un mur existant)
Mêmes éléments, plus **appuis intermédiaires** (hauteur de référence 1,20 m dans le code) et rails
porteurs d'appuis. Isolant entre ossature. Pour un doublage collé, il n'y a pas d'ossature : plots de colle
(mortier adhésif) ou complexe collé (voir catalogue).

### Plafond suspendu sous plaque
Ossature de fourrures suspendues par suspentes ; primaires et secondaires selon fabricant.

### Plafond autoportant
Pas de suspentes, rails et montants/fourrures rattachés aux murs, portée limitée (≈ 3–4 m selon profil
et charge). **Hors périmètre v1 de calcul**, mais modélisé dans le catalogue de systèmes.

### Lambris sur lattes
Lattes entraxe ≈ 0,40–0,50 m, lames posées perpendiculairement ; calcul en m² de lames avec chute de
10–15 %, lattes en ml.

### Autres
La liste des systèmes est **extensible** : un système n'est pas du code figé, c'est une définition
(couches + règles) rattachée à un calculateur (voir `developpement/04-systemes-et-calcul.md`).

## Règles de calcul de base

- **Montants** : positions à `x = k × entraxe` depuis l'origine de l'ossature, + montant de fin, + montants
  doublés de chaque côté d'une ouverture ; aucun montant dans la largeur d'une ouverture, sauf
  petits montants au-dessus/au-dessous (impostes, allèges).
- **Rails** : bas, haut, + linteau (et seuil pour une fenêtre) avec sur-longueur de fixation.
- **Plaques** : calepinage en rangées à joints décalés (≥ 0,40 m), découpe autour des ouvertures ;
  surface utilisée vs surface achetée → chutes réutilisables.
- **Joints** : bande sur chaque jonction de plaques ; joint « armé » (angles sortants) vs plat.
- **Optimisation de coupes** : problème de découpe 1D (bin packing). Heuristique actuelle : tri
  décroissant + first-fit, suffisante tant que l'on affiche la chute.
- **Lots** : on achète des lots entiers : `ceil(quantité / unité par lot)`.

## Contraintes pratiques

Épaisseur de l'ensemble (réduction de surface habitable), hauteur sous plafond mini, continuité du
pare-vapeur, jeu de 10 mm entre plaque et sol, étanchéité à l'air.
