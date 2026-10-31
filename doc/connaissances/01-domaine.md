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
     │        ├─ Ouvertures (0..n par surface)
     │        ├─ Paroi existante (composition du support actuel)
     │        └─ Systèmes de rénovation (variantes, une active)
     ├─ Cloisons (0..n) : tracé, 2 faces, ouvertures, système
     └─ Équipements (électricité, plomberie, ventilation)
```

- **Projet** : un chantier. Plusieurs projets coexistent. Il porte les chiffrages et ses paramètres par
  défaut (marges de sécurité). Il pourra porter une adresse de chantier (utile pour trouver le fournisseur le
  plus proche).
- **Pièce** : un **volume entouré de murs physiques** (existants). Son sol est un polygone ; ses murs en
  découlent. Ce volume ne change pas quand on y ajoute un ouvrage : doublages, cloisons et équipements sont
  des ouvrages **dans** la pièce.
- **Cloison** : paroi **neuve** construite dans une pièce, sur ossature autonome. Elle n'est pas un mur
  physique de la pièce : elle a un **tracé** modifiable (segment au sol), une hauteur déduite du plafond,
  des **ouvertures** comme un mur, et **deux faces** (côté A et côté B) qui reçoivent chacune leur parement.
  L'ossature et l'isolant sont **communs** aux deux faces ; le parement est géré **par face**.
- **Face** : une des deux surfaces d'une cloison (parement, calepinage, joints propres à la face).
- **Jonction** : liaison d'une arête avec une autre ; pour une cloison, ses extrémités sont ancrées sur un
  mur, une autre cloison ou un doublage de mur (ou libres).
- **Surface** : face **plane** à traiter (mur, pan de plafond, sol). Les murs courbes ne sont pas pris en
  charge. Chaque surface a un repère 2D `(u, v)` et des **arêtes** reliées aux arêtes d'autres surfaces.
- **Ouverture** : porte, fenêtre, baie, trappe, conduit… Forme 2D positionnée dans le repère de la surface
  (un velux est une ouverture d'un pan de plafond).
- **Paroi existante** : ce sur quoi on travaille (parpaing, brique, pierre…), avec épaisseur ou R forfaitaire.
- **Système** : la solution de rénovation choisie pour une surface (doublage, cloison, plafond
  suspendu…), composé de **couches** ordonnées (isolant, membrane, ossature, parement…). Une surface peut
  porter plusieurs **variantes** de système ; une seule est active et alimente le chiffrage.
- **Matériau** : article du catalogue (dimensions, conditionnement, poids, caractéristiques JSON).
- **Fournisseur** : nom, adresse, position GPS ; propose des prix pour des matériaux.
- **Prix** : TTC, par lot, par fournisseur, avec historique (date de début, date de fin).
- **Équipement / Réseau** : élément posé dans une pièce (interrupteur, prise, évier, bouche de VMC…) et
  réseau qui les relie (circuit électrique, eau froide, évacuation, ventilation).
- **Besoin** : quantité calculée d'un matériau, avant application d'un prix.
- **Chiffrage** : instantané immuable des besoins d'un projet, valorisés à une date avec des prix
  de fournisseurs.
- **Liste de courses** : export PDF d'un chiffrage, par pièce ou par fournisseur, avec ou sans prix.

## Ce qui existe déjà dans le code

| Existant | Rôle futur |
|---|---|
| `Model\Mur` (largeur, hauteur, ouvertures en tableau, catalogue) | Devient une `Surface` de type mur rectangulaire |
| `Model\Catalogue\*` (Montant, Rail, PlaquePlatre, joints, appuis) | Devient des lignes du catalogue générique (`Materiau`) |
| `Model\Chantier\*` (pièces à découper : montants, rails, plaques) | Reste le résultat intermédiaire des calculateurs |
| `OssatureCloisonCalculator` / `OssatureDoublageCalculator` | Deviennent deux implémentations de système |
| `MateriauxCalculator` | Reste le moteur d'optimisation de coupes et de lots (sans prix) |
| `OuvertureOverlapChecker`, `PositionsIsValid` | Validation générique des ouvertures sur une surface |

## Unités

Longueurs en **millimètres entiers**, surfaces en m², poids en kg, prix en euros **TTC** (décimal à
2 chiffres) pour l'instant. Le code actuel travaille en mètres flottants : c'est à migrer.
