# Plan général

Application Symfony 7.4 existante : un seul écran de calcul de mur, sans persistance. Objectif : un
calculateur multi-projets, persistant, géométrique, extensible, avec chiffrage par fournisseur.

## Principes

- **Incrémental** : chaque phase livre quelque chose d'utilisable et garde le calcul de mur actuel
  fonctionnel (tests de caractérisation avant refactor).
- **Domaine pur et testable** : géométrie et calculs sont des classes PHP sans dépendance Doctrine/Symfony ;
  Doctrine ne sert qu'à la persistance.
- **Extensible par données** : catalogue (JSON validé par schéma), systèmes et types d'équipements
  ajoutés sans nouvelle migration quand c'est possible.
- Outillage : voir [_conventions-qualite.md](_conventions-qualite.md).

## Phases

| # | Phase | Livrable | Dépend de |
|---|---|---|---|
| 0 | Filet de sécurité | Tests de caractérisation du calcul actuel (cloison, doublage, matériaux) ; passage en mm | — |
| 1 | Catalogue et fournisseurs | `Materiau` + JSON + schémas par catégorie, `Fournisseur` (nom, adresse, GPS), prix TTC par fournisseur avec historique, écrans d'administration, import des articles actuels | 0 |
| 2 | Modèle de domaine | Projet / Pièce / Surface / Ouverture persistés, plusieurs projets, CRUD | 0 |
| 3 | Géométrie | Polygone au sol, murs générés, **cloisons (tracé, faces, ancrages)**, jonctions, murs non rectangulaires, **plafonds composés de pans**, repère propre à chaque surface, ouvertures positionnées ; aperçu 2D d'un mur, aperçu 2D vu du dessus d'une pièce | 2 |
| 4 | Sources de mesures | Saisie par côtés et angles (la création manuelle « rectangle rapide » et le tableau de sommets sont déjà en phase 3), import DXF, import CSV de relevé laser → pièce créée | 3 |
| 5 (a–e) | Systèmes et calcul | Paroi existante, systèmes de revêtement par surface (doublage, cloison, plafonds, lambris, isolation seule), couches, thermique R/U, variantes, quantités par surface et par pièce | 1, 3 |
| 6 | Chiffrage et exports | Chiffrage figé (instantané daté ; un nouveau chiffrage est une nouvelle ligne, sans chaîne de versions), choix du fournisseur, liste de courses PDF par pièce et par fournisseur, avec ou sans prix | 1, 5 |
| 7 | Réseaux : électricité | Équipements placés, circuits, longueurs de câble/gaine, quantités annexes | 3, 5 |
| 8 | Plomberie et ventilation | Mêmes principes que l'électricité (équipements, réseaux, cheminement), alimentés par le catalogue | 7 |
| 9 | Vue 3D de la pièce | Visualisation 3D (murs, plafond à pans, ouvertures, équipements) | 3 |

Les phases 1 et 2 sont indépendantes et peuvent avancer en parallèle. L'interface suit chaque phase
(voir [_interface-principes.md](_interface-principes.md)).

## Décisions prises

| Sujet | Décision |
|---|---|
| Utilisateurs | Mono-utilisateur au départ. Le propriétaire sera ajouté plus tard sur `Projet` ; le catalogue, les fournisseurs et les prix étant globaux, il faudra alors décider s'ils sont partagés ou par compte (voir `01`). Pas de travail préparatoire |
| Catalogue | Dynamique, global. Un article peut avoir plusieurs prix selon le fournisseur |
| Fournisseur | Nom, adresse, localisation GPS |
| Prix | TTC dans un premier temps, avec historique |
| Murs courbes | Non pris en charge pour le moment (DXF : un contour avec arc/cercle/bulge est rejeté ; un arc hors contour est ignoré avec avertissement ; pas d'approximation) |
| Exports | Liste de courses PDF par pièce et par fournisseur, avec ou sans prix |
| Aperçu | Mur : 2D. Pièce : 2D vue de dessus d'abord, 3D en phase 9 |
| Interface | Aperçu au centre, propriétés en groupes repliables à droite ; un mur = une page (sélection sur le plan ou dans la liste de droite) ; création d'une pièce par import DXF ou saisie manuelle, modifiable à tout moment ; textes en anglais traduits en français |
| Pièce | Volume entouré de murs physiques ; les cloisons, doublages et équipements sont des ouvrages dans la pièce |
| Cloison | Objet distinct d'un mur : tracé modifiable, deux faces avec parement par face, ossature et isolant communs, ouvertures comme un mur, ancrages (mur, cloison, doublage) avec montant de raccordement supplémentaire |
| Doublage | Profilé périphérique haut et bas : rails (système cloison) ou lisses (système doublage) |
| Marges | Marge de sécurité en % par matériau (priorité chiffrage > projet > matériau > catégorie > global), quantités arrondies au lot supérieur |
| Tests | Uniquement le code du projet, en priorité calculateurs, stratégies, résolveurs, géométrie, imports ; pas de test du framework |
| Plafonds | Gérés dès la phase 3 (pans), l'ossature d'un pan se calcule dans le repère du pan, pas en bandes verticales |
| Surfaces | Mur, pan incliné et plafond horizontal sont la même chose : une forme plane dont les sommets sont placés par distance ou par angle ; même système de revêtement, seuls les matériaux changent ; pas de description de toit paramétrique |
| Sens de `u` | Vue du côté où l'on regarde la surface ; mur de pièce : de `fin` vers `début` de l'arête ; cloison : face A inversée par rapport au tracé, face B dans le sens du tracé |
| Épaisseur de cloison | Dérivée du système actif (planification sans polygone), profilé choisi sur la hauteur maximale ; changer de variante régénère les faces |
| Brouillons | Pas d'entité brouillon : la pièce est créée tout de suite et modifiable à tout moment ; l'état de l'assistant DXF reste en session/fichier temporaire |
| Enregistrement | Toujours explicite (bouton Save, Save and continue / Discard / Cancel) ; jamais d'enregistrement automatique |
| Plafonds | Plafond suspendu (horizontal ou sous rampant) géré dès la v1 : lisses périphériques, fourrures, suspentes ; autoportant et doublage collé plus tard |
| Cotes saisies | Mesurées au **nu brut** (avant doublage) ; case optionnelle pour déduire l'épaisseur d'un doublage |
| Aperçu et calcul | L'enregistrement relance le pipeline de la surface (aperçu complet, avertissements) ; « Calculate » agrège le projet |

## Hors périmètre initial

Comptes utilisateurs, murs courbes, DXF 3D, plafonds autoportants et doublage collé (la table de portées et les renforts horizontaux sont prévus ; le système reste « à écrire »),
chiffrage de main d'œuvre, TVA détaillée / prix HT (le modèle prévoit un champ pour plus tard),
logistique (poids, voyages), import SVG/IFC.

## Questions encore ouvertes

Aucune. Livraison : un prix forfaitaire de livraison peut être saisi par fournisseur (ligne de chiffrage dédiée),
sans franco ni calcul au poids. Fournisseur le plus proche : mesuré depuis l'adresse du chantier saisie sur le
projet. Article indisponible : équivalent seulement sans correspondance exacte (voir `04` et `08`).

## Règle d'une étape

Les fichiers `NN-*.md` sont des **étapes de développement** ; les fichiers préfixés `_` sont des
**références permanentes** (plan, modèle, conventions, principes d'interface) qui ne se « terminent » pas.

Une étape contient : Objectif, Périmètre, Prérequis, Livrables, Spécification, **Critères d'acceptation** (cases à
cocher, chacun vérifiable), Vérifications automatiques, Décisions et écarts.

- On ne commence une étape que si ses prérequis sont `.DONE.md`.
- Une étape est terminée quand **tous** ses critères sont cochés et que les vérifications sont vertes :
  `symfony php vendor/bin/phpunit`, `symfony php vendor/bin/phpstan analyse` (niveau 8),
  `symfony console doctrine:schema:validate`, CI verte.
- Le fichier est alors renommé en `NN-*.DONE.md`, **dans le même commit que le code** (et les liens mis à jour).
- Un critère non satisfait ne se coche pas : il est soit implémenté, soit déplacé explicitement vers une autre
  étape et noté dans « Décisions et écarts ».
- Un changement de comportement métier met à jour la spécification de l'étape concernée.

## Étapes

| Fichier | Étape |
|---|---|
| [00](00-filet-securite.DONE.md) | Filet de sécurité |
| [01](01-catalogue.DONE.md) | Catalogue, fournisseurs, prix |
| [02](02-modele-persistance.DONE.md) | Modèle de domaine et persistance |
| [03](03-geometrie.DONE.md) | Géométrie, pièces, cloisons, interface d'édition |
| [04](04-sources-mesures.DONE.md) | Sources de mesures (DXF, CSV, côtés/angles) |
| [05a](05a-moteur-couches.DONE.md) → [05e](05e-agregation-substitution.DONE.md) | Systèmes et calcul : moteur, cloisons/doublages, plafond/pose/renforts, finitions/thermique, agrégation/substitution |
| [06](06-chiffrage-exports.DONE.md) | Chiffrage et exports |
|  | *Note : l'écran d'application d'un système (choix de la définition, paramètres, couches, variantes) est décrit dans le README racine ; il complète les étapes 05 et 06.* |
| [07](07-electricite.md) | Électricité |
| [08](08-plomberie-ventilation.md) | Plomberie et ventilation |
| [09](09-vue-3d.md) | Vue 3D |
