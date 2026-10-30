# Interface utilisateur

Stack existante : Twig, Stimulus, Turbo, Bootstrap, AssetMapper. Pas de framework JS lourd.

## Parcours

```
Projets (liste, création)
 └─ Projet : pièces, nomenclature globale, import DXF, export
     └─ Pièce : forme au sol, murs, plafond (pans), éléments électriques
         └─ Surface : ouvertures, système appliqué (couches), thermique, résultat
Catalogue : liste, ajout, édition, import CSV
```

## Écrans et composants

| Écran | Contenu |
|---|---|
| Liste des projets | création, duplication, archivage |
| Projet | pièces avec surface totale, bouton « calculer », nomenclature agrégée, accès import DXF |
| Pièce | aperçu SVG du sol et des murs dépliés, saisie du polygone (tableau de sommets, ou import DXF) |
| Surface | schéma SVG (généralisation du schéma de mur actuel), ouvertures en liste dynamique (Stimulus), choix du système → couches |
| Système | sélection des matériaux par rôle depuis le catalogue (filtre par catégorie), épaisseur et R en direct |
| Électricité | placement des éléments sur le schéma de surface, liste des circuits |
| Nomenclature | tableau par matériau, lots, prix, chute, filtre par pièce/surface, export CSV puis PDF |
| Import DXF | assistant en étapes (voir `03-geometrie.md`) |

## Principes

- Formulaires Symfony, validation côté serveur, aperçus mis à jour par Turbo/Stimulus.
- Aperçu SVG généré côté serveur (Twig) à partir des objets géométriques, pour garder un seul modèle.
- Les contrôles métier (ouverture hors surface, R insuffisant) s'affichent sans bloquer l'enregistrement.
- Accessibilité de base (labels, contrastes, navigation clavier) et usage tablette sur chantier.
- Tout texte en français, via les fichiers de traduction.

## Critères d'acceptation

- Parcours complet réalisable sans toucher à l'URL (navigation, fil d'Ariane).
- L'écran actuel `/doublage/mur` reste accessible jusqu'à ce que l'écran de surface le remplace.
- Tests fonctionnels (WebTestCase) sur la création de projet, ajout de pièce, calcul de nomenclature.
