# Étape 05e — Agrégation, marges, optimisation de coupes et substitution

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Agréger les besoins de toutes les surfaces, appliquer les marges, optimiser les coupes sur les articles réels, gérer la substitution par équivalent et brancher l'aperçu/avertissements sur l'enregistrement.

## Périmètre

Marge de sécurité, `BesoinsProjet`, `MateriauxCalculator` (coupes 1D, lots), substitution par équivalent, action « Calculate » et recalcul au Save. Le chiffrage est l'étape 06.

## Prérequis

Étapes 05a à 05d terminées (au minimum 05a et 05b).

## Livrables

`MateriauxCalculator` sur besoins bruts, résolveur de marge, substitution, écran de calcul du projet.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

### Marge de sécurité et arrondi aux lots

Chaque matériau a une **marge de sécurité en pourcentage** (casse, erreur de découpe, imprévus), distincte
des chutes déjà calculées par l'optimisation de coupes. Elle s'applique **une fois**, sur la quantité
agrégée du matériau (après optimisation et agrégation de toutes les surfaces/faces du chiffrage) :

1. `quantiteBesoin` : quantité nécessaire dans l'**unité d'achat** (barres pour les profilés après
   optimisation, plaques, m² d'isolant, rouleaux, pièces, boîtes de vis…) ;
2. `quantiteAvecMarge = quantiteBesoin × (1 + marge/100)` ;
3. `nombreLots = ceil(quantiteAvecMarge / unitesParLot)` : **arrondi au supérieur selon la quantité par
   lot** ; un seul arrondi, pas un par étape ;
4. `quantiteAchetee = nombreLots × unitesParLot` ; chute totale = `quantiteAchetee − quantiteBesoin`.

La marge est lue par matériau selon la priorité de `01-catalogue.DONE.md` (chiffrage > projet > matériau >
catégorie > défaut global). Elle est copiée dans la ligne de chiffrage pour traçabilité
(`margePct`, `quantiteAvecMarge`). Les profilés optimisés en barres : la marge s'applique au nombre de barres
(`barres × (1 + marge)`), pas aux longueurs unitaires.


### Chaîne de calcul

```
Projet
 └─ pour chaque Piece
     └─ pour chaque Surface (variante active)
          ├─ ThermiqueCalculator  : R par couche, RT, U (paroi existante comprise)
          ├─ PipelineCouches      : résout les matériaux (spécifications), puis parcourt les couches
          │    └─ CalculateurCouche : attache, isolant (surface nette, chute), ossature (rails, montants),
          │                           membrane (rouleaux, recouvrements), parement (calepinage, joints, vis)
          └─ ResultatSurface
     └─ ResultatPiece
 └─ BesoinsProjet : agrégation par matériau
        └─ MateriauxCalculator : optimisation de coupes 1D, chute, taux de perte,
              marge de sécurité par matériau, arrondi au lot supérieur
              └─ (les prix sont appliqués au chiffrage, voir 08-…)
```

Les calculs géométriques d'ossature utilisent les coupes `Surface::coupes()` dans le repère de la
surface (voir `03-geometrie.DONE.md`), donc valables pour les plafonds à pans. Les besoins de plusieurs
surfaces du même matériau sont **agrégés avant optimisation**, pour que les chutes d'un mur servent à
un autre (option : calcul par surface ou par projet).

## Critères d'acceptation

- [ ] Remplacement par un équivalent chez le fournisseur choisi, **seulement en l'absence de correspondance exacte**, puis ligne « prix manquant » en dernier recours.
- [ ] Un équivalent de longueur différente (rail 2,6 m au lieu de 3 m) produit une optimisation de coupes recalculée avec sa longueur, donc un nombre de barres et des chutes différents.
- [ ] Marge 10 % sur 11 plaques avec 1 plaque par lot : `ceil(12,1) = 13` ; avec 5 plaques par lot : 3 lots (15 plaques achetées).
- [ ] La marge d'un matériau suit la priorité chiffrage > projet > matériau > catégorie > défaut global.
- [ ] Un projet de 2 pièces produit des besoins agrégés avec lots entiers et chutes.
- [ ] Après « Save », l'aperçu montre ossature et plaques et les avertissements de calcul apparaissent, sans cliquer sur « Calculate » ; « Calculate » agrège le projet (coupes, marge, lots).

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
