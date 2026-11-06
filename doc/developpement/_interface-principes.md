# Principes d'interface (permanents)

Ce fichier est une référence transverse : il ne se « termine » pas. Chaque étape qui livre un écran en respecte les règles. Écrans livrés : étape 03 (pièce, mur, cloison), 04 (assistants d'import), 05e (calcul), 06 (chiffrage, exports).


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
| Pièce | Room (nom, hauteur sous plafond), Floor shape (sommets), Walls (liste nommée), Partitions (liste nommée), Ceiling (liste des pans nommés, chacun une page de surface) |
| Mur / pan | Wall (nom), Dimensions, Existing wall, Openings, Materials, Layers, Thermal result |
| Cloison | Partition (nom), Layout (tracé, ancrages), Dimensions, Openings, Materials, Common layers (ossature, isolant), Side A layers (parement), Side B layers (parement), Thermal result |
| Ouverture | Opening (nom, type, dimensions, position, pose) – affiché dans « Openings » du mur |

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
  manquant (voir `_conventions-qualite.md`).

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
- Chaque enregistrement relance le **pipeline de la surface** (ossature, calepinage des plaques, R/U,
  avertissements) : l'aperçu complet et les avertissements (dont « R insuffisant ») sont donc à jour juste
  après « Save ». Le bouton « Calculate » ne sert qu'à l'**agrégation du projet** : optimisation de coupes
  entre surfaces, marge, lots. L'aperçu utilise l'article épinglé ou par défaut ; seule la création d'un
  chiffrage substitue des équivalents.
- Accessibilité de base (labels, contrastes, navigation clavier) et usage tablette sur chantier ; la colonne
  de droite passe sous l'aperçu sur écran étroit.
- Prix affichés TTC avec mention explicite.

