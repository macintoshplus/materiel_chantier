# Interface utilisateur

Stack existante : Twig, Stimulus, Turbo, Bootstrap, AssetMapper. Pas de framework JS lourd.

## Disposition générale d'un écran d'édition

```
┌───────────────────────────────────────────────┬──────────────────────┐
│                                               │ ▾ Dimensions         │
│                                               │ ▾ Materials          │
│              APERÇU (centre)                  │ ▾ Layers             │
│   2D (mur) / 2D dessus (pièce) / 3D (phase 9) │     ▾ Layer 1 …      │
│                                               │     ▸ Layer 2 …      │
│                                               │ ▾ Openings           │
└───────────────────────────────────────────────┴──────────────────────┘
```

- **Aperçu au centre**, toujours visible, **mis à jour après enregistrement** (pas en direct pendant la saisie).
- **Colonne de propriétés à droite**, composée de **groupes repliables** (« Dimensions », « Materials »,
  « Layers », « Openings »…). Chaque couche de la liste est elle-même un sous-groupe repliable avec ses
  propriétés (matériau, spécification, orientation, position, épaisseur…).
- L'état replié/déplié de chaque groupe est mémorisé par l'utilisateur (stockage navigateur, valeur par
  défaut dépliée pour « Dimensions »), composant Stimulus `collapsible-group` (accessible : bouton, `aria-expanded`).
- Dans une page, survoler ou sélectionner une ouverture, une couche ou un équipement dans l'aperçu met en
  évidence et déplie son groupe dans la colonne ; l'inverse est vrai (colonne → aperçu).

Les groupes affichés dépendent de l'objet édité :

| Objet édité | Groupes de la colonne de droite |
|---|---|
| Pièce | Room (nom, hauteur sous plafond), Floor shape (sommets), Walls (liste nommée), Partitions (liste nommée), Ceiling (type de toit) |
| Mur / pan | Wall (nom), Dimensions, Existing wall, Openings, Materials, Layers, Thermal result |
| Cloison | Partition (nom), Layout (tracé, ancrages), Dimensions, Openings, Materials, Common layers (ossature, isolant), Side A layers (parement), Side B layers (parement), Thermal result |
| Ouverture | Opening (nom, type, dimensions, position, pose) – affiché dans « Openings » du mur |

## Navigation : pièce → mur

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
- Si des modifications du formulaire en cours ne sont pas enregistrées, la navigation demande
  confirmation (ou enregistre automatiquement, selon la règle choisie pour tout l'écran : voir « Principes »).

## Cloisons

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

## Création d'une pièce

Écran « New room » avec **deux entrées**, qui mènent au **même éditeur de pièce** :

1. **Import a DXF file** : assistant d'import (voir `03-geometrie.md`) → éditeur de pièce prérempli.
2. **Start from scratch** : saisie manuelle. Au choix : rectangle rapide (largeur, longueur, hauteur),
   côtés et angles, ou tableau de sommets. Les murs sont générés ; l'éditeur s'ouvre.

Les sources de mesures supplémentaires (CSV de relevé) s'ajoutent comme troisième entrée sans changer
l'éditeur.

### Modifier une pièce à tout moment
Qu'elle vienne d'un import ou d'une saisie, **la pièce reste entièrement modifiable** à tout moment :
nom, hauteur, sommets du sol (déplacer, ajouter, supprimer un sommet), type de plafond.
Règles (voir aussi `03-geometrie.md`, « Édition d'une pièce existante ») :
- les murs gardent leur **identité** quand un sommet bouge : nom, ouvertures, système et couches restent ;
- ajouter un sommet **scinde** un mur (les ouvertures sont réparties selon leur position) ; supprimer un
  sommet **fusionne** deux murs (avertissement si les noms/systèmes diffèrent) ;
- une ouverture qui sort du mur après modification n'est pas supprimée : elle est signalée en erreur et
  l'utilisateur la corrige ;
- aucune perte silencieuse : toute suppression de données demande une confirmation explicite.

## Nommer la pièce et les murs
- La pièce a un **nom** obligatoire (« Room 1 » proposé par défaut).
- Chaque mur a un **nom** modifiable à tout moment (« Wall 1 »… par défaut, ou suggéré depuis le texte du DXF
  quand il existe). Les noms servent partout : listes, aperçus, exports PDF.
- Les ouvertures ont aussi un nom optionnel (« Window 1 », « Door 1 » par défaut).

## Modifier un mur
Dans l'éditeur d'un mur :
- **renommer** le mur ;
- **ajouter, modifier, supprimer une ouverture** (liste dans le groupe « Openings », ou directement sur
  l'aperçu) : type, dimensions, position `(u, v)`, nom ;
- choisir le **mode de pose** de chaque ouverture : *Surface-mounted* (« applique ») ou *Tunnel*. En tunnel, le
  parement fait un **retour vers l'ouverture** : l'aperçu l'affiche (bandes de retour sur les côtés et le haut,
  et en bas pour une fenêtre), et le calcul ajoute les quantités correspondantes (voir
  `04-systemes-et-calcul.md`, « Retour de parement »). Un champ « Recess depth » complète la profondeur du
  retour quand le dormant est en retrait du nu du mur.
- Après enregistrement, les contrôles signalent chevauchement, sortie de la surface ou distance insuffisante à
  une arête ; ce sont des avertissements, ils n'empêchent pas de poursuivre.

## Aperçus

| Objet | Aperçu |
|---|---|
| Mur, pan de plafond (surface) | **2D**, dans le repère de la surface : contour, ouvertures (avec retour de parement en tunnel), ossature, plaques, équipements |
| Pièce | **2D vue de dessus** (sol, murs nommés, ouvertures, équipements) en phase 3 ; **vue 3D** en phase 9 |
| Import | aperçu SVG des contours détectés, jamais d'import sans aperçu |

Aperçus 2D générés côté serveur (Twig/SVG) à partir des objets géométriques, pour garder un seul modèle ;
la vue 3D est un composant Stimulus qui consomme un JSON de la pièce (bibliothèque chargée via l'importmap,
version figée).

## Autres écrans

| Écran | Contenu |
|---|---|
| Projects | liste, création, duplication, archivage |
| Project | pièces avec surface totale, adresse du chantier (optionnelle), chiffrages, création de pièce, exports |
| Variants comparison | tableau variante × (R/U, épaisseur, coût) pour une surface, une pièce ou un projet |
| Equipment | placement sur l'aperçu d'une surface, listes par réseau (électricité, plomberie, ventilation) |
| Quote | stratégie de fournisseur, lignes (prix manquants en évidence), total TTC, comparaison de deux chiffrages |
| Exports | liste de courses PDF par pièce ou par fournisseur, case « With prices » |
| Catalogue, suppliers, prices | CRUD, carte GPS du fournisseur, historique de prix |

## Textes et traduction

- Tous les textes affichés sont **écrits en anglais dans le code et les gabarits** (clé = texte anglais),
  et **traduits en français** via les fichiers de traduction Symfony (`translations/`, domaine `messages`,
  locale par défaut `fr`).
- Cela inclut : libellés de formulaires, boutons, titres de groupes, messages de validation, avertissements
  de calcul, intitulés de PDF, noms de catégories et de types d'ouverture.
- Pas de texte français en dur dans les gabarits ni dans le code. Les noms saisis par l'utilisateur (pièce,
  mur, ouverture, projet) et le contenu du catalogue ne sont pas traduits.
- Extraction : `symfony console translation:extract fr --force` ; la CI échoue si un message est
  manquant (voir `07-conventions-qualite.md`).

## Principes

- Formulaires Symfony, validation côté serveur. Le rendu est rafraîchi par Turbo après enregistrement.
- Les contrôles métier (ouverture hors surface, R insuffisant, prix manquant) s'affichent comme
  avertissements après enregistrement, sans le bloquer. Seules les données invalides (type, valeur hors
  borne, champ obligatoire) bloquent l'enregistrement et s'affichent sur le champ.

### Enregistrement
- **Explicite** : rien n'est enregistré tant que l'utilisateur ne clique pas sur « Save ».
- Le bouton « Save » est **toujours visible**, quel que soit le défilement de la page ou l'état des groupes
  repliés (barre fixe ancrée en bas de la colonne de droite, ou en haut de la page sur écran étroit).
- Un indicateur « Unsaved changes » apparaît dès qu'un champ est modifié.
- Quitter la page (autre mur, retour à la pièce, autre écran) avec des modifications non enregistrées
  demande confirmation : « Save and continue », « Discard », « Cancel ».
- Après un enregistrement réussi, la page est rendue de nouveau : **l'aperçu est recalculé**
  (ouvertures, retours en tunnel, ossature, plaques) et les avertissements sont affichés.
- Le recalcul des quantités n'est pas fait à chaque enregistrement d'un mur : il se fait à la demande
  (bouton « Calculate ») ou au chiffrage ; l'aperçu, lui, reflète la géométrie et les couches enregistrées.
- Accessibilité de base (labels, contrastes, navigation clavier) et usage tablette sur chantier ; la colonne
  de droite passe sous l'aperçu sur écran étroit.
- Prix affichés TTC avec mention explicite.

## Critères d'acceptation

- Création d'une pièce possible par import DXF **ou** à partir de zéro, avec le même éditeur ensuite.
- Après un import, nom, hauteur, sommets, noms de murs sont modifiables ; les murs conservent leur nom et leurs
  ouvertures quand un sommet bouge.
- Une cloison se crée sur le plan, son tracé se modifie, ses deux faces ont leur parement propre, ses
  ouvertures apparaissent sur les deux faces ; on y accède depuis le plan ou la liste « Partitions ».
- Un mur peut être renommé ; une ouverture peut être ajoutée, modifiée, supprimée ; son mode de pose
  applique/tunnel change l'aperçu et les quantités.
- Le bouton « Save » est visible en permanence ; rien n'est enregistré sans lui ; l'aperçu est mis à jour
  après enregistrement, pas pendant la saisie ; quitter avec des modifications non enregistrées demande
  confirmation.
- Les groupes de la colonne de droite se replient/déplient et l'état est mémorisé.
- Sélectionner un mur sur le plan **ou** dans la liste de droite ouvre la page de ce mur ; retour à la pièce
  et passage au mur précédent/suivant possibles depuis cette page ; chaque page a son URL propre.
- Aucun texte visible n'est en dur en français ; chaque message anglais a sa traduction française.
- L'écran actuel `/doublage/mur` reste accessible jusqu'à ce que l'écran de mur le remplace.
