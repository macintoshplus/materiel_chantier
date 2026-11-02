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
- Outillage : voir [07-conventions-qualite.md](07-conventions-qualite.md).

## Phases

| # | Phase | Livrable | Dépend de |
|---|---|---|---|
| 0 | Filet de sécurité | Tests de caractérisation du calcul actuel (cloison, doublage, matériaux) ; passage en mm | — |
| 1 | Catalogue et fournisseurs | `Materiau` + JSON + schémas par catégorie, `Fournisseur` (nom, adresse, GPS), prix TTC par fournisseur avec historique, écrans d'administration, import des articles actuels | 0 |
| 2 | Modèle de domaine | Projet / Pièce / Surface / Ouverture persistés, plusieurs projets, CRUD | 0 |
| 3 | Géométrie | Polygone au sol, murs générés, **cloisons (tracé, faces, ancrages)**, jonctions, murs non rectangulaires, **plafonds composés de pans**, repère propre à chaque surface, ouvertures positionnées ; aperçu 2D d'un mur, aperçu 2D vu du dessus d'une pièce | 2 |
| 4 | Sources de mesures | Saisie par côtés et angles, import DXF, import CSV de relevé laser → pièce créée | 3 |
| 5 | Systèmes et calcul | Paroi existante, systèmes de revêtement par surface (doublage, cloison, plafonds, lambris, isolation seule), couches, thermique R/U, variantes, quantités par surface et par pièce | 1, 3 |
| 6 | Chiffrage et exports | Chiffrage figé et versionné, choix du fournisseur, liste de courses PDF par pièce et par fournisseur, avec ou sans prix | 1, 5 |
| 7 | Réseaux : électricité | Équipements placés, circuits, longueurs de câble/gaine, quantités annexes | 3, 5 |
| 8 | Plomberie et ventilation | Mêmes principes que l'électricité (équipements, réseaux, cheminement), alimentés par le catalogue | 7 |
| 9 | Vue 3D de la pièce | Visualisation 3D (murs, plafond à pans, ouvertures, équipements) | 3 |

Les phases 1 et 2 sont indépendantes et peuvent avancer en parallèle. L'interface suit chaque phase
(voir [06-interface.md](06-interface.md)).

## Décisions prises

| Sujet | Décision |
|---|---|
| Utilisateurs | Mono-utilisateur au départ. Le propriétaire sera ajouté plus tard, uniquement sur `Projet` (l'utilisateur ne se rattache qu'au projet) : pas de travail préparatoire |
| Catalogue | Dynamique, global. Un article peut avoir plusieurs prix selon le fournisseur |
| Fournisseur | Nom, adresse, localisation GPS |
| Prix | TTC dans un premier temps, avec historique |
| Murs courbes | Non pris en charge pour le moment (DXF : un arc/cercle ouvre un avertissement, pas d'approximation) |
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
| Aperçu et calcul | L'enregistrement relance le pipeline de la surface (aperçu complet, avertissements) ; « Calculate » agrège le projet |

## Hors périmètre initial

Comptes utilisateurs, murs courbes, DXF 3D, plafonds autoportants calculés en v1 (la table de portées et les renforts horizontaux sont prévus ; le système reste « à écrire »),
chiffrage de main d'œuvre, TVA détaillée / prix HT (le modèle prévoit un champ pour plus tard),
logistique (poids, voyages), import SVG/IFC.

## Questions encore ouvertes

1. Livraison : prix de transport ou franco par fournisseur à intégrer au chiffrage ?
2. Le « fournisseur le plus proche » se mesure-t-il depuis l'adresse du chantier (à saisir sur le projet) ?
3. Un article peut-il être indisponible chez un fournisseur (remplacement automatique par un équivalent) ?

## Définition de « terminé » pour toute tâche

`symfony php vendor/bin/phpunit` vert, `symfony php vendor/bin/phpstan analyse` (niveau 8) sans erreur,
CI verte, doc mise à jour si le comportement métier change.
