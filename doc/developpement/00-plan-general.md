# Plan général

Application Symfony 7.4 existante : un seul écran de calcul de mur, sans persistance. Objectif : un
calculateur multi-projets, persistant, géométrique, extensible.

## Principes

- **Incrémental** : chaque phase livre quelque chose d'utilisable et garde le calcul de mur actuel
  fonctionnel (tests de non-régression avant refactor).
- **Domaine pur et testable** : géométrie et calculs sont des classes PHP sans dépendance Doctrine/Symfony ;
  Doctrine ne sert qu'à la persistance.
- **Extensible par données** : catalogue (JSON validé par schéma) et systèmes (définitions) ajoutés sans
  nouvelle migration quand c'est possible.
- Outillage : voir [07-conventions-qualite.md](07-conventions-qualite.md).

## Phases

| # | Phase | Livrable | Dépend de |
|---|---|---|---|
| 0 | Filet de sécurité | Tests de caractérisation du calcul actuel (cloison, doublage, matériaux) ; migration en mm | — |
| 1 | Modèle de domaine | Entités Projet / Pièce / Surface / Ouverture, CRUD basique, plusieurs projets | 0 |
| 2 | Catalogue | Entité `Materiau` + JSON + schémas par catégorie, écran d'administration, import des articles actuels | 1 |
| 3 | Géométrie | Polygone au sol, murs générés, jonctions, murs non rectangulaires, ouvertures positionnées | 1 |
| 4 | Import DXF | Import d'un contour de pièce (et blocs) → pièce créée | 3 |
| 5 | Systèmes et calcul | Systèmes de revêtement (doublage, cloison, plafonds, lambris…) par surface, couches, thermique R/U, nomenclature par pièce et par projet | 2, 3 |
| 6 | Plafonds composés | Plafond à plusieurs pans, sous-rampant, mur de genou | 3, 5 |
| 7 | Électricité | Éléments placés, circuits, longueurs de câble/gaine, quantités annexes | 3, 5 |
| 8 | Interface | Parcours projet → pièce → surface, aperçu SVG, export nomenclature (CSV/PDF) | en continu |

Les phases 6 et 7 sont indépendantes. L'interface (8) avance avec chaque phase.

## Hors périmètre initial

Authentification multi-utilisateurs (un seul utilisateur au départ ; ajouter le propriétaire sur Projet
plus tard), vue 3D, DXF 3D, plafonds autoportants calculés (modélisés, non calculés), chiffrage de main
d'œuvre, import d'autres formats (SVG, IFC).

## Questions ouvertes (à trancher avant la phase 1)

1. Mono-utilisateur ou comptes utilisateurs dès le départ ?
2. Le catalogue est-il global, par utilisateur, ou par projet ?
3. Prix : HT seulement ? TVA ? Historique de prix ?
4. Murs courbes : on les approxime en segments, ou on les exclut ?
5. Exports attendus : liste de courses PDF, CSV, impression par fournisseur ?

## Définition de « terminé » pour toute tâche

`symfony php vendor/bin/phpunit` vert, `symfony php vendor/bin/phpstan analyse` (niveau 8) sans erreur,
CI verte, doc mise à jour si le comportement métier change.
