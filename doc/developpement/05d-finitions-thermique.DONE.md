# Étape 05d — Finitions, retours, thermique et consommables

> Statut : terminée (vérifiée le 2026-11-06 : phpunit, phpstan niveau 8, schéma en phase). Calculs seuls : l'affichage (aperçu 2D des retours, R par couche, RT, U, objectif, delta) n'est pas fait, voir « Décisions et écarts ». Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

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

- [x] Le R total d'un mur de test (paroi existante + 100 mm de laine λ 0,035 + BA13) est conforme au calcul manuel.
- [x] Ouverture en tunnel : des bandes de retour (linteau, côtés, appui pour une fenêtre) de largeur `profondeurRetour` s'ajoutent aux plaques, joints et vis ; en applique, rien n'est ajouté.
- [x] Changer le mode de pose d'une ouverture (applique ↔ tunnel) change les quantités sans autre modification.
- [x] Enduit : l'enduit de joint est compté par surface de plaque ; sans couche `ratissage`, seul le joint est compté ; avec `ratissage`, la consommation vaut épaisseur × `consommation_kg_par_m2_par_mm`, arrondie au lot.
- [x] Chevilles : nombre = longueur de profilé fixé ÷ pas de l'article + fixations de périphérie ; bande résiliente : rouleaux = `ceil(longueur × (1 + marge) / longueur_rouleau)`.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Tests : `tests/Calcul/FinitionsThermiqueTest.php` (pipeline réel, doublage série 70, recalcul manuel), `tests/Calcul/CalepinageBandesTest.php`, cas cloison de `ResistanceThermiqueTest`.

**Moteur modifié (nécessité)**
- `ContexteCouches` reçoit des champs et withers : `profondeurOssature`, `railsSupport` (publiés par `CalculateurOssature`), `surfaceParementMm2`, `anglesSortantsMm`, `anglesRentrantsMm`. `ResultatPipeline`, `ResultatSysteme` et `CalculSysteme` exposent le R par couche (`resistances`, clé : ordre de la couche) ; une couche sans R n'a pas d'entrée.
- `ResistanceThermique::rse()` renvoie le Rsi pour `CloisonFace` (spécification : Rsi des deux côtés). Conséquence assumée sur les étapes précédentes : le RT d'une cloison dans la comparaison de variantes passe de 2170 à 2260 millièmes pour 2000 de R système (U 461 -> 442) ; `CloisonsDoublagesTest` est mis à jour en conséquence. Aucun autre résultat des étapes 00 à 05c ne change (tests de caractérisation identiques).

**Calcul**
- `ThermiqueCalculator::resistanceTotale(TypeSurface, rParoiExistante, rCouches)` a une signature pure (entiers) au lieu de `(Surface, ParoiExistante, list<Couche>, Rsi, Rse)` : le R de chaque couche est calculé par le pipeline (`IsolantView`, R certifié prioritaire) et le type de surface suffit pour Rsi/Rse. Il renvoie un `BilanThermique` (R par couche, RT, U, delta à l'objectif). L'objectif de résistance n'est pas persisté : le delta se calcule à partir d'une valeur passée en paramètre.
- R d'une plaque de plâtre = 0 : le catalogue n'a pas de lambda pour les plaques. Une membrane vaut 0 (spécification). Pas de pont thermique d'ossature (option v2).
- Retour de parement : porté par la dernière peau (peau visible) de `CalculateurParement` ; profondeur = max(profondeur d'ossature, épaisseur cumulée devant le support) + parement prévu + refoncement, ou paramètre `retour_profondeur` (surcharge). Un avertissement `reveal_deeper_than_framing` est émis quand la profondeur dépasse cette capacité. Les clés de caractéristiques `retour_*` ne sont ajoutées que si elles ne sont pas nulles, pour ne pas modifier les lignes existantes. Bandes : linteau, côtés, appui si l'ouverture ne descend pas au sol ; plaques par `CalepinageBandes` (rangées dans la longueur de la plaque, coupes 1D ; bande plus large que la plaque comptée à la surface) ; vis : une rangée au pas de la peau le long de chaque bande ; la surface des bandes s'ajoute à la surface de parement (enduit). Sur un plafond (`CalculateurOssaturePlafond` ne publie pas la profondeur d'ossature) la profondeur se rabat sur l'épaisseur cumulée.
- Cloisons : le retour de cloison reste calculé au niveau du système (`RetourTunnel`, étape 05b) ; `CalculateurParement` ignore les `CloisonFace` pour ne pas compter deux fois. Les joints et l'enduit ne sont pas ajoutés pour les retours de cloison.
- Joints d'angle (`CalculateurBandeJoint`, à placer après le parement) : bande `armee` pour les angles sortants (longueur du contour du retour), `plat` ou `papier` pour les rentrants ; rouleaux si l'article donne `longueur_rouleau`, sinon longueur en mm. La cornière n'est pas gérée (bande armée uniquement).
- Enduit (`enduit.plaques`) : emplacements `enduit_joint` et `enduit_ratissage`, deux couches distinctes ; kg arrondis au kilo supérieur (le sac est `unites_par_lot`, arrondi par `OptimiseurBesoins`). Joint = `consommation_kg_par_m2` par m2 de parement (retours en tunnel compris) ; ratissage = épaisseur de la couche (obligatoire, sinon erreur `plaster_thickness_missing`) x `consommation_kg_par_m2_par_mm`. Valeurs décimales du catalogue converties en entiers (grammes). Sur une cloison, il faut une couche d'enduit par face pour le compter sur chaque face (une couche sans face est comptée une fois).
- Membrane (`membrane.rouleaux`) : rouleaux = `ceil(surface nette / ((largeur - recouvrement) x longueur))`, équivalent à la formule de la spécification ; recouvrement de l'article (`recouvrement_mm`, 100 mm par défaut). Adhésif en mm : jonctions de lés (lés verticaux) + périphérie, sans catégorie de catalogue dédiée donc ligne de longueur sans article.
- Chevilles (`fixation.support`) : `ceil(longueur de profilé / pas)` (pas = `pose.pas_mm` ou 1000 / `pose.nombre_par_m`) + périphérie = 2 par pièce de rail/lisse (extrémités), modifiable par le paramètre de couche `fixations_par_extremite`. Bande résiliente (`bande.resiliente`) : paramètre `marge_bande_resiliente_pct`, 0 par défaut (la marge de sécurité du matériau relève de l'agrégation, étape 05e).
- Les nouveaux emplacements (min 0, donc aucun avertissement s'ils manquent) sont ajoutés aux trois systèmes : `enduit_joint`, `enduit_ratissage`, `membrane`, `bande_joint` (0..2), et `fixation_support`, `bande_resiliente` pour la cloison et le doublage ; paramètres `retour_profondeur` et `marge_bande_resiliente_pct`. L'enduit de joint est donc facultatif (non obligatoire comme dans la spécification) pour ne pas changer les systèmes existants.
- Messages : 7 codes ajoutés à `MessagesCalcul` et traduits dans `translations/validators.fr.xlf` (`mc31` à `mc37`).

**Non fait / non vérifié**
- Aucune interface : l'aperçu 2D des retours et l'affichage R par couche, RT, U, objectif, delta ne sont pas faits ; rien n'a été vérifié dans un navigateur. Le critère « changer le mode de pose » est vérifié au niveau du moteur (même surface, seule la pose de l'ouverture change), pas par l'écran.
- Pas d'intégration base de données dédiée aux nouveaux calculateurs (pipeline testé avec des articles de test).
