# Étape 05a — Moteur de couches et pipeline

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Remplacer le calcul monolithique par un pipeline de couches (spécification → catalogue → calculateurs), sans changer les résultats existants de cloison et de doublage.

## Périmètre

Concepts (`DefinitionSysteme`, `EmplacementCouche`, `SystemeApplique`, `CalculateurCouche`), pipeline, contexte, couches multiples, spécifications, isolants croisés, calepinage décalé, objets de résultat typés. Hors périmètre : systèmes concrets (05b, 05c), finitions (05d), agrégation (05e).

## Prérequis

Étapes 01 et 03 terminées.

## Livrables

`src/Calcul/*` (pipeline, contexte, spécifications, façades `IsolantView`/`ProfilView`), enregistrement par tag de service, entités `SystemeApplique`/`CoucheAppliquee` avec migration, adaptateur `Surface` → calculateurs existants.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

### Concepts

- **`ParoiExistante`** : composition de la paroi à rénover (voir plus bas). Optionnelle.
- **`DefinitionSysteme`** : code, libellé, types de surface compatibles (`mur`, `plafond_pan`, `sol`, `cloison`) : un mur, un pan incliné et un plafond horizontal sont la même géométrie, un système s'applique aux trois et seuls les matériaux changent,
  **emplacements de couches** (voir plus bas), paramètres (schéma). Un système est un **assemblage
  ordonné de couches**, pas un calculateur monolithique.
- **`EmplacementCouche`** : rôle, cardinalité (`min`..`max`, ex. 1 à 2 parements), catégorie de matériau
  attendue, position par rapport à l'ossature, orientation autorisée, `CalculateurCouche` associé.
- **`SystemeApplique`** (entité) : choix d'une définition pour une surface ; contient les couches
  réellement posées. **Plusieurs par surface = variantes**, une seule active.
- **`CalculateurCouche`** (interface) : `calculer(CoucheContexte, ContexteCouches): ResultatCouche`
  qui renvoie des **lignes de besoin** et le contexte mis à jour.
- Les définitions et les calculateurs de couche sont enregistrés par tag de service : ajouter un
  système ou un type de couche = une classe + un tag.

### Patterns

| Besoin | Pattern | Rôle |
|---|---|---|
| Lien mur → catalogue | **Spécification** | Une couche exprime une exigence (rôle, catégorie, critères sur le JSON) ; le catalogue se filtre avec elle ; un article épinglé est validé contre elle |
| Lien catalogue → fournisseur | **Stratégie** + **chaîne de responsabilité** | Choix du fournisseur (unique, meilleur prix, plus proche) puis substitution par un équivalent |
| Empilement des couches | **Pipeline** (composite ordonné) | Chaque couche reçoit le contexte des couches précédentes et le complète |
| Lecture typée du catalogue | **Façade** (`IsolantView`, `ProfilView`…) | Les calculateurs ne lisent ni JSON brut ni prix |
| Chiffrage figé | **Instantané** (Memento) | Voir `06-chiffrage-exports.md` |

#### Spécification et résolution des matériaux

`MaterielSpecification` : rôle, catégorie, critères (clé JSON, opérateur, valeur), par exemple
`isolant`, `lambda <= 0.035`, `epaisseur >= 100`. Méthode `estSatisfaitePar(Materiau): bool`.

Une `CoucheAppliquee` porte la spécification et, éventuellement, un **matériau épinglé** :
- épinglé : le matériau doit satisfaire la spécification (validation), sinon avertissement ;
- non épinglé : `ResolveurMateriau` propose les candidats du catalogue qui la satisfont ; le premier
  (ou celui choisi par l'utilisateur) est retenu avant le calcul.

Au chiffrage, un article indisponible chez le fournisseur choisi est remplacé par un **équivalent**
(autre article satisfaisant la même spécification) **uniquement si la correspondance exacte n'est pas
disponible** et si l'utilisateur l'a autorisé. Chaîne de résolution (`ResolveurOffre`) : article épinglé
chez le fournisseur → équivalent chez ce fournisseur → épinglé ou équivalent chez un autre fournisseur
(sauf stratégie `FournisseurUnique`) → ligne « prix manquant ». Chaque maillon est testable seul.

**Un équivalent a d'autres caractéristiques** (longueur de barre, dimensions de plaque, rouleau, lot) : la
substitution **déclenche le recalcul de l'optimisation de coupes** (et du calepinage) de cette ligne avec
les caractéristiques de l'article retenu. Le moteur conserve donc, pour chaque matériau, les **besoins
bruts** (pièces à découper, surfaces, longueurs, nombres de pièces) et non seulement le nombre de barres.
L'optimisation et l'arrondi aux lots sont toujours exécutés **pour un article précis** ; l'ordre est :
besoins bruts → résolution de l'article (fournisseur, équivalent) → optimisation → marge → lots.

### Couches multiples, orientation et position

Une définition de système peut autoriser **plusieurs couches du même rôle** :

| Emplacement | Rôle | Cardinalité | Remarques |
|---|---|---|---|
| attache | fixation | 0..1 | Appuis, pattes, suspentes ; dépend de l'épaisseur isolée |
| isolant | isolant | 1..2 | 2ᵉ couche optionnelle, orientation `0` ou `90` (croisée) ; une couche croisée est obligatoirement de position `continue` (incompatible avec `entre_ossature`, validation bloquante) |
| ossature | ossature | 1 | Cloison ou doublage ; sa profondeur dépend des isolants « entre ossature » |
| parement | parement | 1..2 | 1 ou 2 peaux ; types hydro, phonique, feu, haute dureté…, tous des articles de la catégorie `plaque_platre` |

Chaque couche posée porte :
- **`orientation`** (degrés, **relative à la direction de l'ossature de la surface** : `0` = parallèle, `90` = perpendiculaire ; direction définie par `Surface::coupes()`) : détermine la direction des
  coupes de la couche (voir `Surface::coupes()` dans `03-geometrie.md`). Une 2ᵉ couche d'isolant à 90° est
  croisée ; à 0° elle est simplement doublée.
- **`position`** par rapport à l'ossature : `entre_ossature` (l'isolant remplit la cavité, surface isolée
  = surface nette − ossature, pont thermique), `continue` (passe devant l'ossature) ou `sur_ossature`
  (parement).
- **`decalage`** (calepinage) : pour une 2ᵉ peau de plaques, décalage des joints par rapport à la peau
  précédente (≥ 0,40 m pour les joints verticaux, rangées horizontales décalées).

#### Pipeline de couches et contexte

`PipelineCouches` parcourt les couches de la plus proche du support à la plus visible. À chaque étape,
le calculateur lit et enrichit un `ContexteCouches` (objet valeur immuable, remplacé à chaque étape) :

| Donnée du contexte | Lue par | Exemple |
|---|---|---|
| épaisseur cumulée | attache, ossature, vis | Longueur de l'appui d'isolant = épaisseur isolée |
| profondeur de cavité | ossature | Montant M70 si isolant 70 mm entre ossature |
| direction de l'ossature | isolant croisé, lattes | La 2ᵉ couche d'isolant croisée est à 90° de l'ossature (donc de la 1ʳᵉ couche, qui est à 0°) |
| calepinage de la couche précédente | parement suivant | Joints de la 2ᵉ peau décalés |
| épaisseur de parement totale | visserie | Longueur de vis = épaisseur des peaux + pénétration dans l'ossature |
| R cumulé | thermique | Contrôle de l'objectif |

Règles d'ordre : un isolant « entre ossature » est évalué **après** le choix de l'ossature (sa
profondeur est une contrainte) ; le pipeline gère ces dépendances par une phase de planification
(profondeurs) puis une phase de calcul (besoins). Les erreurs de cohérence sont des avertissements
explicites (ex. « isolant 100 mm dans une ossature de 70 mm »).

#### Exemple : isolant sur attaches, ossature, deux peaux

1. Attache d'isolant (appuis) — fixation ;
2. Isolant 1, 60 mm, 0° — isolant ;
3. Isolant 2, 60 mm, 90° (croisé) — isolant ;
4. Ossature de doublage (montants + rails) — ossature ;
5. Parement 1 BA13 hydro — parement ;
6. Parement 2 BA13 phonique, joints décalés — parement.

L'épaisseur cumulée (120 mm d'isolant + ossature + 2 × 12,5 mm de plaque) pilote les attaches, la
profondeur d'ossature et la longueur des vis ; le R total additionne les deux isolants.

#### Mise en œuvre incrémentale

- v1 : cardinalités multiples, orientation `0`/`90`, position entre ossature / continue, contexte
  (épaisseur cumulée, profondeur de cavité) ; calepinage décalé simple (décalage fixe d'une demi-plaque).
- v2 : pont thermique d'ossature, optimisation du décalage selon les ouvertures, vis par épaisseur
  choisies automatiquement dans le catalogue.
- Les entraxes, pas d'attache et portées limites viennent des **fiches fabricants** : ce sont des
  paramètres du catalogue (`caracteristiques`), pas des formules codées en dur.


### Évolutions de l'existant

1. Passer les calculateurs en mm et leur donner une `SurfaceCalculable` (polygone + ouvertures + repère)
   au lieu de `Mur`.
2. Supprimer le catalogue passé en paramètre : lire les `Materiau` choisis dans le système appliqué.
3. Remplacer les tableaux associatifs de résultat par des objets typés (`LigneBesoin`, `ResultatCouche`, `ContexteCouches`).
4. `MateriauxCalculator` prend des **besoins bruts** et un `Materiau` précis (ses dimensions, son lot) et
   renvoie des `LigneBesoinOptimise` (barres, lots, chutes) sans prix ; il est rappelé quand l'article change
   (substitution par équivalent).
5. Le calepinage des plaques (déjà ébauché) devient un composant partagé `CalepinagePlaques`.
6. Le défaut actuel du contrôleur (catalogue codé en dur ignorant la saisie) disparaît avec le point 2.

## Critères d'acceptation

- [ ] Les résultats de cloison/doublage restent identiques aux tests de caractérisation de la phase 0 (tolérance sur les arrondis mm).
- [ ] Ajouter un nouveau système n'exige aucune modification du moteur ni des entités.
- [ ] Système « 2 isolants croisés + ossature + 2 parements » : les couches s'enchaînent, l'épaisseur cumulée pilote profondeur d'ossature, attaches et vis ; le R total additionne les deux isolants.
- [ ] Une 2ᵉ couche d'isolant à 90° est coupée perpendiculairement à l'ossature (donc à la 1ʳᵉ couche posée à 0°) ; à 0° elle est parallèle.
- [ ] Les joints de la 2ᵉ peau de plaques sont décalés de ≥ 0,40 m de ceux de la 1ʳᵉ.
- [ ] Une couche non épinglée est résolue par spécification ; un article épinglé qui ne la satisfait pas déclenche un avertissement.
- [ ] L'écran actuel `/doublage/mur` reste accessible jusqu'à ce que l'écran de mur le remplace.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
