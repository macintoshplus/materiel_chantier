# Géométrie et sources de mesures (code)

Connaissances : [02-geometrie.md](../connaissances/02-geometrie.md),
[07-import-dxf.md](../connaissances/07-import-dxf.md),
[09-releves-mesures.md](../connaissances/09-releves-mesures.md).

Les murs courbes ne sont **pas pris en charge** : toute surface est plane, tout contour est un polygone.

## Bibliothèque géométrique `src/Geometry`

Classes pures, sans Doctrine, entièrement testées.

| Classe | Rôle |
|---|---|
| `Point2D(int $x, int $y)` / `Point3D` | mm |
| `Polygone` | liste de `Point2D` ; `aire()`, `perimetre()`, `estSimple()`, `sensAntiHoraire()`, `contient(Point2D)`, `contientPolygone()`, `intersecte()`, `boiteEnglobante()` |
| `Plan` | origine 3D + vecteurs `u`, `v` ; `versPiece(Point2D): Point3D`, longueur réelle d'un segment 3D, angle de pente |
| `GenerateurMurs` | `Polygone` au sol + hauteur → liste de `Surface` murs (une par arête), jonctions angulaires |
| `GenerateurPlafond` | pièce + description du toit (plat, double pente, comble aménagé) → liste de pans `plafond_pan` et jonctions (faîtage, noue, sablière) |
| `ServiceJonctions` | relie deux arêtes, vérifie la longueur égale, calcule le type d'angle |
| `PlacementSurSurface` | positionne un rectangle/polygone dans une surface et vérifie l'inclusion/chevauchement (remplace `OuvertureOverlapChecker` et `PositionsIsValid`) |

Tolérance géométrique : constante unique (1 mm). Aucun `float` pour les positions persistées.

### Calcul dans le repère de la surface (murs ET plafonds)

Chaque calculateur d'ossature travaille dans le **repère `(u, v)` de la surface**, jamais en projection
horizontale. Cela couvre :
- un mur : `v` vertical ;
- un pan de plafond incliné : `v` suit la pente, les longueurs de fourrures et de montants sont les
  longueurs réelles ;
- un mur de pignon ou de genou (polygone non rectangulaire).

Pour une direction d'ossature donnée (verticale d'un mur, perpendiculaire à l'égout d'un pan,
ou imposée par le système), l'ossature est placée à `entraxe` le long de `u`, et la **longueur de
chaque élément** est la longueur de l'intersection de la droite avec le polygone moins les
ouvertures. Fonction : `Surface::coupes(Direction $direction, int $pas): list<Segment>`.
Elle remplace l'idée d'une hauteur constante et couvre les plafonds sans cas particulier.

## Sources de mesures `src/Import`

Toutes les sources produisent la **même structure neutre** `PlanImporte` (contours en mm, blocs
positionnés), consommée par `PieceImportService`. Trois sources :

| Source | Classe | Contenu |
|---|---|---|
| Saisie par côtés et angles | `SaisieCotesAngles` | Liste ordonnée de longueurs et d'angles (ou de longueurs + équerrage) → polygone fermé, avec écart de fermeture affiché |
| DXF | `Dxf\DxfReader`, `Dxf\DxfInterpreter` | Contours et blocs d'un plan |
| CSV de relevé laser | `Csv\ReleveCsvReader` | Points (x, y) ou séries de cotes exportés d'un télémètre/appli |

`ChoixImport` (contour retenu, mode intérieur/axe, associations calque → rôle, hauteur sous plafond)
et `PieceImportService` ne changent pas selon la source.

### DXF

1. **`DxfReader`** : lit un fichier ASCII (paires code/valeur), produit un `DxfDocument` neutre.
   Protections : taille max (ex. 10 Mo), nombre max d'entités, rejet du DXF binaire avec message clair,
   encodage (CP1252/UTF-8). Évaluer d'abord une bibliothèque PHP maintenue ; à défaut, un lecteur minimal
   limité aux entités ci-dessous.
2. **`DxfInterpreter`** : convertit en `PlanImporte` : polylignes avec calque et indicateur fermé,
   `LINE` chaînées avec tolérance, blocs `INSERT` résolus (position, rotation, échelle, nom, attributs),
   conversion d'unités via `$INSUNITS` + choix manuel.
3. Entités prises en charge : `LWPOLYLINE`, `POLYLINE`/`VERTEX` 2D, `LINE`, `INSERT`/`BLOCK`, `TEXT`/`MTEXT`.
   `ARC`, `CIRCLE` et les bulges (**courbes**) : le contour est refusé ou signalé en avertissement,
   aucune approximation. Le reste est listé comme « ignoré » dans le rapport.

### CSV de relevé laser

Format accepté, documenté dans l'écran d'import : soit colonnes `x;y` (mm ou m, séparateur `;` ou `,`),
soit colonnes `cote;angle`. Unités et séparateur à confirmer par l'utilisateur. Rejet des lignes
invalides avec numéro de ligne.

### Saisie par côtés et angles

Formulaire dynamique (Stimulus) : tableau de côtés (mm) et d'angles (degrés, par défaut 90°). L'aperçu
SVG se met à jour ; l'écart de fermeture est affiché (et un bouton « fermer automatiquement » répartit
l'écart en dessous d'un seuil).

### Parcours utilisateur commun

1. Choisir la source (saisie, DXF, CSV) sur un projet.
2. Pour DXF/CSV : téléverser, choisir l'unité (préremplie si possible), les calques utiles (DXF).
3. Aperçu SVG des contours ; sélection du contour de la pièce ; option « décaler vers l'intérieur »
   (épaisseur de mur) ; hauteur sous plafond.
4. Aperçu des blocs reconnus (portes, fenêtres, équipements) avec correspondance modifiable.
5. Confirmation → `Piece` créée, rapport (entités ignorées, avertissements, écarts de chaînage).

## Critères d'acceptation

- Rectangle, forme en L, pièce à pan coupé : aire correcte (± 0,1 %), quelle que soit la source.
- DXF : contour dessiné en `LINE` avec jeux de 2 mm détecté fermé avec tolérance de 5 mm.
- DXF contenant un arc : message explicite « murs courbes non pris en charge », aucun import silencieux.
- Unité mal renseignée : corrigeable sans réimporter.
- Fichier trop gros, binaire ou corrompu : message d'erreur, aucun enregistrement partiel.
- Fixtures versionnées dans `tests/fixtures/dxf/` et `tests/fixtures/csv/`.
- Un L génère 6 murs avec un angle rentrant détecté ; longueur de jonction égale des deux côtés.
- Plafond double pente : 2 pans, longueur d'une fourrure sur pente = longueur réelle (Pythagore, ± 1 mm).
- Un mur pignon (pentagone) donne des montants de longueurs différentes selon leur position.
