# Étape 06 — Chiffrage, fournisseurs et exports PDF

> Statut : terminée (vérifiée le 2026-11-06 : phpunit, phpstan niveau 8, schéma en phase). La mise en page du PDF et les écrans restent à voir en navigateur. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

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
[05a-moteur-couches.DONE.md](05a-moteur-couches.DONE.md) (besoins).

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
`05a-moteur-couches.DONE.md`, « Marge de sécurité et arrondi aux lots ». La marge utilisée est copiée dans la ligne.

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

- [x] Un chiffrage créé aujourd'hui ne change pas si un prix est modifié demain.
- [x] Stratégie `MeilleurPrix` : comparaison sur le coût total de la quantité brute ; si A vend un lot de 5 à 12 € et B un lot de 1 à 3 €, pour un besoin de 5 unités A gagne (12 € contre 15 €) alors que le lot de B est le moins cher ; pour un besoin de 2 unités, B gagne (6 € contre 12 €).
- [x] Stratégie `PlusProche` : classement correct à partir de coordonnées de test (distance connue).
- [x] Article absent du fournisseur choisi : ligne « prix manquant » visible, total partiel signalé.
- [x] PDF par pièce avec et sans prix : mêmes articles, colonnes de prix présentes ou non.
- [x] PDF par fournisseur : un document par fournisseur (ou une section par fournisseur) avec son adresse.
- [x] Tests : stratégies (unitaire, prioritaire), service de chiffrage, rendu intermédiaire du PDF (gabarit, pas la bibliothèque PDF ; fichier non vide, texte attendu extrait ou rendu HTML intermédiaire vérifié).

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

- **Montants** : entiers en centimes partout dans `Chiffrage`/`LigneChiffrage` (`Montant::centimes` convertit les prix décimaux du catalogue sans flottant) ; pourcentages en centièmes de pourcent, quantités avec marge en millièmes. `TypeStrategie` et `TypeLigneChiffrage` sont des enums PHP persistés par valeur.
- **Trois types de lignes** : `article` (achat agrégé du projet, base du total), `repartition` (part d'un achat par pièce/surface, au prorata du besoin brut, plus fort reste : la somme des parts égale exactement la ligne agrégée ; non comptée dans le total) et `livraison` (un forfait par fournisseur effectivement utilisé, seulement si frais > 0).
- **Moteur réutilisé, deux modifications minimes** : `BesoinsProjet` gagne `ajouterBesoin()` et `reunir()` ; `CalculProjet::calculer()` est découpé en `preparer(Projet): PreparationCalcul` (réutilisée par le chiffrage) puis regroupement. Résultats des étapes 05a-05e inchangés (tests existants verts).
- **Un passage du moteur par fournisseur retenu** : évite qu'un même article équivalent proposé par deux fournisseurs ait deux prix dans un même passage. Un groupe `manquant` regroupe les articles sans prix (ligne « prix manquant », total 0, total du chiffrage partiel signalé).
- **Dompdf ^3.1** installé (dépendance disponible malgré le pare-feu). Même gabarit Twig `export/liste.html.twig` pour l'aperçu HTML et le PDF (`ListeCoursesPdf::html()` et `generer()`). La signature `generer(Chiffrage, Portee, bool)` utilise `PorteeExport` (type piece/fournisseur/projet, cible, regroupement catégorie/pièce, pièce optionnelle pour l'export fournisseur).
- **Stratégies** : `PlusProche` sans position de chantier ou fournisseur sans position : ordre alphabétique, sans position en dernier. `FournisseurUnique` sans repli sur un autre fournisseur. `Manuelle` : un fournisseur par référence d'article (liste déroulante par article, pas de saisie par surface). `MeilleurPrix` : recherche exhaustive jusqu'à 8 fournisseurs candidats, glouton au-delà.
- **Simplifications d'interface** : marges du chiffrage non saisissables à l'écran (paramètre `marges` du service uniquement) ; adresse du chantier prise dans le projet ; `sansArticle` du calcul non persisté ; avertissements du calcul stockés en JSON dans `chiffrage.avertissements` (colonne ajoutée à la migration) et traduits à l'affichage.
- **Non vérifié** : rendu visuel du PDF (mise en page, pagination, polices) et écrans dans un navigateur ; seuls le gabarit (HTML), l'en-tête `%PDF` et le code 200 des pages sont testés. Extensions PHP requises par Dompdf (mbstring, dom, gd) présentes en test.
- **Migration** : `Version20261106194535` générée par `doctrine:migrations:diff`, appliquée à la base de développement.
