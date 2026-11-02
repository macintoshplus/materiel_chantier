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
| `ConstructeurSurface` | contraintes de sommets (distance depuis un sommet, angle) → polygone `(u, v)` en mm ; voir « Surfaces construites par contraintes » |
| `GenerateurMurs` | **raccourci de création** : `Polygone` au sol + hauteur → surfaces murs rectangulaires (une par arête) avec leurs contraintes, jonctions angulaires |
| `GenerateurPlafond` | **raccourci de création** : plat ou double pente → pans (surfaces avec contraintes) et jonctions (faîtage, noue, sablière) |
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

## Surfaces construites par contraintes

Une `Surface` (mur, pan incliné, plafond horizontal : même classe, mêmes systèmes, seuls les matériaux
changent) est un polygone plan dont chaque sommet est défini par une **contrainte** : `distance` depuis un
sommet de référence (dans une direction) ou `angle` entre deux arêtes (ou avec l'horizontale). 
`ConstructeurSurface` résout les contraintes en mm entiers et produit le polygone ; les contraintes sont
conservées (`Surface.contraintes`) pour recalculer la forme quand une valeur change. Aucune hauteur unique
n'est imposée : trapèze sous rampant, pignon et plafond sont des polygones comme les autres.

`GenerateurMurs` et `GenerateurPlafond` ne sont que des raccourcis de création. **Source de vérité** : le
polygone et les contraintes de chaque surface, pas la hauteur de la pièce. Un pan est positionné par la
jonction de l'une de ses arêtes avec l'arête d'un mur et par son angle de pente. Contraintes sur-définies
ou incohérentes : erreur de validation sur le champ.

## Cloisons (code)

Une cloison est un objet **dans** une pièce (la pièce reste un volume physique). Classes pures dans
`src/Geometry` :

| Classe | Rôle |
|---|---|
| `TraceCloison` | Segment `(début, fin)` en mm (axe) ; longueur, angle, normale ; `deplacerExtremite()`, `definirLongueur()`, `decalerParRapportA(Mur\|Cloison, distance)` |
| `GenerateurFacesCloison` | Trace + `Cloison.epaisseur` (dérivée du système, voir « Épaisseur d'une cloison ») + plafond de la pièce → deux `Face` (polygone `(u, v)` ; hauteur à chaque position via `Piece::hauteurA(Point2D)` qui lit les pans de plafond) |
| `DetecteurAncrages` | Propose l'ancrage de chaque extrémité : mur, autre cloison, doublage (tolérance configurable, ex. 20 mm) sinon `libre` ; calcule la position le long de la cible |
| `JonctionsCloison` | Crée les jonctions sol / plafond / ancrages et le type correspondant (`cloison_sur_mur`, `cloison_sur_cloison`, `cloison_sur_doublage`) |
| `OuvertureCloison` | Place l'ouverture dans le repère du tracé et la projette sur chaque face (repère de A inversé par rapport au tracé, repère de B dans le sens du tracé) |

### Tracé modifiable
`CloisonEditionService` applique les modifications du tracé à tout moment (déplacement d'extrémité, longueur,
angle, décalage par rapport à un mur) :

| Modification | Effet |
|---|---|
| Déplacer une extrémité / changer la longueur | Faces régénérées ; ouvertures conservées à leur position `u` depuis le début, revalidées (hors cloison = erreur signalée, pas supprimée) ; ancrages recalculés par `DetecteurAncrages` (l'utilisateur peut les forcer) |
| Décaler la cloison (parallèlement) | Nouvelle position ; mêmes règles ; montants et rails recalculés au prochain calcul |
| Une extrémité sort du volume de la pièce | Erreur de validation sur le tracé |
| Une autre cloison est ancrée sur celle-ci | Son ancrage suit ; si la cible n'existe plus à cet endroit, `libre` avec avertissement |
| Renommer la cloison ou une face | Aucun impact géométrique |

Un `PlanModification` affiche les impacts avant validation quand des données (ouvertures, ancrages) sont
touchées. Tests prioritaires : génération des faces (hauteur sous plafond plat et rampant), détection
d'ancrage, conservation des ouvertures, projection sur les faces A (inversée) et B.

### Épaisseur d'une cloison
`Cloison.epaisseur` est **dérivée** du système actif, jamais saisie : parement A + ossature + parement B,
calculée en phase de planification **sans polygone** (voir `04-systemes-et-calcul.md`). Le profilé est choisi
sur la **hauteur maximale** de la cloison à l'axe. L'épaisseur est stockée, puis `GenerateurFacesCloison`
génère les faces. Changer de système, de variante active ou de profilé recalcule l'épaisseur et régénère les
faces, avec `PlanModification` quand des ouvertures ou des ancrages sont touchés.
Même règle pour une cloison ancrée sur un doublage : l'épaisseur du doublage de la **variante active** décale
l'extrémité de la cloison ; activer une autre variante du doublage régénère les cloisons ancrées (impact
affiché avant validation).

## Édition d'une pièce existante

Une pièce est modifiable à tout moment, qu'elle vienne d'une source de mesures ou d'une saisie manuelle.
`PieceEditionService` applique les modifications du sol en conservant l'identité des murs :

| Modification | Effet |
|---|---|
| Renommer la pièce ou un mur | Aucun impact géométrique |
| Déplacer un sommet | Les deux murs adjacents changent de largeur ; nom, système, couches et ouvertures sont conservés ; les ouvertures hors du mur deviennent des erreurs signalées (jamais supprimées) |
| Ajouter un sommet sur une arête | Le mur est **scindé** en deux ; les ouvertures sont réparties selon leur position `u` (celle à cheval est signalée) ; les deux nouveaux murs héritent du nom (suffixe), du système et de la paroi existante |
| Supprimer un sommet | Les deux murs sont **fusionnés** ; si leurs noms ou systèmes diffèrent, demande de confirmation ; les ouvertures sont recalées |
| Changer la hauteur sous plafond | Valeur par défaut des raccourcis de création uniquement : les surfaces existantes ne changent pas ; pour modifier un mur, on modifie la contrainte du sommet concerné (distance ou angle) |

Identité d'un mur : identifiant stable (`id` de l'entité), indépendant de l'index du sommet. Chaque
modification passe par un **plan de changements** (`PlanModification`) que l'on peut afficher avant
validation quand des données sont impactées. Tests prioritaires : conservation des murs et des
ouvertures, scission, fusion.

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
SVG et l'écart de fermeture sont rafraîchis à l'enregistrement du brouillon (bouton « Save » toujours
visible, voir `06-interface.md`) ; l'écart de fermeture est affiché (et un bouton « fermer automatiquement » répartit
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
