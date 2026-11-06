# Étape 06 — Chiffrage, fournisseurs et exports PDF

> Statut : à faire. Ce fichier est renommé en `.DONE.md` (même commit que le code) quand tous les critères ci-dessous sont cochés et les vérifications vertes. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Produire un chiffrage figé (instantané daté) avec choix du fournisseur, livraison et exports PDF de liste de courses.

## Périmètre

`Chiffrage`, `LigneChiffrage`, stratégies de fournisseur, livraison, exports PDF.

## Prérequis

Étapes 01 et 05e terminées.

## Livrables

Entités et migration, `StrategieFournisseur` et implémentations, service de chiffrage, exports PDF, écrans Quote et Exports.

## Spécification

Dépend de [01-catalogue.DONE.md](01-catalogue.DONE.md) (fournisseurs, prix) et de
[05a-moteur-couches.md](05a-moteur-couches.md) (besoins).

### Chiffrage

Un **chiffrage** est un instantané immuable des besoins d'un projet, valorisés à une date donnée.

#### `Chiffrage`
id, projet, libelle, dateCreation, strategieFournisseur, parametres JSON (surcharges de marge de sécurité, fournisseur
imposé, adresse de référence), totalTtc.

#### `LigneChiffrage`
chiffrage, piece (nullable : ligne agrégée), surface (nullable), materiau (identité figée : référence,
libellé, dimensions copiés), fournisseur, referenceFournisseur, quantiteBesoin, margePct, quantiteAvecMarge, unitesParLot, nombreLots, quantiteAchetee,
prixLotTtc, totalTtc, chute, tauxPerte, prixMateriauId (traçabilité).

Quantités : `nombreLots = ceil(quantiteBesoin × (1 + margePct/100) / unitesParLot)` ; voir
`05a-moteur-couches.md`, « Marge de sécurité et arrondi aux lots ». La marge utilisée est copiée dans la ligne.

**Livraison** : si le fournisseur a un `frais_livraison_ttc`, une ligne « Livraison » (une par fournisseur utilisé dans le chiffrage, montant copié) s'ajoute au total ; elle **entre dans la comparaison de `MeilleurPrix`** (voir ci-dessous).

Les lignes **copient** les libellés, dimensions et prix : renommer ou supprimer un article ensuite ne
modifie pas un chiffrage existant.

#### Stratégie de fournisseur
Interface `StrategieFournisseur::choisir(Materiau, QuantiteBrute, DateTimeImmutable, Contexte): ?PrixMateriau`.
La **quantité brute** (besoin avant marge et lots, dans l'unité de l'article) est toujours fournie : les lots
n'ayant pas la même taille d'un fournisseur à l'autre, comparer des prix de lot n'a pas de sens.

| Stratégie | Règle |
|---|---|
| `FournisseurUnique` | Tous les articles chez le même fournisseur ; équivalent chez lui si pas de correspondance exacte, sinon article signalé ; jamais de repli sur un autre fournisseur |
| `MeilleurPrix` | Coût **total** pour la quantité brute : `ceil(quantiteBrute × (1 + marge) / unitesParLot) × prixLotTtc`, pour chaque fournisseur, le plus bas gagne (peut mélanger les fournisseurs) ; l'optimisation de coupes est refaite par candidat si ses dimensions diffèrent ; **la livraison est incluse** : le critère est le coût total du chiffrage = Σ articles + Σ `frais_livraison_ttc` des fournisseurs réellement utilisés (voir « Livraison dans MeilleurPrix ») |
| `PlusProche` | Fournisseur le plus proche de l'adresse du chantier qui vend l'article, repli sur le suivant |
| `Manuelle` | Choix par ligne dans l'écran |

##### Livraison dans `MeilleurPrix`

Le forfait de livraison étant payé une fois par fournisseur utilisé, le choix article par article n'est plus
indépendant : un article légèrement moins cher chez B peut coûter plus cher au total si B ajoute un forfait.
Cette stratégie ne se réduit donc pas à `choisir()` article par article : l'interface expose aussi `choisirEnsemble(list<Besoin>, DateTimeImmutable, Contexte): list<?PrixMateriau>` (les autres stratégies l'implémentent en itérant sur `choisir()`). `MeilleurPrix` minimise le **coût total du projet** :
1. calcule pour chaque article le coût chez chaque fournisseur qui le vend (sans livraison) ;
2. évalue des **ensembles de fournisseurs** : pour chaque ensemble, chaque article va au moins cher de
   l'ensemble (un article absent de l'ensemble est impossible), puis on ajoute les forfaits de l'ensemble ;
3. retient l'ensemble de coût total minimal. Recherche exhaustive jusqu'à 8 fournisseurs candidats
   (2⁸ ensembles) ; au-delà, heuristique gloutonne (ajout du fournisseur qui réduit le plus le coût total) ;
4. un fournisseur sans `frais_livraison_ttc` a une livraison nulle ; un ensemble n'est retenu que s'il couvre
   tous les articles (sinon articles « prix manquant »).

Exemple : A vend les articles 1, 2 et 3 pour 100 € au total, livraison 15 € ; B vend les articles 1 et 2 pour
90 € au total, livraison 20 €, et ne vend pas l'article 3 (30 € chez A). A seul : 115 €. B seul : impossible
(article 3 absent). A+B : 90 + 30 + 15 + 20 = 155 €. → A seul gagne, alors que B est moins cher sur les articles 1–2. Le détail (articles par
fournisseur, forfaits) est conservé dans les lignes de chiffrage.

Articles sans prix chez le fournisseur choisi : ligne marquée « prix manquant », jamais un 0 silencieux.

#### Service
`ChiffrageService::creer(Projet, Strategie, DateTimeImmutable, parametres): Chiffrage` calcule les
besoins (variantes actives), les optimise, applique la stratégie, enregistre. Les besoins étant
agrégés par matériau, les lignes de pièce/surface sont obtenues par répartition proportionnelle de la
quantité (pour les exports par pièce) ; le total par article est celui de l'agrégat.

Comparaison de deux chiffrages (écart par ligne et total) : utile avant/après changement de prix ou de
variante.

### Exports PDF

Liste de courses en **PDF**, avec ou sans prix :

| Export | Contenu |
|---|---|
| Par pièce | Articles nécessaires à une pièce, regroupés par catégorie |
| Par fournisseur | Articles à acheter chez un fournisseur (tous projets/pièces confondus, ou une pièce), avec adresse du fournisseur |
| Projet | Récapitulatif global (option) |

Options communes : avec ou sans prix, regroupement (catégorie / pièce), date et libellé du chiffrage en
entête, mention « Prix TTC à la date du … ».

Mise en œuvre : gabarit Twig → HTML → PDF. Bibliothèque à évaluer (Dompdf en PHP pur pour éviter un
binaire externe ; Snappy/wkhtmltopdf ou Chromium en alternative si le rendu l'exige). Le rendu passe par
un service `ListeCoursesPdf::generer(Chiffrage, Portee, bool $avecPrix): string`. Le même gabarit sert
l'aperçu HTML.

## Critères d'acceptation

- [ ] Un chiffrage créé aujourd'hui ne change pas si un prix est modifié demain.
- [ ] Stratégie `MeilleurPrix` : comparaison sur le coût total de la quantité brute ; si A vend un lot de 5 à 12 € et B un lot de 1 à 3 €, pour un besoin de 5 unités A gagne (12 € contre 15 €) alors que le lot de B est le moins cher ; pour un besoin de 2 unités, B gagne (6 € contre 12 €).
- [ ] Stratégie `PlusProche` : classement correct à partir de coordonnées de test (distance connue).
- [ ] Article absent du fournisseur choisi : ligne « prix manquant » visible, total partiel signalé.
- [ ] PDF par pièce avec et sans prix : mêmes articles, colonnes de prix présentes ou non.
- [ ] PDF par fournisseur : un document par fournisseur (ou une section par fournisseur) avec son adresse.
- [ ] Tests : stratégies (unitaire, prioritaire), service de chiffrage, rendu intermédiaire du PDF (gabarit, pas la bibliothèque PDF ; fichier non vide, texte attendu extrait ou rendu HTML intermédiaire vérifié).

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Aucun pour le moment.
