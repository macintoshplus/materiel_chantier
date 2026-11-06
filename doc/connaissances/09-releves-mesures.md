# Relevés de mesures

Un chantier n'a pas toujours un plan. Trois sources de mesures alimentent la même création de pièce :

| Source | Quand |
|---|---|
| **Saisie par côtés et angles** | Relevé au mètre : on mesure chaque côté et, si l'angle n'est pas droit, l'angle ou une diagonale |
| **DXF** | Plan d'architecte ou export (voir [07-import-dxf.md](07-import-dxf.md)) |
| **CSV de relevé laser** | Télémètre laser/application de relevé qui exporte des cotes ou des points |

## Saisie par côtés et angles

- Les cotes sont relevées au **nu brut** (avant doublage ou cloison) ; une option permet de déduire l'épaisseur d'un doublage existant.
- On parcourt la pièce dans le sens anti-horaire en notant : longueur du côté 1, angle au sommet suivant
  (par défaut 90°), longueur du côté 2…
- Un polygone se **ferme** si la somme des vecteurs est nulle ; les erreurs de relevé donnent un **écart de
  fermeture** (quelques mm à quelques cm). Règle : on affiche l'écart et on le répartit en dessous d'un
  seuil (par exemple 20 mm) ; au-dessus, l'utilisateur corrige.
- Une pièce rectangulaire se décrit avec deux côtés ; on propose aussi la **vérification par les diagonales**
  (égalité des diagonales = angles droits) pour détecter un faux équerrage.
- Pièces non orthogonales : l'angle est donné en degrés (rapporteur, appli) ; l'erreur d'un degré sur
  3 m vaut ≈ 5 cm.

## CSV de relevé laser

Deux formes courantes :
- **Points** `x;y` : sommets du contour déjà positionnés ; l'unité (mm, cm, m) est indiquée par
  l'utilisateur.
- **Cotes** `cote;angle` : mêmes données que la saisie manuelle.

Séparateur `;` ou `,`, décimale `,` ou `.`, entête optionnelle. On rejette les lignes invalides en
donnant le numéro de ligne ; aucun import partiel.

## Hauteurs

Aucune source ne donne la hauteur par un plan de dessus : la hauteur sous plafond est saisie (une valeur
par pièce, ou par mur/pan pour un plafond non plat), éventuellement relevée avec un laser en plusieurs
points.

## Cas non pris en charge

Murs courbes (voir [02-geometrie.md](02-geometrie.md)), nuages de points 3D, plans scannés (images).
