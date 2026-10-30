# Catalogue de matériaux

Le catalogue est la liste des articles achetables. Il est **commun à tous les projets** et dynamique
(catégories et caractéristiques ajoutables). Le **prix n'est pas une propriété de l'article** : il dépend
du fournisseur.

## Champs communs

| Champ | Description |
|---|---|
| `reference` | Code interne ou fabricant |
| `libelle` | Nom lisible |
| `categorie` | Voir ci-dessous |
| `longueur`, `largeur`, `epaisseur`, `hauteur` | mm, nullable selon l'article |
| `unite_vente` | `piece`, `ml`, `m2`, `kg`, `rouleau`… |
| `unites_par_lot` | Nombre d'éléments dans un lot par défaut (on achète des lots entiers) |
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

## Fournisseurs et prix

- Un **fournisseur** a un nom, une adresse et une position GPS (latitude/longitude). La position sert à
  classer les fournisseurs par distance au chantier.
- Un **prix** relie un article à un fournisseur : prix du lot **TTC** (pour commencer), référence chez le
  fournisseur, taille de lot éventuellement différente de celle de l'article, dates de validité.
- **Historique** : on ne modifie pas un prix, on en ajoute un nouveau qui clôt l'ancien. On peut ainsi
  retrouver le prix à une date donnée et expliquer l'écart entre deux chiffrages.
- Un même article peut être vendu par plusieurs fournisseurs à des prix et conditionnements différents ;
  il peut aussi ne pas être vendu par certains (le chiffrage signale alors « prix manquant »).
- Passage futur en HT : le taux de TVA est stocké (nullable) pour permettre ce changement sans migration
  lourde des prix.
- Catégorie spéciale `paroi_existante` (parpaing, béton, brique, pierre…) : articles **sans prix ni lot**,
  utilisés seulement pour décrire un support existant (λ, épaisseur, R) dans les calculs thermiques.
