# Étape 05d — Finitions, retours, thermique et consommables

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Compléter les calculs : retour de parement des ouvertures, isolant et thermique R/U, membrane, enduit (joint et ratissage), chevilles et bande résiliente.

## Périmètre

Retour de parement, `ThermiqueCalculator` (R, RT, U, Rsi des deux côtés pour les cloisons), membrane, enduit, fixations.

## Prérequis

Étapes 05a et 05b terminées.

## Livrables

Calculateurs de couche correspondants, `ThermiqueCalculator`, tests de calcul manuel.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

### Retour de parement (ouvertures en tunnel)

Une ouverture en pose `tunnel` impose un **retour de parement** ; en pose `applique`, rien.

Calcul (calculateur de la couche parement, avec accès au `ContexteCouches`) :
- `profondeurRetour = épaisseur cumulée du complexe devant le support + refoncement` de l'ouverture
  (règle validée ; surcharge manuelle possible) ;
- côtés : linteau + côtés (et appui pour une fenêtre) ; chaque côté est une **bande** de largeur
  `profondeurRetour` et de longueur celle du côté de l'ouverture (+ chevauchement éventuel) ;
- les bandes entrent dans le **calepinage des plaques** comme pièces supplémentaires (même type de plaque
  que le parement, peau visible) et dans l'optimisation de coupes ;
- joints : angles sortants (retour/parement) en bande armée ou cornière, angles rentrants (retour/appui) en
  bande à joint plat ; ils sont ajoutés aux besoins en joints ;
- ossature d'encadrement : rails/montants d'ouverture (déjà présents pour les linteaux) suffisent à porter
  le retour tant que la profondeur ne dépasse pas la capacité du système (sinon avertissement) ;
- visserie : les bandes de retour comptent dans la surface de peau pour le calcul des vis.

L'aperçu 2D du mur affiche ces retours ; le mode de pose est modifiable à tout moment par ouverture
(voir `_interface-principes.md`).


### Isolant et thermique

- `IsolantView` : λ, épaisseur, R certifié (prioritaire sur `e/λ`).
- `ThermiqueCalculator::resistanceTotale(Surface, ParoiExistante, list<Couche>, Rsi, Rse)` ; Rsi/Rse selon
  le type de surface, constantes documentées. **Cloison / `cloison_face`** : Rsi des **deux côtés** (pas de Rse, deux ambiances intérieures) ; le résultat sert à l'acoustique et au confort, pas à l'objectif d'isolation de paroi extérieure.
- Option v2 : pont thermique d'ossature (fractions de surface).
- Affichage : R par couche, RT, U, objectif saisi, delta.

### Membrane

Surface = surface isolée ; rouleaux = `ceil(surface × (1 + recouvrement) / surface utile du rouleau)` ;
adhésif en ml selon jonctions de lés et périphérie ; R = 0. La continuité du pare-vapeur (raccords aux
jonctions, passages de gaines) n'est pas contrôlée en v1 : elle est couverte par la **marge de sécurité en
% de surface**, comme pour les autres matériaux (voir « Marge de sécurité et arrondi aux lots »).

### Enduit : joint et ratissage

Deux usages distincts de la catégorie `enduit`, chacun avec sa propre consommation :
- `joint` (obligatoire) : traitement des joints entre plaques ; consommation en kg par m² de plaque (ou par
  ml de joint) donnée par l'article ;
- `ratissage` (**facultatif**) : enduit sur toute la surface ; l'**épaisseur est choisie par l'utilisateur**
  (paramètre de la couche, en mm) et la consommation vaut `épaisseur × consommation_kg_par_m2_par_mm` de l'article
  (`consommation_kg_par_m2_par_mm`, plus élevée pour un ratissage épais).

Quantité = surface de parement × consommation, puis marge et arrondi au lot (sacs). Le ratissage est une couche
optionnelle (cardinalité `0..1`) ; sans elle, seul l'enduit de joint est compté.

### Chevilles et bande résiliente

- `fixation_support` (chevilles, vis à béton) : nombre = longueur de profilé fixé ÷ `pas` de l'article, plus
  les fixations de périphérie ;
- `bande_resiliente` : longueur = longueur de rail/lisse au contact du support ; rouleaux = `ceil(longueur ×
  (1 + marge) / longueur_rouleau)`.

## Critères d'acceptation

- [ ] Le R total d'un mur de test (paroi existante + 100 mm de laine λ 0,035 + BA13) est conforme au calcul manuel.
- [ ] Ouverture en tunnel : des bandes de retour (linteau, côtés, appui pour une fenêtre) de largeur `profondeurRetour` s'ajoutent aux plaques, joints et vis ; en applique, rien n'est ajouté.
- [ ] Changer le mode de pose d'une ouverture (applique ↔ tunnel) change les quantités sans autre modification.
- [ ] Enduit : l'enduit de joint est compté par surface de plaque ; sans couche `ratissage`, seul le joint est compté ; avec `ratissage`, la consommation vaut épaisseur × `consommation_kg_par_m2_par_mm`, arrondie au lot.
- [ ] Chevilles : nombre = longueur de profilé fixé ÷ pas de l'article + fixations de périphérie ; bande résiliente : rouleaux = `ceil(longueur × (1 + marge) / longueur_rouleau)`.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
