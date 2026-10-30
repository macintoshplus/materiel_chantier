# Systèmes de revêtement et moteur de calcul

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

## Concepts

- **`DefinitionSysteme`** : code, libellé, types de surface compatibles (`mur`, `plafond_pan`, `sol`),
  couches possibles (rôle, obligatoire, catégorie de matériau attendue), paramètres (schéma), et un
  **calculateur** associé.
- **`SystemeApplique`** (entité) : choix d'une définition pour une surface, avec matériaux retenus par
  rôle et paramètres.
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

## Chaîne de calcul

```
Projet
 └─ pour chaque Piece
     └─ pour chaque Surface
          ├─ ThermiqueCalculator  : R par couche, RT, U
          ├─ CalculateurSysteme   : lignes de besoin (rails, montants, plaques, lattes…)
          ├─ CalculateurCouche    : isolant (surface nette, chute), membrane (rouleaux, recouvrements, adhésif)
          └─ ResultatSurface
     └─ ResultatPiece
 └─ NomenclatureProjet : agrégation par matériau
        └─ MateriauxCalculator : optimisation de coupes 1D, lots entiers, prix, chute, taux de perte
```

Les besoins de plusieurs surfaces du même matériau sont **agrégés avant optimisation**, pour que les
chutes d'un mur servent à un autre (option : calcul par surface ou par projet).

## Isolant et thermique

- `IsolantView` : λ, épaisseur, R certifié (prioritaire sur `e/λ`).
- `ThermiqueCalculator::resistanceTotale(Surface, list<Couche>, Rsi, Rse)` ; Rsi/Rse selon le type de
  surface (mur, plafond, sol) définis en constantes documentées.
- Mur existant : l'utilisateur renseigne la composition (matériau + épaisseur, ou un R forfaitaire).
- Option v2 : pont thermique d'ossature (fractions de surface).
- Affichage : R par couche, RT, U, objectif saisi, delta.

## Membrane

Surface = surface isolée ; rouleaux = `ceil(surface × (1 + recouvrement) / surface utile du rouleau)` ;
adhésif en ml selon jonctions de lés et périphérie ; R = 0.

## Évolutions de l'existant

1. Passer les calculateurs en mm et leur donner une `SurfaceCalculable` (polygone + ouvertures + hauteur
   utile) au lieu de `Mur`.
2. Supprimer le catalogue passé en paramètre : lire les `Materiau` choisis dans le système appliqué.
3. Remplacer les tableaux associatifs de résultat par des objets typés (`LigneBesoin`, `ResultatSystème`).
4. Une classe `Nomenclature` + `LigneNomenclature` remplace le tableau renvoyé par `MateriauxCalculator`.
5. Le calepinage des plaques (déjà ébauché) devient un composant partagé `CalepinagePlaques`.

## Critères d'acceptation

- Les résultats de cloison/doublage restent identiques aux tests de caractérisation de la phase 0
  (avec tolérance sur les arrondis mm).
- Ajouter un nouveau système n'exige aucune modification du moteur ni des entités.
- Le R total d'un mur de test (ex. 100 mm de laine λ 0,035 + BA13) est conforme au calcul manuel.
- Un projet de 2 pièces produit une nomenclature agrégée avec lots entiers et chutes.
