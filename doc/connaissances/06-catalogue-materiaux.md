# Catalogue de matériaux

Le catalogue est la liste des articles achetables. Il est **commun à tous les projets**, un projet peut
l'enrichir ou surcharger un prix.

## Champs communs

| Champ | Description |
|---|---|
| `reference` | Code interne ou fabricant |
| `libelle` | Nom lisible |
| `categorie` | Voir ci-dessous |
| `longueur`, `largeur`, `epaisseur`, `hauteur` | mm, nullable selon l'article |
| `unite_vente` | `piece`, `ml`, `m2`, `kg`, `rouleau`… |
| `unites_par_lot` | Nombre d'éléments dans un lot (on achète des lots entiers) |
| `prix_lot` | Prix du lot HT |
| `poids` | kg par élément (pour la logistique : charge, nombre de voyages) |
| `caracteristiques` | **JSON extensible**, propre à la catégorie |

## Catégories et caractéristiques JSON

| Catégorie | Exemples de clés JSON |
|---|---|
| `plaque_platre` | `type` (std, hydro, feu…), `bord` (BA, AB…), `densite` |
| `profil_ossature` | `famille` (rail, montant, fourrure, cornière), `serie` (48, 70), `entraxe_defaut` |
| `appui_fixation` | `usage`, `pas_defaut`, `hauteur_defaut` |
| `bande_joint` | `type` (papier, armee, plat), `longueur_rouleau` |
| `isolant` | `lambda`, `r_certifie`, `famille` (verre, roche, bois…), `mise_en_oeuvre` (entre/sous ossature), `reaction_feu` |
| `membrane` | `sd`, `usage` (pare-vapeur, frein-vapeur, pare-pluie), `recouvrement_mm` |
| `lambris_latte` | `essence`, `profil`, `largeur_utile` |
| `suspente` | `charge_max`, `pas` |
| `electrique_cable` | `section`, `nb_conducteurs`, `usage` |
| `electrique_appareillage` | `fonction` (interrupteur, prise, va-et-vient…), `type_boite` |
| `electrique_gaine` | `diametre` |

Les clés sont décrites par un **schéma** par catégorie (liste des champs, type, obligatoire) : sert à
valider l'import, générer le formulaire d'ajout et documenter ce que lit chaque calculateur.

## Règles

- Le calculateur n'utilise que des champs déclarés pour la catégorie ; il échoue explicitement si un
  champ obligatoire manque (pas de valeur par défaut silencieuse).
- Une catégorie nouvelle = un schéma, pas une migration de base.
- Les prix sont horodatés (date de validité) pour pouvoir recalculer un devis ancien.
