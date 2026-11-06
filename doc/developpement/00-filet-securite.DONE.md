# Étape 00 — Filet de sécurité

> Statut : terminée (vérifiée le 2026-11-06). Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Figer le comportement du calcul actuel (cloison, doublage, matériaux, validation des ouvertures) par des tests de caractérisation, et passer les longueurs en millimètres entiers.

## Périmètre

Tests de caractérisation de `OssatureCloisonCalculator`, `OssatureDoublageCalculator`, `MateriauxCalculator`, `OuvertureOverlapChecker` ; unités en mm dans `Model\Mur`.

## Prérequis

Aucun.

## Livrables

`tests/Service/Caracterisation/*`, `Model\Mur` en mm.

## Critères d'acceptation

- [x] Les calculs de cloison, de doublage, de matériaux et la validation des ouvertures ont chacun un test de caractérisation vert.
- [x] `Model\Mur` documente et utilise des longueurs en millimètres entiers.
- [x] Les tests de caractérisation restent verts à chaque étape suivante (garde-fou de non-régression).

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
