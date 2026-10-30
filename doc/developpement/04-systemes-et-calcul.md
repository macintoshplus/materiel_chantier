# Systèmes de revêtement et moteur de calcul

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

## Concepts

- **`ParoiExistante`** : composition de la paroi à rénover (voir plus bas). Optionnelle.
- **`DefinitionSysteme`** : code, libellé, types de surface compatibles (`mur`, `plafond_pan`, `sol`),
  couches possibles (rôle, obligatoire, catégorie de matériau attendue), paramètres (schéma), et un
  **calculateur** associé.
- **`SystemeApplique`** (entité) : choix d'une définition pour une surface, avec matériaux retenus par
  rôle et paramètres. **Plusieurs par surface = variantes**, une seule active.
- **`CalculateurSysteme`** (interface) : `calculer(SurfaceCalculable, SystemeApplique): ResultatSysteme`
  qui renvoie des **lignes de besoin** (matériau + quantité brute + longueurs à découper).
- Les définitions sont enregistrées par tag de service : ajouter un système = une classe + un tag.

### Systèmes initiaux

| Code | Surfaces | Réutilise |
|---|---|---|
| `cloison_ossature` | mur | `OssatureCloisonCalculator` |
| `doublage_ossature` | mur | `OssatureDoublageCalculator` |
| `doublage_colle` | mur | à écrire (complexe collé / plots de colle) |
| `plafond_suspendu_placo` | plafond_pan | à écrire (fourrures, suspentes, plaques) |
| `plafond_autoportant_placo` | plafond_pan | définition seule en v1 |
| `lambris_sur_lattes` | mur, plafond_pan | à écrire |
| `isolation_seule` | mur, plafond_pan | à écrire (isolant + membrane) |

## Paroi existante

- Liste de couches du support actuel : matériau existant (catégorie catalogue `paroi_existante` :
  parpaing, béton, brique, pierre, plâtre, bois…) + épaisseur, ou un **R forfaitaire** saisi.
- Sert au calcul du R total : `RT = Rsi + R(paroi existante) + Σ R(système) + Rse`.
- Sert aux contrôles : épaisseur totale, support compatible avec le système (ex. doublage collé).
- Pas de prix, pas de lot : les articles de cette catégorie ne figurent jamais dans une nomenclature.

## Variantes

- L'utilisateur crée une variante (copie du système actif, modifiable) et la nomme.
- Écran de comparaison pour une surface, une pièce ou un projet : par variante, R/U, coût (selon un
  fournisseur ou le meilleur prix, voir `08-fournisseurs-chiffrage-export.md`), épaisseur totale.
- Le calcul global, le chiffrage et les exports utilisent **la variante active** de chaque surface.
- `ServiceVariantes::comparer(Surface|Piece|Projet): list<ComparaisonVariantes>` calcule sans modifier
  l'état actif ; activer une variante est une action explicite.

## Chaîne de calcul

```
Projet
 └─ pour chaque Piece
     └─ pour chaque Surface (variante active)
          ├─ ThermiqueCalculator  : R par couche, RT, U (paroi existante comprise)
          ├─ CalculateurSysteme   : lignes de besoin (rails, montants, plaques, lattes…)
          ├─ CalculateurCouche    : isolant (surface nette, chute), membrane (rouleaux, recouvrements)
          └─ ResultatSurface
     └─ ResultatPiece
 └─ BesoinsProjet : agrégation par matériau
        └─ MateriauxCalculator : optimisation de coupes 1D, lots entiers, chute, taux de perte
              └─ (les prix sont appliqués au chiffrage, voir 08-…)
```

Les calculs géométriques d'ossature utilisent les coupes `Surface::coupes()` dans le repère de la
surface (voir `03-geometrie.md`), donc valables pour les plafonds à pans. Les besoins de plusieurs
surfaces du même matériau sont **agrégés avant optimisation**, pour que les chutes d'un mur servent à
un autre (option : calcul par surface ou par projet).

## Isolant et thermique

- `IsolantView` : λ, épaisseur, R certifié (prioritaire sur `e/λ`).
- `ThermiqueCalculator::resistanceTotale(Surface, ParoiExistante, list<Couche>, Rsi, Rse)` ; Rsi/Rse selon
  le type de surface, constantes documentées.
- Option v2 : pont thermique d'ossature (fractions de surface).
- Affichage : R par couche, RT, U, objectif saisi, delta.

## Membrane

Surface = surface isolée ; rouleaux = `ceil(surface × (1 + recouvrement) / surface utile du rouleau)` ;
adhésif en ml selon jonctions de lés et périphérie ; R = 0.

## Évolutions de l'existant

1. Passer les calculateurs en mm et leur donner une `SurfaceCalculable` (polygone + ouvertures + repère)
   au lieu de `Mur`.
2. Supprimer le catalogue passé en paramètre : lire les `Materiau` choisis dans le système appliqué.
3. Remplacer les tableaux associatifs de résultat par des objets typés (`LigneBesoin`, `ResultatSysteme`).
4. `MateriauxCalculator` renvoie des `LigneBesoinOptimise` (barres, lots, chutes) sans prix.
5. Le calepinage des plaques (déjà ébauché) devient un composant partagé `CalepinagePlaques`.
6. Le défaut actuel du contrôleur (catalogue codé en dur ignorant la saisie) disparaît avec le point 2.

## Critères d'acceptation

- Les résultats de cloison/doublage restent identiques aux tests de caractérisation de la phase 0
  (tolérance sur les arrondis mm).
- Ajouter un nouveau système n'exige aucune modification du moteur ni des entités.
- Le R total d'un mur de test (paroi existante + 100 mm de laine λ 0,035 + BA13) est conforme au calcul manuel.
- Un plafond double pente avec fourrures est calculé avec les longueurs réelles de pente.
- Deux variantes sur une surface se comparent sans changer la variante active.
- Un projet de 2 pièces produit des besoins agrégés avec lots entiers et chutes.
