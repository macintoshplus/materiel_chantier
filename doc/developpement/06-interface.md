# Interface utilisateur

Stack existante : Twig, Stimulus, Turbo, Bootstrap, AssetMapper. Pas de framework JS lourd.

## Parcours

```
Projets (liste, création)
 └─ Projet : pièces, chiffrages, sources de mesures, exports
     └─ Pièce : forme au sol, murs, plafond (pans), équipements
         └─ Surface (mur, pan) : ouvertures, paroi existante, systèmes (variantes), thermique, résultat
Catalogue : matériaux, fournisseurs, prix
```

## Aperçus

| Objet | Aperçu |
|---|---|
| Mur, pan de plafond (surface) | **2D**, dans le repère de la surface : contour, ouvertures, ossature, plaques, équipements. Généralisation du schéma SVG actuel |
| Pièce | **2D vue de dessus** (sol, murs, ouvertures, équipements) en phase 3 ; **vue 3D** en phase 9 (murs, plafond à pans, ouvertures) |
| Import | aperçu SVG des contours détectés, jamais d'import sans aperçu |

Les aperçus 2D sont générés côté serveur (Twig/SVG) à partir des objets géométriques, pour garder un seul
modèle. La vue 3D est un composant Stimulus consommant un JSON de la pièce (bibliothèque 3D chargée via
l'importmap, version figée).

## Écrans et composants

| Écran | Contenu |
|---|---|
| Liste des projets | création, duplication, archivage |
| Projet | pièces avec surface totale, adresse du chantier (optionnelle), chiffrages, import de mesures, exports |
| Pièce | aperçu 2D du dessus, liste des murs et pans, plafond (type de toit), équipements |
| Surface | aperçu 2D, ouvertures (liste dynamique Stimulus), paroi existante, **variantes** de système |
| Système / couches | sélection des matériaux par rôle depuis le catalogue (filtre catégorie), épaisseur et R en direct |
| Comparaison de variantes | tableau variante × (R/U, épaisseur, coût) pour une surface, une pièce ou un projet |
| Équipements | placement sur l'aperçu d'une surface, listes par réseau (électricité, plomberie, ventilation) |
| Chiffrage | stratégie de fournisseur, lignes (prix manquants en évidence), total TTC, comparaison de deux chiffrages |
| Exports | liste de courses PDF par pièce ou par fournisseur, case « avec prix » |
| Import de mesures | assistant en étapes : saisie par côtés/angles, DXF, CSV |
| Matériaux, fournisseurs, prix | CRUD, carte GPS du fournisseur, historique de prix |

## Principes

- Formulaires Symfony, validation côté serveur, aperçus mis à jour par Turbo/Stimulus.
- Les contrôles métier (ouverture hors surface, R insuffisant, prix manquant) s'affichent sans bloquer
  l'enregistrement.
- Accessibilité de base (labels, contrastes, navigation clavier) et usage tablette sur chantier.
- Tout texte en français, via les fichiers de traduction.
- Prix affichés TTC avec mention explicite.

## Critères d'acceptation

- Parcours complet réalisable sans toucher à l'URL (navigation, fil d'Ariane).
- L'écran actuel `/doublage/mur` reste accessible jusqu'à ce que l'écran de surface le remplace.
- L'aperçu 2D d'un pan de plafond incliné respecte la pente (longueurs réelles).
- Tests fonctionnels (WebTestCase) : création de projet, ajout de pièce, calcul, génération d'un PDF.
