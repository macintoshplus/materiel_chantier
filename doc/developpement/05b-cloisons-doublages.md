# Étape 05b — Cloisons, doublages, paroi existante et variantes

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Calculer les systèmes `cloison_ossature` (deux faces) et `doublage_ossature`, avec paroi existante et comparaison de variantes.

## Périmètre

Systèmes initiaux cloison et doublage, rails périphériques, ouvertures de cloison, `ParoiExistante`, variantes. Hors périmètre : plafonds (05c).

## Prérequis

Étape 05a terminée.

## Livrables

Définitions de systèmes `cloison_ossature` et `doublage_ossature` + calculateurs de couche, `ParoiExistante`/`CoucheExistante` avec migration, écran de variantes.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

#### Systèmes initiaux

Chaque système est un assemblage d'emplacements de couches ; les calculateurs actuels deviennent le
calculateur de la couche « ossature » (et « attache » pour les appuis).

| Code | Surfaces | Réutilise |
|---|---|---|
| `cloison_ossature` | cloison (deux faces) | `OssatureCloisonCalculator` (couche ossature) |
| `doublage_ossature` | mur | `OssatureDoublageCalculator` (couche ossature + attaches) |
| `doublage_colle` | mur | **hors v1** (complexe collé / plots de colle, plus tard) |
| `plafond_suspendu_placo` | plafond_pan | **v1** : lisses périphériques, fourrures, suspentes, plaques ; pan horizontal ou sous rampant (voir « Plafond suspendu ») |
| `plafond_autoportant_placo` | plafond_pan | à écrire : ossature **horizontale**, portée = distance entre appuis ; utilise la table de portées en orientation `horizontale` et les renforts |
| `lambris_sur_lattes` | mur, plafond_pan | à écrire |
| `isolation_seule` | mur, plafond_pan | à écrire (isolant + membrane) |

### Cloison à deux faces

La cloison est un objet avec **un seul `SystemeApplique`**, des couches **communes** et des couches **par
face** :

| Emplacement | Portée | Remarques |
|---|---|---|
| ossature | commune (`face = null`) | une seule ossature pour les deux côtés ; profil, entraxe, renforts |
| isolant | commun | entre ossature (profondeur de cavité) ou continu |
| attache | commune | si le système en comporte |
| parement | **par face** (`A`, `B`) | 1 à 2 peaux, type et épaisseur propres à chaque côté ; calepinage, joints et visserie par face |

#### Pipeline à deux faces
`PipelineCouches` pour une cloison parcourt la pile **de la face A vers la face B** : parement(s) A →
isolant/ossature → parement(s) B. L'épaisseur cumulée est la somme des deux parements et de l'ossature
(épaisseur de la cloison). Les calculs :
1. **Planification** (sans polygone) : **profilé choisi sur la hauteur maximale** de la cloison à l'axe,
   **épaisseur de la cloison** (publiée vers `Cloison.epaisseur`, voir `03-geometrie.DONE.md`), entraxe, renfort,
   profondeur de cavité (isolant), choix des vis **par face** (critère
   calculé sur l'épaisseur de parement de la face).
2. **Ossature** : calculée **une fois** pour la cloison (rails, montants, renforts, linteaux d'ouverture),
   dans le repère du tracé ; hauteur variable si plafond rampant.
3. **Parements** : calculés **une fois par face**, dans le repère de la face (ouvertures projetées, joints
   décalés de la 2ᵉ peau propres à la face).
4. **Agrégation** : les besoins de l'ossature ne sont comptés **qu'une fois** ; ceux des parements sont comptés
   par face puis agrégés par matériau pour le chiffrage de la pièce.

#### Rails périphériques et montants d'extrémité
- **Rails** : bas (sol) et haut (plafond ou pans) sur toute la longueur ; pour un plafond rampant, la longueur
  du rail haut est la longueur réelle de la ligne de jonction avec le(s) pan(s).
- **Extrémité contre un mur existant** : montant de rive fixé au mur.
- **Extrémité contre une autre cloison ou un doublage de mur** : en plus du montant d'extrémité, un
  **montant supplémentaire de raccordement** est ajouté à la jonction (`montants_raccordement`, défaut 1,
  paramètre du système donné par le fabricant), rattaché à la structure qui reçoit, **hors de la grille
  d'entraxe**. Il est compté dans les besoins de montants de la cloison ou du doublage correspondant.
- Extrémité `libre` : montant d'extrémité seul, avertissement.

#### Ouvertures d'une cloison
Une porte ou baie de cloison est portée par la cloison : linteau et montants d'encadrement sont communs
(l'ossature n'est comptée qu'une fois) ; la découpe des plaques est faite sur chaque face. Le **retour de
parement en tunnel** couvre l'épaisseur totale de la cloison (voir « Retour de parement ») ; la matière du
retour est celle du parement d'une face choisie (par défaut la face A, modifiable).

### Doublage : rails ou lisses

Un doublage de mur a toujours un profilé périphérique **haut et bas**. La famille dépend du système
(`type_profil_peripherique`) :
- `rail` : système de type cloison (rails + montants) ;
- `lisse` : système doublage (lisses haute et basse + fourrures).

Le calculateur de la couche ossature d'un doublage lit ce paramètre et sélectionne le profilé dans le
catalogue (`profil_ossature`, famille `rail` ou `lisse`). Les appuis intermédiaires et rails porteurs d'appuis
restent propres au système.

Un doublage peut recevoir une cloison (ancrage `doublage`) : le montant supplémentaire de raccordement est
alors ajouté à l'ossature du doublage à la position de la jonction. L'épaisseur du doublage (variante
active) décale l'extrémité de la cloison ancrée ; activer une autre variante régénère les faces de la cloison.


### Paroi existante

- Liste de couches du support actuel : matériau existant (catégorie catalogue `paroi_existante` :
  parpaing, béton, brique, pierre, plâtre, bois…) + épaisseur, ou un **R forfaitaire** saisi.
- Sert au calcul du R total : `RT = Rsi + R(paroi existante) + Σ R(système) + Rse`.
- Sert aux contrôles : épaisseur totale, support compatible avec le système (ex. doublage collé).
- Pas de prix, pas de lot : les articles de cette catégorie ne figurent jamais dans une nomenclature.

### Variantes

- L'utilisateur crée une variante (copie du système actif, modifiable) et la nomme.
- Écran de comparaison pour une surface, une pièce ou un projet : par variante, R/U, coût (selon un
  fournisseur ou le meilleur prix, voir `06-chiffrage-exports.md`), épaisseur totale.
- Le calcul global, le chiffrage et les exports utilisent **la variante active** de chaque surface.
- `ServiceVariantes::comparer(Surface|Piece|Projet): list<ComparaisonVariantes>` calcule sans modifier
  l'état actif ; activer une variante est une action explicite.

## Critères d'acceptation

- [ ] Deux variantes sur une surface se comparent sans changer la variante active.
- [ ] Cloison à deux faces : l'ossature et l'isolant sont comptés une seule fois ; les parements sont comptés par face ; deux types de plaques différents sur les deux côtés produisent deux lignes.
- [ ] Extrémité de cloison contre une autre cloison ou un doublage : un montant supplémentaire de raccordement est compté (1 par défaut, selon le fabricant) ; contre un mur existant : seulement le montant de rive.
- [ ] Cloison sous un plafond rampant : hauteur de la face variable, longueur de rail haut = longueur réelle.
- [ ] Une ouverture de cloison apparaît sur les deux faces ; son retour en tunnel couvre l'épaisseur totale.
- [ ] Doublage avec un système cloison : rails haut et bas ; avec un système doublage : lisses haute et basse.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
