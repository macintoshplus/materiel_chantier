# Isolation thermique

## Grandeurs

| Symbole | Nom | Unité |
|---|---|---|
| λ (lambda) | conductivité thermique du matériau (plus c'est bas, plus c'est isolant) | W/(m·K) |
| e | épaisseur de la couche | m |
| R | résistance thermique d'une couche | m²·K/W |
| Rsi / Rse | résistances superficielles intérieure / extérieure | m²·K/W |
| R total (RT) | résistance totale de la paroi | m²·K/W |
| U | coefficient de transmission, `U = 1 / RT` | W/(m²·K) |
| Sd | épaisseur d'air équivalente à la diffusion de vapeur (membranes) | m |

## Formules

- Couche : `R = e / λ`. Si le fabricant donne directement `R` pour une épaisseur, on le stocke tel quel
  (le R certifié prime sur le calcul).
- Paroi : `RT = Rsi + Σ R(couches) + Rse` (+ lames d'air si présentes).
- Cloison (deux faces intérieures) : `Rsi + Σ R + Rsi`, sans Rse ; sert à l'acoustique/confort, pas à l'objectif d'isolation de paroi extérieure.
- Valeurs usuelles de Rsi/Rse : mur `0,13 / 0,04`, plafond/toiture (flux ascendant) `0,10 / 0,04`,
  plancher bas `0,17 / 0,04`. Pour un doublage intérieur, la paroi existante (mur porteur) compte comme
  une couche de plus : `R = e / λ` du matériau existant, ou valeur forfaitaire connue (parpaing, brique,
  pierre…) à saisir par l'utilisateur.
- Pour comparer à une exigence (ex. R ≥ 3,7 en mur, ≥ 7 en rampant — **à valider**
  selon la réglementation/aides), le calcul affiche R atteint et R manquant.

## Isolant

Famille et exemples : laine de bois, laine de roche, laine de verre, ouate de cellulose, polystyrène,
polyuréthane, liège… Caractéristiques à stocker (catalogue) : `lambda`, `R` certifié (optionnel),
épaisseur, dimensions du panneau/rouleau, surface par lot (m²), densité, classe de réaction au feu,
`mu`/résistance à la vapeur, mise en œuvre (entre montants, sous ossature, en continu).

Mise en œuvre et incidence sur le calcul :
- **Entre montants** : l'isolant remplit la cavité ; la surface isolée = surface nette − surface des
  montants (pont thermique). Surdimensionner la largeur de 1 à 2 cm pour un maintien par friction (laines semi-rigides).
- **Isolation croisée / sur-isolation** : 2ᵉ couche croisée, sans pont thermique de l'ossature.
- **Panneaux collés** (complexe doublage PSE+BA) : une seule couche, R fabricant.

## Ponts thermiques de l'ossature

Pour une couche hétérogène (isolant + montants), le R équivalent se calcule par la méthode
« combinée » simplifiée : fractions de surface `f_i` (isolant) et `f_m` (montant) :

`U_couche ≈ f_i × (1/R_i) + f_m × (1/R_m)` puis `R = 1/U_couche`.

Fraction de montants typique : `largeur montant / entraxe` (ex. 0,05/0,60 ≈ 8 %). Pour la v1 on peut
ignorer ce pont (R nominal) et l'afficher en option.

## Membranes

Placées après l'isolant, côté chaud (intérieur) : pare-vapeur (Sd élevé, ≥ 18 m), frein-vapeur (Sd
modulable ≈ 0,2–5 m), pare-pluie/écran sous toiture côté froid. Caractéristiques : `sd`, largeur de rouleau,
surface par rouleau, **recouvrement** (≈ 10 cm), adhésif de jonction (mètres linéaires), adhésif périphérique.
Quantité : `surface / surface utile du rouleau`, la surface utile tenant compte du recouvrement.
Une membrane a `R ≈ 0` pour le calcul thermique.

## Paroi existante

Le support à rénover compte dans le R total. On le décrit par des couches (matériau existant + épaisseur,
λ pris dans le catalogue `paroi_existante`) ou par un **R forfaitaire** quand la composition est
inconnue (ex. mur ancien en pierre : valeur saisie par l'utilisateur, avec mention « estimé »).

## Données à afficher par surface

R de chaque couche, RT, U, comparaison à l'objectif, épaisseur totale du complexe (utile pour les
retours aux ouvertures et la perte de surface habitable).
