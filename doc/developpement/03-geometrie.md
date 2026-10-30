# Géométrie et import DXF (code)

Connaissances : [02-geometrie.md](../connaissances/02-geometrie.md) et [07-import-dxf.md](../connaissances/07-import-dxf.md).

## Bibliothèque géométrique `src/Geometry`

Classes pures, sans Doctrine, entièrement testées.

| Classe | Rôle |
|---|---|
| `Point2D(int $x, int $y)` / `Point3D` | mm |
| `Polygone` | liste de `Point2D` ; `aire()`, `perimetre()`, `estSimple()`, `sensAntiHoraire()`, `contient(Point2D)`, `contientPolygone()`, `intersecte()`, `boiteEnglobante()`, `decouperEnBandesVerticales(int $pas)` |
| `Plan` | origine 3D + vecteurs `u`, `v` ; `versPiece(Point2D): Point3D`, longueur réelle d'un segment 3D |
| `GenerateurMurs` | `Polygone` au sol + hauteur → liste de `Surface` murs (une par arête), jonctions angulaires |
| `ServiceJonctions` | relie deux arêtes, vérifie la longueur égale, calcule le type d'angle |
| `PlacementSurSurface` | positionne un rectangle/polygone dans une surface et vérifie l'inclusion/chevauchement (remplace `OuvertureOverlapChecker` et `PositionsIsValid`) |

Tolérance géométrique : constante unique (1 mm). Aucun `float` pour les positions persistées.

### Hauteur utile par position

`Surface::hauteurUtileEn(int $u): int` pour les murs non rectangulaires. Les calculateurs d'ossature
l'utilisent à la place de la hauteur constante actuelle.

## Import DXF `src/Import/Dxf`

Architecture en trois étages, chacun testable séparément :

1. **`DxfReader`** : lit un fichier ASCII (paires code/valeur), produit un `DxfDocument` neutre
   (sections, calques, entités). Protections : taille max (ex. 10 Mo), nombre max d'entités, rejet des
   DXF binaires avec message clair, encodage (CP1252/UTF-8). Évaluer d'abord une bibliothèque PHP maintenue ;
   à défaut, écrire un lecteur minimal limité aux entités du tableau ci-dessous.
2. **`DxfInterpreter`** : convertit en structure neutre `PlanImporte` (indépendante du DXF) :
   - polylignes en mm avec calque et indicateur fermé,
   - `LINE` chaînées avec tolérance en polylignes,
   - `ARC`/bulge approximés en segments (pas angulaire/flèche configurable),
   - blocs `INSERT` résolus (position, rotation, échelle, nom, attributs),
   - conversion d'unités via `$INSUNITS` + choix manuel.
3. **`PieceImportService`** : à partir de `PlanImporte` et d'un `ChoixImport` (contour retenu, mode
   « intérieur » ou « axe + épaisseur de mur », associations calque → rôle, hauteur sous plafond),
   crée `Piece`, murs (via `GenerateurMurs`), ouvertures et éléments électriques proposés.

Entités prises en charge en v1 : `LWPOLYLINE`, `POLYLINE`/`VERTEX` 2D, `LINE`, `ARC`, `CIRCLE` (approx.),
`INSERT`/`BLOCK`, `TEXT`/`MTEXT` (nom de pièce). Tout le reste est listé comme « ignoré » dans le rapport.

### Parcours utilisateur

1. Téléverser un `.dxf` sur un projet.
2. Choix de l'unité (préremplie depuis l'entête) et des calques utiles.
3. Aperçu SVG des contours détectés ; sélection du contour de la pièce ; correction du sens ; option
   « décaler vers l'intérieur » (épaisseur de mur).
4. Aperçu des blocs reconnus (portes, fenêtres, électrique) avec correspondance modifiable ; saisie de
   la hauteur sous plafond.
5. Confirmation → `Piece` créée, rapport d'import (entités ignorées, avertissements, écarts de chaînage).

Plusieurs pièces dans un même fichier : le parcours se répète par contour sélectionné.

## Critères d'acceptation

- Un rectangle, une forme en L et une pièce à pan coupé s'importent avec la bonne aire (± 0,1 %).
- Contour dessiné en `LINE` avec jeux de 2 mm : détecté fermé avec tolérance 5 mm.
- Unité mal renseignée : l'utilisateur la corrige sans réimporter.
- Fichier trop gros, binaire ou corrompu : message d'erreur, aucun enregistrement partiel.
- Tests avec des fichiers DXF d'exemple versionnés dans `tests/fixtures/dxf/` (rectangle, L, arc,
  blocs de porte, lignes non jointes, unités en pouces).
- Jonctions : un L génère 6 murs, angle rentrant détecté ; longueur de jonction égale des deux côtés.
