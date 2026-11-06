# Étape 07 — Réseaux : électricité

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Placer des équipements électriques, calculer longueurs de câble et de gaine, quantités annexes.

## Périmètre

`Equipement`, `Reseau` générique, cheminement, quantités annexes, contrôles, import des blocs DXF.

## Prérequis

Étapes 03 et 05e terminées.

## Livrables

Entités et migration, calcul de cheminement, nomenclature annexe, écran Equipment.

## Spécification

Connaissance : [07-electricite.md](../connaissances/05-electricite.md). Dépend des phases géométrie et
systèmes.

### Modèle

- `Equipement` (famille `electrique`, socle commun décrit dans `08-plomberie-ventilation.md`) : pièce,
  surface porteuse, position `(u, v)` dans la surface (la hauteur de pose est `v` sur un mur), type, réseau. Types extensibles via un catalogue de **types d'éléments** (code, libellé, famille,
  conducteurs requis, matériaux associés : boîte, mécanisme, plaque).
- `Reseau` (famille `electrique` = circuit) : projet, nom, type (éclairage, prises, chauffage, cuisson, spécialisé), section, protection.
- Le tableau et les boîtes de dérivation sont des `Equipement` (types `tableau`, `boite_derivation`) ; il n'y a pas d'entité `PointDepart`. Le point de départ d'un `Reseau` est l'équipement désigné dans ses paramètres.
- Liaisons : un circuit est un **arbre ou une chaîne** d'éléments, stockée dans les `parametres` du `Reseau` (liste ordonnée ou parent de chaque équipement) ; pour les va-et-vient, la liaison entre commandes est un tronçon à 3 conducteurs + terre décrit dans ces données (pas d'entité `TronconNavette`).

### Calcul de cheminement

`CheminementCalculator` :
1. Construit un **graphe de cheminement** : nœuds = éléments, points de départ, points de passage de pièce
   à pièce (ouvertures, saignées) ; arêtes = tronçons le long des surfaces (descente verticale + horizontal
   le long du mur ou du plafond).
2. Calcule le chemin le plus court respectant les surfaces (Manhattan sur le développement du mur/plafond).
3. Résultat : longueur de gaine par diamètre, longueur de câble par section et par nombre de conducteurs,
   avec réserve (paramètre, défaut 10 %) et queue de 0,20 m par boîte.

**Choix du diamètre de gaine** : chaque diamètre de l'article porte un `taux_remplissage_max` (% de la section intérieure, donnée à valider) et, par section de conducteur, un nombre maximal de conducteurs (`conducteurs_max`, déduit de section intérieure × taux ÷ section du conducteur avec isolant). Le calcul retient le plus petit diamètre dont le total des conducteurs du tronçon respecte la limite ; sinon erreur bloquante.

Version 1 (simple) : distance orthogonale pièce par pièce (tableau → plafond de la pièce → descente à
chaque élément), sans graphe ; version 2 : graphe avec points de passage.

### Quantités annexes

Boîtes (1/élément + dérivation), mécanismes, plaques, connecteurs, gaine, fixations. Tout vient du
catalogue (catégories `electrique_*`) et alimente la même nomenclature que le reste.

### Contrôles (avertissements, non bloquants)

Nombre minimal de prises ou de points lumineux par pièce, charge d'un circuit (somme des puissances),
boîte posée sur un montant d'ossature, distance mini aux ouvertures.

### Import DXF

Les blocs électriques d'un DXF sont proposés comme éléments (voir `03-geometrie.DONE.md`) avec position ;
type à confirmer par l'utilisateur.

## Critères d'acceptation

- [ ] Pièce rectangulaire avec 1 tableau, 1 interrupteur, 1 point lumineux : longueur de gaine/câble conforme au calcul manuel ± réserve.
- [ ] Va-et-vient : le nombre de conducteurs entre commandes est de 3 + terre.
- [ ] Les quantités annexes apparaissent dans la nomenclature du projet.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
