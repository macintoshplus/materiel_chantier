# Électricité

Référence : NF C 15-100 (installation électrique basse tension des logements). Les valeurs ci-dessous
sont des ordres de grandeur à **valider** avant implémentation.

## Éléments placés dans une pièce

| Famille | Exemples | Particularités |
|---|---|---|
| Commande | interrupteur simple allumage, double, va-et-vient, permutateur, poussoir, variateur | Hauteur de pose habituelle ≈ 1,10 m |
| Prises | 16 A 2P+T, spécialisées (32 A, cuisson), RJ45, TV | Hauteur ≈ 0,05 m du sol (mini) ou 0,90–1,10 m ; quantités minimales par pièce |
| Points lumineux | plafonnier, applique, spot encastré | Position au plafond ou sur un mur |
| Chauffage | radiateur électrique, sèche-serviettes | Circuit dédié, position fixée par un mur |
| Distribution | tableau, boîte de dérivation, DCL | Points de départ/rattachement des circuits |

Un élément a : un type, une **surface porteuse** (mur, plafond, sol), une position `(u, v)` dans le repère
de cette surface (comme une ouverture), une hauteur de pose (la coordonnée `v` sur un mur), un circuit. Le tableau est lui-même un élément (type `tableau`).

## Circuits

Un **circuit** relie le tableau (ou une boîte de dérivation) à un ensemble d'éléments.

| Circuit | Section câble (ordre de grandeur) | Protection |
|---|---|---|
| Éclairage | 1,5 mm² | 16 A (ou 10 A) |
| Prises 16 A | 2,5 mm² | 20 A |
| Chauffage | 1,5 mm² (≤ 2 250 W) ou 2,5 mm² | 16 A / 20 A |
| Cuisson | 6 mm² (cuisinière/plaque) | 32 A |
| Spécialisé (lave-linge…) | 2,5 mm² | 20 A |

## Longueur de câble et de gaine

Les câbles courent dans des gaines (ICTA) encastrées dans les murs et cloisons ou courant en plafond.
Estimation par cheminement orthogonal (« Manhattan ») :

1. cheminer **le long des surfaces** : descentes verticales du plafond/de la plinthe vers l'élément, tronçons
   horizontaux le long des murs, passage d'une pièce à l'autre par un point de passage (porte, trou
   percé) ;
2. longueur de gaine d'un tronçon ≈ distance de cheminement ; câble = gaine + **réserve** (≈ 10–15 %, plus
   ≈ 0,20 m de queue à chaque boîte) ;
3. nombre de **conducteurs** selon la fonction : simple allumage = phase + retour + terre ;
   va-et-vient = 3 conducteurs entre les deux commandes + terre ; prise = phase + neutre + terre ;
4. longueur de fil par couleur/section = longueur de câble × nombre de conducteurs du type.

Quantités annexes : boîtes d'encastrement (1 par élément, boîtes de dérivation en plus), plaques, mécanismes,
connecteurs (Wago ≈ 2–3 par boîte), gaine par diamètre (16/20/25 mm), fixations.

## Contrainte avec l'ossature

Les gaines passent dans les rails/montants (trous prépercés) ou derrière l'isolant ; en doublage, le
réseau se loge dans la lame d'air / l'ossature. Les boîtes d'encastrement à placo ont des diamètres
standards (≈ 67 mm) et ne doivent pas tomber sur un montant. À long terme, la position des boîtes doit être
vérifiée par rapport à la grille de montants.
