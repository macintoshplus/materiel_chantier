# Étape 02 — Modèle de domaine et persistance

> Statut : terminée (vérifiée le 2026-11-06 : 66 tests, phpstan niveau 8, schéma en phase). Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Persister projets, pièces, surfaces, ouvertures et cloisons, avec plusieurs projets et un CRUD minimal.

## Périmètre

Entités `Projet`, `Piece`, `Surface`, `Ouverture`, `Cloison` (voir [_modele-domaine.md](_modele-domaine.md)), migration, écrans projets et pièces. Hors périmètre : `Jonction`, `SystemeApplique`, `Equipement`, `Reseau`, `Chiffrage` (étapes suivantes).

## Prérequis

Étape 00 terminée.

## Livrables

Entités, repositories, migration `doctrine:migrations:diff`, contrôleurs/formulaires/templates projets et pièces, traductions.

## Spécification

Le détail des entités et des règles de cohérence est dans [_modele-domaine.md](_modele-domaine.md) (référence permanente) ; seules les entités ci-dessus sont dues ici.

## Critères d'acceptation

- [x] Les entités `Projet`, `Piece`, `Surface`, `Ouverture`, `Cloison` existent avec les champs du modèle, en millimètres entiers.
- [x] Le schéma est en phase avec le mapping (`doctrine:schema:validate`) et la migration a été générée par `doctrine:migrations:diff`.
- [x] Une face de cloison exige `cloison` et `cote` ; un mur n'en porte pas ; une ouverture a exactement un parent ; le refoncement est ≥ 0 (tests de validation).
- [x] On peut créer, lister, modifier et supprimer un projet ; supprimer un projet supprime ses pièces, surfaces, ouvertures et cloisons (cascade).
- [x] On peut créer et modifier une pièce (nom, hauteur sous plafond) dans un projet.
- [x] Le formulaire de pièce permet de saisir `formeSol` (un sommet « x;y » en mètres par ligne, stocké en mm entiers, remis en sens anti-horaire ; < 3 sommets, sommets confondus ou surface nulle refusés) — `FormeSol`, `FormeSolType`.
- [x] Supprimer une cloison (`SuppressionCloison`) rend `libre` les cloisons ancrées sur elle et retourne la liste de celles-ci pour avertissement.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

- `formeSol` et la règle de suppression de cloison ont été implémentés dans cette étape (plus de report).
- Reporté à l'étape 03 : la détection d'un contour auto-sécant (`Polygone::estSimple`) et, pour la suppression de cloison, la route/le bouton qui appelle `SuppressionCloison` et affiche l'avertissement (il n'y a pas encore d'écran de cloison).
