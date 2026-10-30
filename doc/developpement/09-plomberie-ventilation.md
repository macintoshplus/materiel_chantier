# Plomberie et ventilation (code)

Connaissance : [08-plomberie-ventilation.md](../connaissances/08-plomberie-ventilation.md). S'appuie
sur le même socle que l'électricité ([05-electricite.md](05-electricite.md)) : **le socle est écrit
pour l'électricité en phase 7 puis généralisé ici**, sans réécriture.

## Socle commun `src/Reseaux`

| Élément | Rôle |
|---|---|
| `Equipement` (entité) | `famille` (`electrique`, `plomberie`, `ventilation`), type, surface porteuse, `u`, `v`, hauteur, paramètres JSON |
| `Reseau` (entité) | Groupe d'équipements reliés : circuit électrique, alimentation eau (froide, chaude), évacuation, ventilation |
| `TypeEquipement` (tag de service) | Code, famille, libellé, matériaux associés (rôle → catégorie catalogue), paramètres |
| `CheminementCalculator` | Cheminement le long des surfaces entre équipements et point de départ, selon des règles propres à la famille |
| `RegleReseau` (interface) | Contrôles et quantités d'une famille : conducteurs, pente d'évacuation, débit de ventilation… |

## Plomberie

- Équipements : point d'eau (évier, lavabo, douche, baignoire, WC, lave-linge, lave-vaisselle), arrivée
  d'eau, chauffe-eau, collecteur, siphon de sol.
- Réseaux : eau froide, eau chaude sanitaire, évacuation (eaux usées).
- Quantités : tubes par diamètre (longueur + réserve), raccords (coudes, tés, manchons : estimés par
  nombre de changements de direction et de piquages), vannes d'arrêt, fixations, gaine d'isolation de
  tuyau, tubes d'évacuation par diamètre avec **pente** (longueur réelle), siphons, boîtes de raccordement.
- Contrôles : pente d'évacuation minimale, diamètre selon l'équipement, distance au chauffe-eau.

## Ventilation

- Équipements : bouche d'extraction (cuisine, salle de bains, WC), entrée d'air, groupe VMC, réseau de
  gaines, piquages.
- Quantités : gaines par diamètre, coudes, manchons, piquages, bouches, entrées d'air, colliers, isolant
  de gaine si traversant un volume froid, sorties de toiture.
- Contrôles : débits mini par pièce (selon la réglementation en vigueur, **à valider**), nombre de bouches.

## Entités et catalogue

`Equipement` et `Reseau` remplacent `ElementElectrique`/`Circuit` (voir `01-modele-domaine.md`). Nouvelles
catégories de catalogue : `plomberie_tube`, `plomberie_raccord`, `plomberie_appareil`, `ventilation_gaine`,
`ventilation_bouche`, `ventilation_groupe`.

## Import DXF / sources de mesures

Les blocs d'un DXF reconnus comme équipements sont proposés avec une famille et un type à confirmer.

## Critères d'acceptation

- Un équipement de plomberie placé sur un mur est relié au point de départ ; la longueur de tube eau
  froide/chaude est calculée avec réserve.
- Évacuation : longueur réelle avec pente, diamètre selon l'équipement.
- Ajouter un type d'équipement ne demande ni migration ni modification du moteur.
- Les quantités entrent dans les besoins du projet et dans le chiffrage comme les autres matériaux.
