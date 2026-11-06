# Étape 04 — Sources de mesures

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Créer une pièce à partir d'un import DXF, d'un CSV de relevé laser ou d'une saisie par côtés et angles, avec correction possible avant enregistrement.

## Périmètre

`src/Import` (DXF, CSV, côtés/angles), parcours utilisateur commun, message d'erreur explicite. Hors périmètre : DXF 3D, courbes, SVG/IFC.

## Prérequis

Étape 03 terminée.

## Livrables

Importeurs et leurs tests, fixtures `tests/fixtures/dxf/` et `tests/fixtures/csv/`, assistant d'import (état en session ou fichier temporaire).

## Spécification

Connaissances : [07-import-dxf.md](../connaissances/07-import-dxf.md), [09-releves-mesures.md](../connaissances/09-releves-mesures.md).

### Sources de mesures `src/Import`

Toutes les sources produisent la **même structure neutre** `PlanImporte` (contours en mm, blocs
positionnés), consommée par `PieceImportService`. Trois sources :

| Source | Classe | Contenu |
|---|---|---|
| Saisie par côtés et angles | `SaisieCotesAngles` | Liste ordonnée de longueurs et d'angles (ou de longueurs + équerrage) → polygone fermé, avec écart de fermeture affiché |
| DXF | `Dxf\DxfReader`, `Dxf\DxfInterpreter` | Contours et blocs d'un plan |
| CSV de relevé laser | `Csv\ReleveCsvReader` | Points (x, y) ou séries de cotes exportés d'un télémètre/appli |

`ChoixImport` (contour retenu, mode intérieur/axe, associations calque → rôle, hauteur sous plafond)
et `PieceImportService` ne changent pas selon la source.

#### DXF

1. **`DxfReader`** : lit un fichier ASCII (paires code/valeur), produit un `DxfDocument` neutre.
   Protections : taille max (ex. 10 Mo), nombre max d'entités, rejet du DXF binaire avec message clair,
   encodage (CP1252/UTF-8). Évaluer d'abord une bibliothèque PHP maintenue ; à défaut, un lecteur minimal
   limité aux entités ci-dessous.
2. **`DxfInterpreter`** : convertit en `PlanImporte` : polylignes avec calque et indicateur fermé,
   `LINE` chaînées avec tolérance, blocs `INSERT` résolus (position, rotation, échelle, nom, attributs),
   conversion d'unités via `$INSUNITS` + choix manuel.
3. Entités prises en charge : `LWPOLYLINE`, `POLYLINE`/`VERTEX` 2D, `LINE`, `INSERT`/`BLOCK`, `TEXT`/`MTEXT`.
   `ARC`, `CIRCLE` et les bulges (**courbes**) : tout contour qui en contient un est **rejeté** avec le message
   « murs courbes non pris en charge », sans approximation ; un arc ou cercle hors contour (mobilier, colonne
   isolée) est ignoré avec un avertissement. Le reste est listé comme « ignoré » dans le rapport.

#### CSV de relevé laser

Format accepté, documenté dans l'écran d'import : soit colonnes `x;y` (mm ou m, séparateur `;` ou `,`),
soit colonnes `cote;angle`. Unités et séparateur à confirmer par l'utilisateur. Rejet des lignes
invalides avec numéro de ligne.

#### Saisie par côtés et angles

Formulaire dynamique (Stimulus) : tableau de côtés (mm) et d'angles (degrés, par défaut 90°). L'aperçu
SVG et l'écart de fermeture sont rafraîchis à l'enregistrement du brouillon (bouton « Save » toujours
visible, voir `_interface-principes.md`) ; l'écart de fermeture est affiché (et un bouton « fermer automatiquement » répartit
l'écart en dessous d'un seuil).

#### Parcours utilisateur commun

1. Choisir la source (saisie, DXF, CSV) sur un projet.
2. Pour DXF/CSV : téléverser, choisir l'unité (préremplie si possible), les calques utiles (DXF).
3. Aperçu SVG des contours ; sélection du contour de la pièce ; option « décaler vers l'intérieur »
   (épaisseur de mur) ; hauteur sous plafond.
4. Aperçu des blocs reconnus (portes, fenêtres, équipements) avec correspondance modifiable.
5. Confirmation → `Piece` créée, rapport (entités ignorées, avertissements, écarts de chaînage).

## Critères d'acceptation

- [ ] Rectangle, forme en L, pièce à pan coupé : aire correcte (± 0,1 %), quelle que soit la source.
- [ ] DXF : contour dessiné en `LINE` avec jeux de 2 mm détecté fermé avec tolérance de 5 mm.
- [ ] DXF contenant un arc : message explicite « murs courbes non pris en charge », aucun import silencieux.
- [ ] Unité mal renseignée : corrigeable sans réimporter.
- [ ] Fichier trop gros, binaire ou corrompu : message d'erreur, aucun enregistrement partiel.
- [ ] Fixtures versionnées dans `tests/fixtures/dxf/` et `tests/fixtures/csv/`.
- [ ] Après un import, nom, hauteur, sommets, noms de murs sont modifiables ; les murs conservent leur nom et leurs ouvertures quand un sommet bouge.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
