# Catalogue, fournisseurs et prix (code)

Connaissance : [06-catalogue-materiaux.md](../connaissances/06-catalogue-materiaux.md).

## Entité `Materiau`

| Colonne | Type | Remarque |
|---|---|---|
| id | int | |
| reference | string(64) unique | |
| libelle | string | |
| categorie | string(48), indexé | valeur du schéma de catégorie |
| longueur, largeur, epaisseur, hauteur | int nullable | mm |
| unite_vente | string | `piece`, `ml`, `m2`, `rouleau`… |
| unites_par_lot | int ≥ 1 | valeur par défaut ; un prix peut la surcharger |
| poids | decimal(8,3) nullable | kg par unité |
| caracteristiques | JSON | validé contre le schéma de la catégorie |
| actif | bool | |

Le **prix n'est plus porté par `Materiau`** : il dépend du fournisseur.

## Entité `Fournisseur`

| Colonne | Type |
|---|---|
| id | int |
| nom | string |
| adresse (ligne 1, ligne 2, code postal, ville, pays) | string |
| latitude, longitude | decimal(10,7), nullables mais requis pour le calcul de distance |
| site_web, telephone | string nullable |
| actif | bool |

Service `DistanceCalculator` : distance à vol d'oiseau (formule de haversine) entre deux coordonnées GPS ;
sert à classer les fournisseurs par proximité du chantier (adresse du projet, optionnelle).
Saisie manuelle des coordonnées au départ ; géocodage d'adresse optionnel plus tard.

## Entité `PrixMateriau` (historique)

| Colonne | Type | Remarque |
|---|---|---|
| id | int | |
| materiau | FK | |
| fournisseur | FK | |
| reference_fournisseur | string nullable | référence chez le fournisseur |
| prix_lot_ttc | decimal(10,2) | prix TTC d'un lot **(HT et TVA non gérés pour l'instant)** |
| unites_par_lot | int nullable | surcharge du lot par défaut |
| taux_tva | decimal(4,2) nullable | réservé pour le passage futur en HT |
| date_debut | datetime | |
| date_fin | datetime nullable | `null` = prix courant |
| source | string | `manuel`, `import` |

Règles : on ne modifie jamais un prix, on en **crée un nouveau** qui clôt le précédent
(`date_fin` = `date_debut` du nouveau). Au plus un prix courant par couple (matériau, fournisseur).
`PrixService::prixA(Materiau, Fournisseur, DateTimeImmutable): ?PrixMateriau`.

## Schémas de catégorie

Classe `CategorieMateriau` (interface) : `code()`, `libelle()`, `champs(): list<ChampCaracteristique>`.
Chaque `ChampCaracteristique` : clé, type (`int`, `float`, `string`, `enum`, `bool`), obligatoire, unité,
choix. Les catégories sont enregistrées via un tag de service (`app.categorie_materiau`) : ajouter une
catégorie = ajouter une classe.

Services :
- `CaracteristiquesValidator` : valide le JSON ; contrainte `#[CaracteristiquesValides]` sur `Materiau`.
- `MateriauFormBuilder` : génère le sous-formulaire des caractéristiques à partir du schéma.
- Accès typé pour les calculateurs : `IsolantView`, `MembraneView`, `ProfilView`… (façades en lecture sur
  `Materiau`, exception claire si un champ obligatoire manque).

## Reprise de l'existant

Commande `app:catalogue:importer-defauts` qui crée les articles correspondant aux objets codés en dur dans
le contrôleur actuel (montant 3 m, rail 3 m, plaque 2,5×1,2, joints, appuis) et un fournisseur « Défaut »
portant leurs prix. Le contrôleur lit ensuite le catalogue au lieu de construire les objets à la main.

## Écrans

- Matériaux : liste filtrable par catégorie, formulaire dynamique, duplication.
- Fournisseurs : CRUD avec carte (position GPS), liste des articles vendus.
- Prix : par article, tableau « fournisseur × prix courant » + historique ; saisie d'un nouveau prix.
- Import CSV (articles et prix, une ligne = un prix par fournisseur).

## Critères d'acceptation

- Ajouter une catégorie ne demande aucune migration.
- Un article avec une caractéristique obligatoire manquante est refusé.
- Changer un prix conserve l'ancien dans l'historique ; `prixA` retourne le bon prix à une date passée.
- Un article peut avoir des prix différents chez deux fournisseurs, avec des tailles de lot différentes.
- Les calculateurs ne reçoivent plus d'objets construits en dur.
- Tests : validation de schéma, historique de prix, haversine (cas connu), CRUD fonctionnel.
