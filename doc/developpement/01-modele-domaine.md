# Modèle de domaine

Base : MariaDB via Doctrine ORM (attributs PHP). Entités dans `src/Entity`, repositories dans
`src/Repository`. Valeurs géométriques en **millimètres entiers**. Le catalogue, les fournisseurs et les
prix sont détaillés dans [02-catalogue.md](02-catalogue.md) ; chiffrage et exports dans
[08-fournisseurs-chiffrage-export.md](08-fournisseurs-chiffrage-export.md).

## Entités

```
Projet 1──n Piece 1──n Surface 1──n Ouverture
  │            │          │
  │            │          ├─ ParoiExistante 1──n CoucheExistante ──> Materiau (catégorie paroi_existante)
  │            │          ├─ SystemeApplique (variantes, une active) 1──n CoucheAppliquee ──> Materiau
  │            │          └─ Arete ── Jonction ── Arete
  │            └─ Equipement n──1 Reseau
  │            └─ formeSol (JSON de sommets)
  └─ Chiffrage 1──n LigneChiffrage ──> Materiau, Fournisseur
```

| Entité | Champs principaux |
|---|---|
| `Projet` | id, nom, description, dateCreation, adresseChantier + latitude/longitude (optionnels), parametres JSON (marge de chute %, options) |
| `Piece` | id, projet, nom, hauteurSousPlafond (mm), formeSol JSON `[{x,y}]` mm anti-horaire, sourceMesures (saisie, dxf, csv) |
| `Surface` | id, piece, type (`mur`, `plafond_pan`, `sol`), nom, origine3D, vecteurU, vecteurV (JSON), polygone JSON `[{u,v}]` |
| `Arete` | surface, indexDebut, indexFin (dérivable du polygone) |
| `Jonction` | areteA, areteB, type (`angle_saillant`, `angle_rentrant`, `faitage`, `noue`, `plat`…) |
| `Ouverture` | id, surface, type, polygone JSON `[{u,v}]` (ou rectangle u,v,largeur,hauteur), typePose, typeContour, refoncement, parametres JSON |
| `ParoiExistante` | surface (1–1), couches (matériau existant + épaisseur, ou R forfaitaire) |
| `SystemeApplique` | id, surface, variante (libellé), actif (bool), definitionSysteme (code), parametres JSON ; plusieurs par surface, **un seul actif** |
| `CoucheAppliquee` | systemeApplique, ordre, role (`isolant`, `membrane`, `ossature`, `parement`…), materiau, epaisseurOverride |
| `Materiau`, `Fournisseur`, `PrixMateriau` | voir `02-catalogue.md` |
| `Equipement` | id, piece, surface, famille (`electrique`, `plomberie`, `ventilation`), type, u, v, hauteurPose, reseau, parametres JSON |
| `Reseau` | id, projet, nom, famille, type, parametres JSON (section, protection, diamètre…) |
| `Chiffrage`, `LigneChiffrage` | voir `08-fournisseurs-chiffrage-export.md` |

Choix : les polygones sont stockés en **JSON** (petits, jamais interrogés en SQL), manipulés dans le code
par des objets valeur (`Polygone`, `Point2D`). Surface plane : un mur courbe n'est pas représentable
(décision : non pris en charge).

## Objets valeur (hors Doctrine)

`Point2D`, `Point3D`, `Polygone`, `Segment`, `Plan`, `Longueur` (mm), `Epaisseur`. Immuables, `readonly`.

## Migration depuis l'existant

- `Model\Mur` → hydraté depuis une `Surface` de type mur rectangulaire (largeur, hauteur) via un adaptateur
  pour que les calculateurs actuels continuent à fonctionner pendant la transition.
- Ouvertures : le tableau associatif devient `Ouverture` ; `distance_gauche`, `elevation`, `largeur`,
  `hauteur` correspondent à `u`, `v`, largeur, hauteur.
- Conversion m → mm à la lecture du formulaire existant.

## Règles de cohérence

- Supprimer un projet supprime ses pièces, surfaces, ouvertures, équipements, chiffrages (cascade).
- Une ouverture reste dans sa surface (validation Symfony, voir `03-geometrie.md`).
- Une jonction relie exactement deux arêtes de longueur égale (± 1 mm).
- Une surface a au plus un `SystemeApplique` actif.
- Les chiffrages sont **immuables** une fois créés (instantané) : ils ne dépendent plus du catalogue.
- Migrations Doctrine versionnées ; aucune modification manuelle du schéma.

## Propriétaire (plus tard)

L'ajout de l'utilisateur ne touchera que `Projet` (clé étrangère `proprietaire`) ; tout le reste y est
rattaché par cascade. Aucune préparation dans les phases actuelles.

## Tests

Tests unitaires des objets valeur ; test d'intégration Doctrine (MariaDB de la CI) pour
l'enregistrement/relecture d'un projet complet avec variantes et chiffrage.
