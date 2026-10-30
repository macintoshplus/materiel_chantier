# Modèle de domaine

Base : MariaDB via Doctrine ORM (attributs PHP). Entités dans `src/Entity`, repositories dans
`src/Repository`. Valeurs géométriques en **millimètres entiers**.

## Entités

```
Projet 1──n Piece 1──n Surface 1──n Ouverture
                  │         │
                  │         ├─ SystemeApplique 1──n CoucheAppliquee ──> Materiau
                  │         └─ Arete ── Jonction ── Arete
                  └─ ElementElectrique n──1 Circuit
Piece 1── formeSol (JSON de sommets)
```

| Entité | Champs principaux |
|---|---|
| `Projet` | id, nom, description, dateCreation, parametres JSON (marge de chute %, options) |
| `Piece` | id, projet, nom, hauteurSousPlafond (mm), formeSol JSON `[{x,y}]` mm anti-horaire |
| `Surface` | id, piece, type (`mur`, `plafond_pan`, `sol`), nom, origine3D, vecteurU, vecteurV (JSON), polygone JSON `[{u,v}]` |
| `Arete` | surface, indexDebut, indexFin (peut être dérivé du polygone) |
| `Jonction` | areteA, areteB, type (`angle_saillant`, `angle_rentrant`, `faitage`, `noue`, `plat`…) |
| `Ouverture` | id, surface, type, polygone JSON `[{u,v}]` (ou rectangle u,v,largeur,hauteur), typePose, typeContour, refoncement, parametres JSON |
| `SystemeApplique` | id, surface, definitionSysteme (code), parametres JSON (entraxe, rail…) |
| `CoucheAppliquee` | systemeApplique, ordre, role (`isolant`, `membrane`, `ossature`, `parement`…), materiau, epaisseurOverride |
| `Materiau` | voir [02-catalogue.md](02-catalogue.md) |
| `ElementElectrique` | id, piece, surface, type, u, v, hauteurPose, circuit, parametres JSON |
| `Circuit` | id, projet, nom, type, section, protection |

Choix : les polygones sont stockés en **JSON** (petits, toujours lus/écrits avec leur entité, jamais
interrogés en SQL), mais manipulés dans le code par des objets valeur (`Polygone`, `Point2D`).

## Objets valeur (hors Doctrine)

`Point2D`, `Point3D`, `Polygone`, `Segment`, `Plan`, `Longueur` (mm), `Epaisseur`. Immuables, `readonly`.
Doctrine les hydrate depuis le JSON via des types personnalisés ou un accesseur.

## Migration depuis l'existant

- `Model\Mur` → hydraté depuis un `Surface` de type mur rectangulaire (largeur, hauteur) via un adaptateur,
  pour que les calculateurs actuels continuent à fonctionner pendant la transition.
- Ouvertures : le tableau associatif devient `Ouverture` ; les clés `distance_gauche`, `elevation`,
  `largeur`, `hauteur` correspondent à `u`, `v`, largeur, hauteur.
- Conversion m → mm à la lecture du formulaire existant.

## Règles de cohérence

- Supprimer un projet supprime ses pièces, surfaces, ouvertures, éléments (cascade).
- Une ouverture doit rester dans sa surface (contrainte de validation Symfony, voir `03-geometrie.md`).
- Une jonction relie exactement deux arêtes de longueur égale (± 1 mm).
- Migrations Doctrine versionnées ; aucune modification manuelle du schéma.

## Tests

Tests unitaires des objets valeur ; test d'intégration Doctrine (base MariaDB du service CI) pour
l'enregistrement/relecture d'un projet complet.
