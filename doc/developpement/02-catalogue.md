# Catalogue (code)

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
| unites_par_lot | int ≥ 1 | |
| prix_lot | decimal(10,2) | HT |
| poids | decimal(8,3) nullable | kg par unité |
| caracteristiques | JSON | validé contre le schéma de la catégorie |
| actif | bool | |

## Schémas de catégorie

Classe `CategorieMateriau` (interface) : `code()`, `libelle()`, `champs(): list<ChampCaracteristique>`.
Chaque `ChampCaracteristique` : clé, type (`int`, `float`, `string`, `enum`, `bool`), obligatoire, unité,
choix. Les catégories sont enregistrées via un tag de service (`app.categorie_materiau`) pour
qu'ajouter une catégorie = ajouter une classe.

Services :
- `CaracteristiquesValidator` : valide le JSON ; contrainte Symfony `#[CaracteristiquesValides]` sur `Materiau`.
- `MateriauFormBuilder` : génère le sous-formulaire des caractéristiques à partir du schéma.
- Accès typé pour les calculateurs : `IsolantView`, `MembraneView`, `ProfilView`… (façades en lecture sur
  `Materiau` qui lèvent une exception claire si un champ obligatoire manque).

## Reprise de l'existant

Commande `app:catalogue:importer-defauts` (ou migration de données) qui crée les articles correspondant aux
objets codés en dur dans le contrôleur actuel (montant 3 m, rail 3 m, plaque 2,5×1,2, joints, appuis).
Le contrôleur lit ensuite le catalogue au lieu de construire les objets à la main.

## Écrans

CRUD Materiau : liste filtrable par catégorie, formulaire dynamique, duplication d'un article. Import CSV
optionnel (une ligne = un article, caractéristiques en colonnes préfixées).

## Critères d'acceptation

- Ajouter une catégorie nouvelle ne demande aucune migration.
- Un article avec une caractéristique obligatoire manquante est refusé.
- Les calculateurs ne reçoivent plus d'objets construits en dur.
- Tests : validation de schéma, import des défauts, CRUD fonctionnel.
