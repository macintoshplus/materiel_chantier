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
| `marge_securite_pct` | Marge de sécurité en pourcentage ajoutée au besoin avant arrondi au lot (nullable : défaut de catégorie, puis défaut global) |
| `caracteristiques` | **JSON extensible**, propre à la catégorie |

## Catégories et caractéristiques JSON

| Catégorie | Exemples de clés JSON |
|---|---|
| `plaque_platre` | `type` (std, hydro, feu…), `bord` (BA, AB…), `densite`, `pose.entraxe_max`, `pose.pas_vis_*` |
| `profil_ossature` | `famille` (rail, lisse, montant, fourrure, cornière), `serie` (48, 70), `pose.entraxe_defaut`, `pose.portees` (table fabricant : renfort, orientation, entraxe, longueur max, décalage des joints, accessoires) |
| `appui_fixation` | `usage`, `pose.pas_defaut`, `pose.hauteur_defaut` |
| `visserie` | `longueur` (information), `usage` (plaque sur métal/bois…), `pose.epaisseur_fixable_min/max` (critère de sélection), `pose.consommation_par_m2` |
| `accessoire_ossature` | `usage` (liaison de montants décalés ou accolés), `renfort_compatible` |
| `bande_joint` | `type` (papier, armee, plat), `longueur_rouleau` |
| `isolant` | `lambda`, `r_certifie`, `famille` (verre, roche, bois…), `mise_en_oeuvre` (entre/sous ossature), `reaction_feu` |
| `membrane` | `sd`, `usage` (pare-vapeur, frein-vapeur, pare-pluie), `recouvrement_mm` |
| `lambris_latte` | `essence`, `profil`, `largeur_utile` |
| `suspente` | `charge_max`, `pas` |
| `electrique_cable` | `section`, `nb_conducteurs`, `usage` |
| `electrique_appareillage` | `fonction` (interrupteur, prise, va-et-vient…), `type_boite` |
| `electrique_gaine` | `diametre` |
| `electrique_boite` | `type_boite` (encastrement, cloison creuse, saillie), `profondeur`, `diametre` |
| `electrique_connecteur` | `type` (domino, wago…), `nb_entrees` |
| `plomberie_tube`, `plomberie_raccord`, `plomberie_appareil` | `diametre`, `matiere`, `usage` (eau froide, chaude, évacuation) |
| `ventilation_gaine`, `ventilation_bouche`, `ventilation_groupe` | `diametre`, `debit` |
| `paroi_existante` | `lambda`, `r_forfaitaire`, `masse_volumique` (parpaing, brique, pierre, béton, plâtre, bois…) |
| `fixation_support` | `type` (cheville, vis à béton…), `support` (béton, parpaing, brique), `charge_max`, règle de quantité (pas ou nombre par m) |
| `bande_resiliente` | `epaisseur`, `largeur`, `longueur_rouleau` ; quantité = longueur de rail/lisse au contact du support |
| `enduit` | `usage` (joint, finition), `consommation_kg_par_m2` ; quantité = surface de plaques × consommation |

Les clés sont décrites par un **schéma** par catégorie (liste des champs, type, obligatoire) : sert à
valider l'import, générer le formulaire d'ajout et documenter ce que lit chaque calculateur.

## Données de pose (groupe `pose`)

Les entraxes maximaux, pas de fixation, portées limites et plages d'épaisseur fixable viennent des fiches
fabricants (DTU, Avis Techniques) et varient d'une marque à l'autre : elles sont stockées **sur
l'article** sous la clé `pose`. Le choix effectif de la mise en œuvre (entraxe retenu, renfort) se fait au
niveau du système, avec des bornes issues de ces données (voir `developpement/04-systemes-et-calcul.md`).

La table de portées d'un montant liste, par renfort (`simple`, `double_accole`, `double_decale`) et par
entraxe **et par orientation de pose** (verticale ou horizontale), la longueur maximale admissible du
montant final ; pour un renfort décalé elle donne aussi l'écart minimal entre les joints des deux lignes,
le pas des vis d'assemblage et les accessoires (éclisses de liaison à chaque joint) avec leur règle de
quantité.

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
