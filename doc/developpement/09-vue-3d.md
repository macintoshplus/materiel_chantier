# Étape 09 — Vue 3D de la pièce

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Visualiser la pièce en 3D (murs, plafond à pans, ouvertures, équipements).

## Périmètre

Visualisation en lecture seule.

## Prérequis

Étape 03 terminée.

## Livrables

Page de vue 3D alimentée par les surfaces persistées.

## Critères d'acceptation

- [ ] Une pièce rectangulaire et une pièce à plafond double pente s'affichent avec leurs ouvertures.
- [ ] Les équipements apparaissent à leur position (u, v) sur leur surface.
- [ ] Aucune donnée n'est modifiée par la vue.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
