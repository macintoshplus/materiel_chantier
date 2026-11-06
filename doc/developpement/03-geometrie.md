# Étape 03 — Géométrie, pièces, cloisons et édition

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Un utilisateur crée une pièce à partir de zéro (rectangle rapide ou tableau de sommets), obtient ses murs, son sol, ses jonctions, peut ajouter des cloisons, des ouvertures et des plafonds à pans, et voit des aperçus 2D.

## Périmètre

Bibliothèque `src/Geometrie` (dossier déjà créé à l'étape 02), surfaces par contraintes, cloisons (tracé, faces, ancrages), jonctions, édition d'une pièce, écrans pièce / mur / cloison / aperçus. Hors périmètre : imports DXF/CSV/côtés-angles (étape 04), systèmes, couches, parements, quantités et calcul (étape 05) : les groupes « Common layers », « Side A layers » et « Side B layers » de la page de cloison, le réglage d'un doublage dans la page de mur et les quantités liées au mode de pose arrivent avec les étapes 05b/05d ; cette étape livre la géométrie, les écrans et leurs groupes « Layout » / « Openings ».

## Prérequis

Étape 02 terminée (`Projet`, `Piece`, `Surface`, `Ouverture`, `Cloison` persistés ; `FormeSol` ; `SuppressionCloison`).

## Livrables

`src/Geometrie/*` (classes pures testées), entités/migrations complémentaires (`Jonction`, `SystemeApplique` non requis ici), contrôleurs, formulaires et templates des écrans pièce, mur, cloison ; aperçus 2D (mur et pièce vue de dessus) ; fixtures de test.

## Spécification

Connaissances : [02-geometrie.md](../connaissances/02-geometrie.md). Modèle : [_modele-domaine.md](_modele-domaine.md). Principes d'interface : [_interface-principes.md](_interface-principes.md).

Les murs courbes ne sont **pas pris en charge** : toute surface est plane, tout contour est un polygone.

### Bibliothèque géométrique `src/Geometrie`

Classes pures, sans Doctrine, entièrement testées.

| Classe | Rôle |
|---|---|
| `Point2D(int $x, int $y)` / `Point3D` | mm |
| `Polygone` | liste de `Point2D` ; `aire()`, `perimetre()`, `estSimple()`, `sensAntiHoraire()`, `contient(Point2D)`, `contientPolygone()`, `intersecte()`, `boiteEnglobante()` |
| `Plan` | origine 3D + vecteurs `u`, `v` ; `versPiece(Point2D): Point3D`, longueur réelle d'un segment 3D, angle de pente |
| `ConstructeurSurface` | contraintes de sommets (distance depuis un sommet, angle) → polygone `(u, v)` en mm ; voir « Surfaces construites par contraintes » |
| `GenerateurMurs` | **raccourci de création** : `Polygone` au sol + hauteur → surfaces murs rectangulaires (une par arête) avec leurs contraintes, jonctions angulaires |
| `GenerateurSol` | **raccourci de création** : `Polygone` au sol → surface sol (type `sol`), créée avec la pièce |
| `GenerateurPlafond` | **raccourci de création** : plat ou double pente → pans (surfaces avec contraintes) et jonctions (faîtage, noue, sablière) |
| `ServiceJonctions` | relie deux arêtes ou portions d'arêtes (par identifiants de sommets stables), vérifie la longueur égale sauf pour les jonctions de cloison, calcule le type d'angle |
| `PlacementSurSurface` | positionne un rectangle/polygone dans une surface et vérifie l'inclusion/chevauchement (remplace `OuvertureOverlapChecker` et `PositionsIsValid`) |

Tolérance géométrique : constante unique (1 mm). Aucun `float` pour les positions persistées.

#### Calcul dans le repère de la surface (murs ET plafonds)

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

### Surfaces construites par contraintes

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

### Cloisons (code)

Une cloison est un objet **dans** une pièce (la pièce reste un volume physique). Classes pures dans
`src/Geometrie` :

| Classe | Rôle |
|---|---|
| `TraceCloison` | Segment `(début, fin)` en mm (axe) ; longueur, angle, normale ; `deplacerExtremite()`, `definirLongueur()`, `decalerParRapportA(Mur\|Cloison, distance)` |
| `GenerateurFacesCloison` | Trace + `Cloison.epaisseur` (dérivée du système, voir « Épaisseur d'une cloison ») + plafond de la pièce → deux `Face` (polygone `(u, v)` ; hauteur à chaque position via `Piece::hauteurA(Point2D)` qui lit les pans de plafond) |
| `DetecteurAncrages` | Propose l'ancrage de chaque extrémité : mur, autre cloison, doublage (tolérance configurable, ex. 20 mm) sinon `libre` ; calcule la position le long de la cible |
| `JonctionsCloison` | Crée les jonctions sol / plafond / ancrages et le type correspondant (`cloison_sur_mur`, `cloison_sur_cloison`, `cloison_sur_doublage`) |
| `OuvertureCloison` | Place l'ouverture dans le repère du tracé et la projette sur chaque face (repère de A inversé par rapport au tracé, repère de B dans le sens du tracé) |

#### Tracé modifiable
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

#### Épaisseur d'une cloison
`Cloison.epaisseur` est **dérivée** du système actif, jamais saisie : parement A + ossature + parement B,
calculée en phase de planification **sans polygone** (voir `05a-moteur-couches.md`). Le profilé est choisi
sur la **hauteur maximale** de la cloison à l'axe. L'épaisseur est stockée, puis `GenerateurFacesCloison`
génère les faces. Changer de système, de variante active ou de profilé recalcule l'épaisseur et régénère les
faces, avec `PlanModification` quand des ouvertures ou des ancrages sont touchés.
Même règle pour une cloison ancrée sur un doublage : l'épaisseur du doublage de la **variante active** décale
l'extrémité de la cloison ; activer une autre variante du doublage régénère les cloisons ancrées (impact
affiché avant validation).

### Édition d'une pièce existante

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


### Interface (pièce, cloison, mur)

### Navigation : pièce → mur

Une pièce et un mur sont **deux pages distinctes** :

- La **page de la pièce** montre l'aperçu 2D du dessus ; la colonne de droite contient le groupe « Walls »
  (liste des murs nommés).
- **Choisir un mur change de page** : on arrive sur la **page du mur** (aperçu 2D du mur, propriétés du mur).
  La sélection se fait de **deux façons équivalentes** : cliquer le mur **sur le plan**, ou cliquer son nom
  **dans la liste des murs** de la colonne de droite. Survoler l'un met l'autre en évidence avant le clic.
- La page du mur propose un lien de retour vers la pièce (fil d'Ariane : Project › Room › Wall) et des
  boutons « Previous wall » / « Next wall » qui suivent l'ordre des arêtes du sol, pour éditer les murs
  l'un après l'autre sans repasser par la pièce.
- Même principe pour les pans de plafond (page de pan) quand le plafond est composé de plusieurs pans.
- Chaque page a sa propre URL (`/rooms/{id}`, `/rooms/{id}/walls/{id}`) : lien partageable, retour
  navigateur fonctionnel, navigation par Turbo Drive (sans rechargement complet).
- Si des modifications du formulaire en cours ne sont pas enregistrées, la navigation propose
  « Save and continue », « Discard » ou « Cancel » : l'enregistrement est toujours explicite, jamais automatique.

### Cloisons

- Une cloison est un ouvrage dans une pièce (la pièce reste un volume physique). Elle apparaît sur le plan
  de la pièce (trait épais, nommé) et dans le groupe **« Partitions »** de la colonne de droite.
- **Création** : bouton « Add partition » sur la page de la pièce, puis deux points sur le plan ou saisie
  numérique (début, fin, ou longueur + angle + distance à un mur de référence). Les ancrages sont proposés
  automatiquement et modifiables.
- **Sélection** : comme pour un mur, cliquer la cloison **sur le plan ou dans la liste « Partitions »** ouvre
  **la page de la cloison**.
- **Page de la cloison** : aperçu 2D au centre avec un sélecteur **« Side A / Side B »** pour afficher l'une
  ou l'autre face (ouvertures, ossature, plaques) ; propriétés à droite :
  - *Layout* : début, fin, longueur, angle, décalage, ancrage de chaque extrémité (mur, cloison, doublage,
    libre) ;
  - *Openings* : liste de la cloison (visibles sur les deux faces) avec mode de pose ;
  - *Common layers* : ossature, isolant ;
  - *Side A layers*, *Side B layers* : parements propres à chaque face.
- Le **tracé est modifiable à tout moment** (voir `03-geometrie.md`). Comme partout, l'aperçu est mis à jour
  **après enregistrement** ; le bouton « Save » est toujours visible.
- Un doublage de mur se règle dans la page du mur (système, type de profilé périphérique rail ou lisse).

### Création d'une pièce

Écran « New room » avec **deux entrées**, qui mènent au **même éditeur de pièce** :

1. **Import a DXF file** : assistant d'import (voir `03-geometrie.md`) → éditeur de pièce prérempli.
2. **Start from scratch** : saisie manuelle. Au choix : rectangle rapide (largeur, longueur, hauteur, disponible dès la phase 3),
   côtés et angles (phase 4), ou tableau de sommets (phase 3). Les murs sont générés ; l'éditeur s'ouvre.

Les sources de mesures supplémentaires (CSV de relevé) s'ajoutent comme troisième entrée sans changer
l'éditeur.

#### Modifier une pièce à tout moment
Qu'elle vienne d'un import ou d'une saisie, **la pièce reste entièrement modifiable** à tout moment :
nom, hauteur, sommets du sol (déplacer, ajouter, supprimer un sommet), type de plafond.
Règles (voir aussi `03-geometrie.md`, « Édition d'une pièce existante ») :
- les murs gardent leur **identité** quand un sommet bouge : nom, ouvertures, système et couches restent ;
- ajouter un sommet **scinde** un mur (les ouvertures sont réparties selon leur position) ; supprimer un
  sommet **fusionne** deux murs (avertissement si les noms/systèmes diffèrent) ;
- une ouverture qui sort du mur après modification n'est pas supprimée : elle est signalée en erreur et
  l'utilisateur la corrige ;
- aucune perte silencieuse : toute suppression de données demande une confirmation explicite.

### Nommer la pièce et les murs
- La pièce a un **nom** obligatoire (« Room 1 » proposé par défaut).
- Chaque mur a un **nom** modifiable à tout moment (« Wall 1 »… par défaut, ou suggéré depuis le texte du DXF
  quand il existe). Les noms servent partout : listes, aperçus, exports PDF.
- Les ouvertures ont aussi un nom optionnel (« Window 1 », « Door 1 » par défaut).

### Modifier un mur
Dans l'éditeur d'un mur :
- **renommer** le mur ;
- **ajouter, modifier, supprimer une ouverture** (liste dans le groupe « Openings », ou directement sur
  l'aperçu) : type, dimensions, position `(u, v)`, nom ;
- choisir le **mode de pose** de chaque ouverture : *Surface-mounted* (« applique ») ou *Tunnel*. En tunnel, le
  parement fait un **retour vers l'ouverture** : l'aperçu l'affiche (bandes de retour sur les côtés et le haut,
  et en bas pour une fenêtre), et le calcul ajoute les quantités correspondantes (voir
  `05a-moteur-couches.md`, « Retour de parement »). Un champ « Recess depth » complète la profondeur du
  retour quand le dormant est en retrait du nu du mur.
- Après enregistrement, les contrôles signalent chevauchement, sortie de la surface ou distance insuffisante à
  une arête ; ce sont des avertissements, ils n'empêchent pas de poursuivre.

### Aperçus

| Objet | Aperçu |
|---|---|
| Mur, pan de plafond (surface) | **2D**, dans le repère de la surface : contour, ouvertures (avec retour de parement en tunnel), ossature, plaques, équipements |
| Pièce | **2D vue de dessus** (sol, murs nommés, ouvertures, équipements) en phase 3 ; **vue 3D** en phase 9 |
| Import | aperçu SVG des contours détectés, jamais d'import sans aperçu |

Aperçus 2D générés côté serveur (Twig/SVG) à partir des objets géométriques, pour garder un seul modèle ;
la vue 3D est un composant Stimulus qui consomme un JSON de la pièce (bibliothèque chargée via l'importmap,
version figée).

## Critères d'acceptation

Géométrie (classes pures, testées) :

- [x] `Polygone` : aire, périmètre, sens, `estSimple()`, `contient`, `intersecte`, boîte englobante, testés (rectangle, L, pentagone, polygone auto-sécant).
- [x] Un L génère 6 murs avec un angle rentrant détecté ; longueur de jonction égale des deux côtés.
- [x] Plafond double pente : 2 pans, longueur d'une fourrure sur pente (`Surface::coupes()`) = longueur réelle (Pythagore, ± 1 mm).
- [x] Un mur pignon (pentagone) : `coupes()` donne des longueurs différentes selon la position.
- [x] Surface par contraintes (distance depuis un sommet, angle) : le polygone `(u, v)` est résolu en mm entiers ; une contrainte sur-définie ou incohérente est une erreur de validation sur le champ.
- [x] Le sol est généré avec la pièce (`GenerateurSol`) ; ajouter/supprimer un sommet ne décale aucune jonction (identifiants de sommets stables).
- [x] Jonctions : égalité des longueurs (± 1 mm) vérifiée pour les types de même contour, non exigée pour `cloison_sur_*`.
- [x] Placement d'une ouverture : hors surface, chevauchement et distance insuffisante à une arête détectés (remplace `OuvertureOverlapChecker`).

Cloisons et édition :

- [x] Une cloison se crée (deux points ou saisie numérique), son tracé se modifie (extrémité, longueur, angle, décalage) ; ses deux faces A et B sont régénérées, avec hauteur variable sous un plafond rampant.
- [x] Ancrages détectés (mur, cloison, doublage, libre) avec tolérance ; un ancrage dont la cible a disparu devient `libre` avec avertissement (route de suppression de cloison branchée sur `SuppressionCloison`, report de l'étape 02).
- [x] Une ouverture de cloison apparaît sur les deux faces (face A inversée, face B dans le sens du tracé) ; elle est conservée à sa position quand le tracé change et signalée si elle sort.
- [x] Édition de pièce : déplacer un sommet conserve identité, nom et ouvertures des murs ; ajouter un sommet scinde un mur ; supprimer un sommet fusionne deux murs (confirmation si noms ou systèmes diffèrent) ; aucune ouverture supprimée silencieusement.
- [x] `PlanModification` liste les impacts avant validation quand des ouvertures ou ancrages sont touchés.
- [x] Le formulaire de pièce refuse un contour auto-sécant (`estSimple()`, report de l'étape 02).

Écrans :

- [x] Création d'une pièce à partir de zéro (rectangle rapide ou tableau de sommets) puis édition dans le même éditeur ; l'entrée « Import a DXF file » est ajoutée à l'étape 04 sans modifier l'éditeur.
- [x] Un mur peut être renommé ; une ouverture peut être ajoutée, modifiée, supprimée ; son mode de pose applique/tunnel (et le champ « Recess depth ») change l'aperçu (retour de parement) ; les quantités correspondantes sont traitées à l'étape 05d.
- [x] Une cloison est accessible depuis le plan ou la liste « Partitions » ; sa page a un sélecteur « Side A / Side B » et les groupes « Layout » et « Openings ».
- [ ] Le bouton « Save » est visible en permanence ; rien n'est enregistré sans lui ; l'aperçu est mis à jour après enregistrement, pas pendant la saisie ; quitter avec des modifications non enregistrées demande « Save and continue » / « Discard » / « Cancel ».
- [ ] Les groupes de la colonne de droite se replient/déplient et l'état est mémorisé.
- [x] Sélectionner un mur sur le plan **ou** dans la liste de droite ouvre la page de ce mur ; retour à la pièce et mur précédent/suivant possibles ; chaque page a son URL propre.
- [x] Un pan incliné et un plafond plat s'éditent avec la même page et les mêmes groupes qu'un mur ; les sommets se règlent par distance ou par angle.
- [x] Aperçus SVG côté serveur : mur/pan (contour, ouvertures, retour en tunnel), pièce vue de dessus (sol, murs nommés, ouvertures, cloisons).
- [x] Aucun texte visible n'est en dur en français ; chaque message anglais a sa traduction française.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

- Critères repris de l'ancienne version, recadrés le 2026-11-06 : import DXF (→ 04), parements par face et groupes de couches (→ 05b), quantités du mode de pose (→ 05d), « mêmes systèmes » pour pans/murs (→ 05a) ne conditionnent plus cette étape.
- Dossier de code : `src/Geometrie` (français, comme créé à l'étape 02) au lieu de `src/Geometry`.
- Partie 1 (domaine, persistance, services) et partie 2 (écrans) faites. Critère 241 coché : la route `partition_delete` appelle `SuppressionCloison::supprimerAvecAvertissements()` et affiche les avertissements (testé : cloison ancrée sur la cloison supprimée). Critère 243 : la confirmation « systèmes différents » dépend de l'étape 05 ; seule la différence de noms est gérée.
- Repères : `u × v` pointe vers l'intérieur de la pièce. Sol : `u = (1000,0,0)`, `v = (0,1000,0)`. Mur : origine au sommet `fin` de l'arête du sol, `u` = vecteur (début − fin), `v = (0,0,1000)`. Plafond plat : origine `(0,0,H)`, `v = (0,−1000,0)`. Les vecteurs sont des entiers bruts (mm).
- Identifiants de sommets : sol `S1..Sn` (nouveau = max + 1, jamais réutilisé dans l'ordre), haut des murs `S1h`, faîtage `F1`/`F2`, faces de cloison `db`, `fb`, `dh`, `fh`, `r1h`… Les métadonnées d'un mur sont dans `Surface.contraintes` (`sommets`, `arete_sol`).
- Contraintes : `fixe`, `distance`, `angle` (dixièmes de degré), `controle` (vérification seule). Codes d'erreur `constraint_*`, messages anglais dans `MessagesGeometrie`, traduits dans `validators.fr.xlf` ; l'adaptateur `App\Service\MessageAvertissement` fabrique le `TranslatableMessage` (paramètres `{{ nom }}`).
- Double pente : sol rectangulaire seulement ; faîtage parallèle au plus grand côté (ou argument `axe`), au milieu de la largeur ; pan = rectangle largeur × hypoténuse ; murs pignons = pentagones par contraintes. Un plafond plat est un pan horizontal (`TypeSurface::PlafondPan`).
- `TypeJonction` : ajout de `Sabliere` (pan contre mur le long d'une arête horizontale). Type dièdre calculé en 3D (seuil ±0,001 sur le produit scalaire) ; deux pans : saillant = `Faitage`, rentrant = `Noue`. `exigeEgaliteLongueurs()` faux pour `cloison_*`.
- Écart au modèle : `Jonction` porte aussi une FK `piece` (+ `Piece::jonctions`, cascade/orphanRemoval) ; FK de surfaces en `ON DELETE CASCADE` ; `surfaceB` nullable (sol, pan seul) ; portions côté B en mm le long de l'arête basse de la cible. Migration générée par `doctrine:migrations:diff` (`Version20261106150627`).
- Faces de cloison : face A (gauche du tracé) origine = fin + normale·e/2, `u` inversé ; face B origine = début − normale·e/2. Épaisseur provisoire 100 mm (`GenerateurFacesCloison::EPAISSEUR_PROVISOIRE`) tant que la cloison n'a pas de système (étape 05). Hauteur variable via l'abstraction `HauteurPlafond` (`HauteurPlate`, `HauteurSousPans`), ruptures = croisements de la projection des pans.
- Ancrages : tolérance 20 mm, priorité mur > doublage > cloison ; ancrage forcé mémorisé dans `Cloison.parametres['ancrages_forces']`. Les cloisons ancrées sur une cloison éditée la suivent (un seul niveau) ou deviennent `libre` avec `partition_anchor_lost`. Les doublages ne sont pas encore des cibles (étape 05). Extrémité hors du sol : `partition_end_outside_room` avec confirmation requise.
- Édition de pièce : sommet déplacé, seule la largeur des deux murs voisins est mise à jour (`wall_shape_unchanged` si le mur a une forme non rectangulaire, ex. pignon) ; scission : le mur existant devient la première moitié (« (1) »), un nouveau mur la seconde (« (2) »), ouverture à cheval conservée sur la seconde et signalée (`opening_straddles_split`) ; fusion : le mur précédent est conservé, le suivant supprimé, ses ouvertures lui sont rattachées (`opening_moved`). Minimum 3 sommets, contour simple et anti-horaire exigé. Les pans de plafond ne sont pas recalculés (`ceiling_not_updated`). Après chaque modification, les cloisons sont régénérées (invalides : `partition_invalid_after_change`, jamais supprimées).
- Schéma `planifier*` / `appliquer*` renvoyant un `PlanModification(impacts, confirmationRequise)` ; `OuvertureOverlapChecker` et `PositionsIsValid` conservés (utilisés par `/doublage/mur`).
- Services : `PieceCreationService::generer(Piece, ?hauteurFaitage, ?axe)`, `PieceJonctions::synchroniser(Piece)`, `PieceEditionService` (`planifier|appliquer` `Deplacement(piece, idSommet, Point2D)`, `Scission(piece, idDebutArete, distanceMm)`, `Fusion(piece, idSommet)`), `CloisonEditionService` (`planifier`, `appliquer`, `regenerer`, `deplacerExtremite`, `definirLongueur`, `definirAngle`, `decaler`, `forcerAncrage`), `SuppressionCloison::supprimerAvecAvertissements`.
- Partie 2 (écrans) : routes `piece_new` (`/projets/{id}/pieces/nouvelle`), `piece_show` (`/rooms/{id}`), `surface_show` (`/rooms/{piece}/walls/{surface}`, commune aux murs, pans de plafond et sol), `partition_new`, `partition_show` (`?side=A|B`, défaut B), `partition_delete`. L'ancienne route `/pieces/{id}/modifier` est supprimée (même éditeur pour création et édition). Services : `EditionPieceSaisie`, `EditionCloisonSaisie`, `SurfaceEditionService`, `OuvertureEditionService`, `ControlePlacement`, `OrdreSurfaces` ; aperçus `App\Apercu\ApercuSurface` / `ApercuPiece` ; contrôleurs Stimulus `collapsible-group`, `unsaved-changes`, `collection`, `highlight`, `mode-switch`, `plan-picker`.
- Écarts : saisie en mm (plus en mètres) ; une seule page `surface_show` pour mur/pan/sol (pas de route `/walls` distincte du plafond) ; un seul changement de sommet par enregistrement de pièce (`edit_one_vertex_at_a_time`) ; confirmation de `PlanModification` en deux temps (réaffichage 422 avec `_confirmer`) ; création d'une cloison par deux clics sur le plan (`plan-picker`) ou saisie numérique ; pas d'entrée DXF (étape 04). `CloisonEditionService::analyser` reconstruit désormais la cible d'un ancrage forcé depuis son identifiant (les ancrages forcés non libres produisent des jonctions).
- Non vérifié en navigateur (aucun navigateur, pas de réseau) : le dialogue « Save and continue / Discard / Cancel », la mémorisation `localStorage` des groupes, la surbrillance au survol et le plan-picker. Leur code JS est contrôlé par `node --check` seulement ; critères 252 et 253 laissés non cochés. Les tests couvrent l'enregistrement explicite, la confirmation de plan et la redirection `_continue` côté serveur.
- Le `.DONE` n'est pas appliqué (critères 252, 253 restants).
