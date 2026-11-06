# Étape 05e — Agrégation, marges, optimisation de coupes et substitution

> Statut : terminée (vérifiée le 2026-11-06 : phpunit, phpstan niveau 8, schéma en phase). Le dessin de l'aperçu de calcul reste à voir en navigateur. Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Agréger les besoins de toutes les surfaces, appliquer les marges, optimiser les coupes sur les articles réels, gérer la substitution par équivalent et brancher l'aperçu/avertissements sur l'enregistrement.

## Périmètre

Marge de sécurité, `BesoinsProjet`, `MateriauxCalculator` (coupes 1D, lots), substitution par équivalent, action « Calculate » et recalcul au Save. Le chiffrage est l'étape 06.

## Prérequis

Étapes 05a à 05d terminées (au minimum 05a et 05b).

## Livrables

`MateriauxCalculator` sur besoins bruts, résolveur de marge, substitution, écran de calcul du projet.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

### Marge de sécurité et arrondi aux lots

Chaque matériau a une **marge de sécurité en pourcentage** (casse, erreur de découpe, imprévus), distincte
des chutes déjà calculées par l'optimisation de coupes. Elle s'applique **une fois**, sur la quantité
agrégée du matériau (après optimisation et agrégation de toutes les surfaces/faces du chiffrage) :

1. `quantiteBesoin` : quantité nécessaire dans l'**unité d'achat** (barres pour les profilés après
   optimisation, plaques, m² d'isolant, rouleaux, pièces, boîtes de vis…) ;
2. `quantiteAvecMarge = quantiteBesoin × (1 + marge/100)` ;
3. `nombreLots = ceil(quantiteAvecMarge / unitesParLot)` : **arrondi au supérieur selon la quantité par
   lot** ; un seul arrondi, pas un par étape ;
4. `quantiteAchetee = nombreLots × unitesParLot` ; chute totale = `quantiteAchetee − quantiteBesoin`.

La marge est lue par matériau selon la priorité de `01-catalogue.DONE.md` (chiffrage > projet > matériau >
catégorie > défaut global). Elle est copiée dans la ligne de chiffrage pour traçabilité
(`margePct`, `quantiteAvecMarge`). Les profilés optimisés en barres : la marge s'applique au nombre de barres
(`barres × (1 + marge)`), pas aux longueurs unitaires.


### Chaîne de calcul

```
Projet
 └─ pour chaque Piece
     └─ pour chaque Surface (variante active)
          ├─ ThermiqueCalculator  : R par couche, RT, U (paroi existante comprise)
          ├─ PipelineCouches      : résout les matériaux (spécifications), puis parcourt les couches
          │    └─ CalculateurCouche : attache, isolant (surface nette, chute), ossature (rails, montants),
          │                           membrane (rouleaux, recouvrements), parement (calepinage, joints, vis)
          └─ ResultatSurface
     └─ ResultatPiece
 └─ BesoinsProjet : agrégation par matériau
        └─ MateriauxCalculator : optimisation de coupes 1D, chute, taux de perte,
              marge de sécurité par matériau, arrondi au lot supérieur
              └─ (les prix sont appliqués au chiffrage, voir 08-…)
```

Les calculs géométriques d'ossature utilisent les coupes `Surface::coupes()` dans le repère de la
surface (voir `03-geometrie.DONE.md`), donc valables pour les plafonds à pans. Les besoins de plusieurs
surfaces du même matériau sont **agrégés avant optimisation**, pour que les chutes d'un mur servent à
un autre (option : calcul par surface ou par projet).

## Critères d'acceptation

- [x] Remplacement par un équivalent chez le fournisseur choisi, **seulement en l'absence de correspondance exacte**, puis ligne « prix manquant » en dernier recours.
- [x] Un équivalent de longueur différente (rail 2,6 m au lieu de 3 m) produit une optimisation de coupes recalculée avec sa longueur, donc un nombre de barres et des chutes différents.
- [x] Marge 10 % sur 11 plaques avec 1 plaque par lot : `ceil(12,1) = 13` ; avec 5 plaques par lot : 3 lots (15 plaques achetées).
- [x] La marge d'un matériau suit la priorité chiffrage > projet > matériau > catégorie > défaut global.
- [x] Un projet de 2 pièces produit des besoins agrégés avec lots entiers et chutes.
- [x] Après « Save », l'aperçu montre ossature et plaques et les avertissements de calcul apparaissent, sans cliquer sur « Calculate » ; « Calculate » agrège le projet (coupes, marge, lots).

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

Tests : `tests/Calcul/Agregation/AgregateurMateriauxTest.php` (marge 11 plaques, 1 et 5 par lot, priorité des marges, coupes réunies, rail 2,6 m contre 3 m, offre manquante, lot du prix), `tests/Calcul/Substitution/ResolveurOffreTest.php` (exact, puis équivalent, puis autre fournisseur, puis prix manquant), `tests/Integration/CalculProjetTest.php` (2 pièces en base, portées, fournisseur avec prix), `tests/Controller/CalculProjetControllerTest.php` (Save puis aperçu et avertissements ; page « Calculate »).

**Moteur modifié (nécessité)**
- `Materiau::valeur()` expose `reference`, `libelle` et `marge_securite_pct` (lecture par `MaterielLisible`, nécessaire à la priorité des marges et à la lecture du projet). Rien d'autre dans le moteur (pipeline, calculateurs, `OptimiseurBesoins`, `OptimiseurCoupes`) n'est modifié ; les codes `offer_equivalent_used`, `offer_other_supplier_used`, `offer_missing`, `article_dimension_missing` sont ajoutés à `MessagesCalcul` et traduits (`validators.fr.xlf`, mc38 à mc41).

**Écarts et choix**
- Le `MateriauxCalculator` hérité (`App\Service`, sur `Model\Mur`, utilisé par `/doublage/mur` et les tests de caractérisation) est laissé intact ; le nouvel agrégateur s'appelle `App\Calcul\Agregation\AgregateurMateriaux` (domaine pur) et réutilise `OptimiseurBesoins`.
- Marge en entiers (centièmes de pourcent), jamais en flottant : `lots = ceil(besoin x (1 + marge) / parLot)` en un seul arrondi entier ; `quantiteAvecMargeMilli` en millièmes d'unité. La marge s'applique au nombre d'unités après optimisation (barres, plaques).
- Marges de chiffrage : le type `ConfigurationMarges` les accepte mais le chiffrage (étape 06) n'existe pas encore, donc `CalculProjet` ne lit que `Projet.parametres` : `marges.materiaux.{REFERENCE}` et `marges.categories.{code}` (pourcentage, 2 décimales). Aucune interface de saisie de ces marges (à faire avec 06).
- Agrégation : clé = identité de l'article + type de besoin ; les pièces à découper sont réunies avant optimisation. Une ligne sans article (ex. besoin exprimé en longueur sans matériau) est cumulée sans lot ni marge (`ResultatAgregation::sansArticle`). Un article sans dimension utilisable est ignoré avec l'avertissement `article_dimension_missing`.
- Substitution : l'article est choisi avant l'optimisation (dimensions et lot de l'article retenu). Chaîne `OffreExacte`, `OffreEquivalente`, `OffreAutreFournisseur` puis « prix manquant » (ligne d'achat conservée avec l'article d'origine et `prixManquant`). Les offres viennent de l'historique des prix (`OffresCatalogue`, `PrixService::prixA`, lot du prix prioritaire). La stratégie « autres fournisseurs » existe dans le domaine (testée) mais `CalculProjet` n'expose qu'un fournisseur unique.
- Les équivalents ne sont cherchés que si la spécification de la couche d'origine est résolue (sans référence au contexte de calcul) et l'article est celui de la couche ; les profilés d'ossature produits par le moteur (sans spécification de couche) n'ont donc que la correspondance exacte, sinon « prix manquant ».
- « Calculate » : page `/projets/{id}/calculate` (portée projet, pièce ou surface, fournisseur optionnel), lien depuis la page du projet. Le calcul n'est jamais persisté.
- Après « Save » : la page d'une surface (redirection GET) recalcule le système actif à chaque affichage ; l'aperçu superpose les montants (entraxe retenu) et les plaques du dernier parement, et le groupe « Calculation » liste les avertissements et erreurs.

**Non fait / non vérifié**
- La page des cloisons (faces A et B) n'a ni superposition ni groupe « Calculation » : seules les surfaces (murs, pans, sol) les ont.
- Le dessin des montants et plaques est schématique (boîte englobante de la surface, non découpé par le contour ni les ouvertures) ; non vérifié visuellement dans un navigateur, seulement par les tests de présence des éléments SVG.
- Aucune comparaison de coûts ni prix : étape 06.
