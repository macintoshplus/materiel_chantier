# Électricité (code)

Connaissance : [05-electricite.md](../connaissances/05-electricite.md). Dépend des phases géométrie et
systèmes.

## Modèle

- `ElementElectrique` : pièce, surface porteuse, position `(u, v)` dans la surface, hauteur de pose,
  type, circuit. Types extensibles via un catalogue de **types d'éléments** (code, libellé, famille,
  conducteurs requis, matériaux associés : boîte, mécanisme, plaque).
- `Circuit` : projet, nom, type (éclairage, prises, chauffage, cuisson, spécialisé), section, protection.
- `PointDepart` : tableau ou boîte de dérivation (position dans une pièce).
- Liaisons : un circuit est un **arbre ou une chaîne** d'éléments ; pour les va-et-vient, la liaison
  entre commandes est un tronçon à 3 conducteurs + terre (`TronconNavette`).

## Calcul de cheminement

`CheminementCalculator` :
1. Construit un **graphe de cheminement** : nœuds = éléments, points de départ, points de passage de pièce
   à pièce (ouvertures, saignées) ; arêtes = tronçons le long des surfaces (descente verticale + horizontal
   le long du mur ou du plafond).
2. Calcule le chemin le plus court respectant les surfaces (Manhattan sur le développement du mur/plafond).
3. Résultat : longueur de gaine par diamètre, longueur de câble par section et par nombre de conducteurs,
   avec réserve (paramètre, défaut 10 %) et queue de 0,20 m par boîte.

Version 1 (simple) : distance orthogonale pièce par pièce (tableau → plafond de la pièce → descente à
chaque élément), sans graphe ; version 2 : graphe avec points de passage.

## Quantités annexes

Boîtes (1/élément + dérivation), mécanismes, plaques, connecteurs, gaine, fixations. Tout vient du
catalogue (catégories `electrique_*`) et alimente la même nomenclature que le reste.

## Contrôles (avertissements, non bloquants)

Nombre minimal de prises ou de points lumineux par pièce, charge d'un circuit (somme des puissances),
boîte posée sur un montant d'ossature, distance mini aux ouvertures.

## Import DXF

Les blocs électriques d'un DXF sont proposés comme éléments (voir `03-geometrie.md`) avec position ;
type à confirmer par l'utilisateur.

## Critères d'acceptation

- Pièce rectangulaire avec 1 tableau, 1 interrupteur, 1 point lumineux : longueur de gaine/câble
  conforme au calcul manuel ± réserve.
- Va-et-vient : le nombre de conducteurs entre commandes est de 3 + terre.
- Les quantités annexes apparaissent dans la nomenclature du projet.
