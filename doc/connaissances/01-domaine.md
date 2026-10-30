# Domaine et vocabulaire

## Hiérarchie

```
Projet
 └─ Pièce (1..n)
     ├─ Forme au sol (polygone)
     ├─ Surfaces
     │   ├─ Murs (1 par arête du polygone au sol, plus éventuellement des pignons)
     │   ├─ Sol
     │   └─ Plafond (1..n pans : plat, rampants, etc.)
     │        └─ Ouvertures (0..n par surface)
     └─ Éléments électriques (interrupteurs, prises, luminaires, radiateurs…)
```

- **Projet** : un chantier. Plusieurs projets coexistent. Il porte la liste de matériaux globale
  (somme des pièces) et ses propres paramètres par défaut (marge de chute, TVA éventuelle).
- **Pièce** : espace délimité. Son sol est un polygone ; ses murs en découlent.
- **Surface** : face plane à traiter (mur, pan de plafond, sol). Toute surface est une forme géométrique
  2D placée dans l'espace 3D, avec des **arêtes** reliées aux arêtes d'autres surfaces.
- **Ouverture** : porte, fenêtre, baie, trappe, conduit… Forme 2D positionnée dans le repère de la
  surface qui la porte (un velux est une ouverture d'un pan de plafond).
- **Système** : la solution de rénovation choisie pour une surface (doublage, cloison, plafond
  suspendu…). Il est composé de **couches** ordonnées (isolant, membrane, ossature, parement…).
- **Matériau** : article du catalogue (dimensions, conditionnement, poids, caractéristiques JSON).
- **Nomenclature (BOM)** : liste des matériaux, quantités, lots, prix, chutes, résultat du calcul.

## Ce qui existe déjà dans le code

| Existant | Rôle futur |
|---|---|
| `Model\Mur` (largeur, hauteur, ouvertures en tableau, catalogue) | Devient une `Surface` de type mur, rectangulaire dans un premier temps |
| `Model\Catalogue\*` (Montant, Rail, PlaquePlatre, joints, appuis) | Devient des lignes du catalogue générique (`Materiau`) |
| `Model\Chantier\*` (pièces à découper : montants, rails, plaques) | Reste le résultat intermédiaire des calculateurs |
| `OssatureCloisonCalculator` / `OssatureDoublageCalculator` | Deviennent deux implémentations de système |
| `MateriauxCalculator` | Reste le moteur d'optimisation de coupes et de lots |
| `OuvertureOverlapChecker`, `PositionsIsValid` | Validation générique des ouvertures sur une surface |

## Unités

Toutes les longueurs sont stockées en **millimètres entiers** (voir `developpement/03-geometrie.md`),
les surfaces en m², les poids en kg, les prix en euros HT (décimal à 2 chiffres).
Le code actuel travaille en mètres flottants : c'est à migrer.
