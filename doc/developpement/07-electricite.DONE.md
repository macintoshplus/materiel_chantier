# Étape 07 — Réseaux : électricité

> Statut : terminée (vérifiée le 2026-11-06 : phpunit, phpstan niveau 8, schéma en phase). Import des blocs DXF différé sur décision de l'utilisateur ; écrans à voir en navigateur. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Placer des équipements électriques, calculer longueurs de câble et de gaine, quantités annexes.

## Périmètre

`Equipement`, `Reseau` générique, cheminement, quantités annexes, contrôles, import des blocs DXF.

## Prérequis

Étapes 03 et 05e terminées.

## Livrables

Entités et migration, calcul de cheminement, nomenclature annexe, écran Equipment.

## Spécification

Connaissance : [07-electricite.DONE.md](../connaissances/05-electricite.md). Dépend des phases géométrie et
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

- [x] Pièce rectangulaire avec 1 tableau, 1 interrupteur, 1 point lumineux : longueur de gaine/câble conforme au calcul manuel ± réserve.
- [x] Va-et-vient : le nombre de conducteurs entre commandes est de 3 + terre.
- [x] Les quantités annexes apparaissent dans la nomenclature du projet.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Tests : `tests/Calcul/Electricite/CheminementCalculatorTest.php` (calcul pur), `tests/Integration/ElectriciteTest.php` (base : persistance, nomenclature, va-et-vient, chiffrage, contrôles).

- **Cheminement v1 uniquement** (par pièce, orthogonal). Entre pièces : passage par le haut de la porte la plus proche dans chaque pièce, sans topologie (avertissement `electrical_no_door` s'il n'y en a pas). Le graphe (v2) n'est pas fait.
- Câble = longueur x (100 + réserve) / 100 arrondie, + 2 x 200 mm de queue par tronçon ; la réserve est de 10 % (`reserve_pct` du réseau). La marge de catalogue de 5 % du moteur s'y ajoute (ex. une barre de gaine + 5 % = 2 lots achetés, comportement existant inchangé).
- `taux_remplissage_max` est stocké à titre d'information ; le choix du diamètre ne s'appuie que sur `conducteurs_max` (déjà déduit de ce taux). Sans diamètre admissible : erreur bloquante `electrical_no_conduit`. Pas de partage de gaine entre tronçons.
- Nombre de conducteurs = actifs + terre (3G1.5 = 3 ; va-et-vient entre commandes : 3 actifs + terre = 4).
- Fixations : pas de catégorie dédiée, donc `electrique_connecteur` de type `fixation` (1 par 300 mm de gaine). Connecteurs `wago` : 1 par conducteur entrant d'une boîte de dérivation.
- Seule modification du moteur : `BesoinsSupport::$support` devient nullable (support null = installation électrique de la pièce, libellé « Electrical installation »), `ChiffrageService` et `CalculProjet` adaptés. Résultats 05a-06 inchangés (suite complète verte).
- Types d'éléments : paramètre `app.electricite.types` (config/services.yaml), extensible par données.
- Contrôles : constantes de `ControleElectricite` (3 prises, 1 point lumineux, 230 V, 100 mm aux ouvertures, entraxe d'ossature 600 par défaut), surchargeables par `Projet.parametres['electricite']`.
- **Écran Equipement** (fait) : générique par famille. `TypesEquipement` (registre des types par famille : électricité depuis `app.electricite.types`, autres familles depuis `app.reseaux.types`, vide pour l'instant) ; routes `/rooms/{piece}/walls/{surface}/equipments/{famille}/new`, `/equipments/{id}/edit`, `/equipments/{id}/delete`. Pour l'étape 08 il suffit de remplir `app.reseaux.types` pour plomberie et ventilation. Marqueurs sur l'aperçu de la surface et de la pièce (`ApercuSurface`, `ApercuPiece`). Libellés de types dans `messages+intl-icu.fr.xlf`.
- **Écran des circuits** (fait) : `/projets/{id}/reseaux/{famille}` (liste avec éléments, longueurs de gaine et de câble, quantités annexes, avertissements), `/projets/{id}/reseaux/{famille}/new`, `/reseaux/{id}/edit`, suppression en POST avec jeton CSRF. Limité à la famille électrique : les autres familles répondent 404 car le formulaire dépend de l'énumération `TypeCircuit` (point d'extension de l'étape 08).
- **Écart au périmètre « moteur inchangé »** : `ResultatElectricite` expose en plus `cheminementParReseau` et `elementsParReseau` (clés `spl_object_id` du réseau), remplis par `ElectriciteProjet::analyser()`, pour afficher les longueurs et les quantités par circuit. Aucune modification de `src/Calcul`. Ajout de `Piece::retirerEquipement()`.
- Les contrôles « boîte sur montant d'ossature » et « distance mini aux ouvertures » sont couverts par `tests/Integration/ElectriciteTest.php` ; l'écran par `tests/Controller/EquipementControllerTest.php`.
- **Différé sur décision de l'utilisateur (2026-11-06)** : l'import des blocs DXF comme équipements. À reprendre dans une étape ultérieure ; les messages `import_equipment_*` sont déjà préparés.
