# Étape 02 — Modèle de domaine et persistance

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

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
- [ ] Le formulaire de pièce permet de saisir `formeSol` (polygone au sol).
- [ ] Supprimer une cloison rend `libre` les cloisons ancrées sur elle, avec avertissement.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

- `formeSol` n'est pas saisissable dans le formulaire de pièce (reporté à l'étape 03, création de pièce) : critère non coché, l'étape n'est donc **pas** terminée tant qu'il est ouvert ou formellement reporté.
- La règle « supprimer une cloison → cloisons ancrées deviennent `libre` » n'est pas implémentée (dépend de l'ancrage, étape 03).
- Décider avec l'utilisateur : reporter ces deux critères à l'étape 03 (puis cocher/déplacer) ou les implémenter ici.
