# Import DXF

Le DXF (AutoCAD Drawing Exchange Format) sert à récupérer les **mesures de base d'une pièce** depuis un plan
(relevé, plan d'architecte, export d'application de relevé de mesures) au lieu de les saisir à la main.

## Format

Fichier texte (ASCII, le plus courant) ou binaire (plus rare) : suite de paires `code de groupe` / `valeur`,
organisée en sections : `HEADER`, `TABLES` (calques/layers), `BLOCKS`, `ENTITIES`. L'entête contient
l'unité du dessin (`$INSUNITS` : 1 pouce, 4 mm, 5 cm, 6 m ; `0` = non spécifiée) : il faut
**toujours proposer à l'utilisateur de confirmer l'unité**.

## Entités utiles

| Entité | Usage pour nous |
|---|---|
| `LWPOLYLINE` | Contour de pièce (le plus fréquent), fermée si le drapeau 70 bit 1 est posé ; sommets avec « bulge » pour les arcs (courbe : non pris en charge) |
| `POLYLINE` / `VERTEX` | Ancienne forme du précédent, parfois 3D |
| `LINE` | Murs dessinés segment par segment : à **chaîner** pour reconstituer un contour |
| `ARC`, `CIRCLE` | Murs courbes, colonnes : **non pris en charge** pour le moment (avertissement, import refusé pour le contour concerné) |
| `INSERT` (+ `BLOCKS`) | Symboles insérés : portes, fenêtres, appareillage électrique, avec position, rotation, échelle |
| `TEXT` / `MTEXT` | Libellés de pièces, cotes : aide au nommage, sans valeur géométrique |
| `DIMENSION` | Cotes : à ignorer pour la géométrie |

Les **calques** (`WALLS`, `A-WALL`, `DOORS`, `WINDOWS`, `ELEC`…) permettent de filtrer, mais leur nommage
n'est pas standardisé : l'utilisateur doit pouvoir associer chaque calque à un rôle.

## Particularités et pièges

- Plan en **vue de dessus** : donne le polygone au sol, pas la hauteur. La hauteur sous plafond est saisie
  à part (ou lue d'un plan de coupe/élévation en phase ultérieure).
- Les contours de murs peuvent être dessinés avec **épaisseur** (deux traits par mur) : le polygone de la
  pièce utile est la face intérieure. Prévoir un choix « contour intérieur » vs « axe du mur » (décalage).
- Lignes non jointes (écarts de 1–5 mm) : chaînage avec tolérance configurable.
- Contours superposés (plusieurs pièces, cotation, mobilier) : l'utilisateur sélectionne les contours
  à importer, avec aperçu.
- Portes/fenêtres : blocs `INSERT` donnant position et largeur ; hauteur et allège absentes, à saisir
  ou déduire d'attributs du bloc (`ATTRIB`) quand ils existent. Sinon, on crée l'ouverture avec des
  valeurs par défaut modifiables.
- Axe Y orienté vers le haut ; l'orientation des sommets peut être horaire : normaliser en anti-horaire.
- Coordonnées souvent très grandes (repère de site) : recaler sur l'origine de la pièce à l'import.
- Fichiers DXF 3D (extrusions, `3DFACE`, `MESH`) : hors périmètre v1.
- Fichiers non fiables : taille maximale, limite d'entités, aucune exécution de contenu.

## Ce que l'on importe

1. **Obligatoire** : un contour fermé → forme au sol de la pièce (un mur par arête).
2. **Optionnel** : blocs de portes/fenêtres → ouvertures positionnées sur le mur le plus proche ;
   blocs électriques → éléments électriques ; textes → nom de pièce.

Format de sortie du parseur : une structure neutre (liste de polylignes en mm avec calque et statut
fermé/ouvert, liste d'insertions de blocs avec position/rotation), indépendante du format DXF.
