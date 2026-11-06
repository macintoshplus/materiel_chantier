# Documentation

Deux familles de fichiers :

| Dossier | Contenu | Usage |
|---|---|---|
| [`connaissances/`](connaissances/) | Savoir métier et technique (bâtiment, géométrie, thermique, électricité) | Référence pour comprendre et valider les calculs. Ne dit pas comment coder. |
| [`developpement/`](developpement/) | Plan, modèle de données, tâches à réaliser, critères d'acceptation | À suivre (ou à donner à un agent) pour réaliser le développement. |

## Connaissances

1. [Domaine et vocabulaire](connaissances/01-domaine.md)
2. [Géométrie](connaissances/02-geometrie.md)
3. [Isolation thermique](connaissances/03-isolation-thermique.md)
4. [Plâtrerie : cloisons, doublages, plafonds](connaissances/04-platrerie.md)
5. [Électricité](connaissances/05-electricite.md)
6. [Catalogue de matériaux](connaissances/06-catalogue-materiaux.md)
7. [Import DXF](connaissances/07-import-dxf.md)
8. [Plomberie et ventilation](connaissances/08-plomberie-ventilation.md)
9. [Relevés de mesures](connaissances/09-releves-mesures.md)

## Développement

Références permanentes (préfixe `_`, jamais « terminées ») :

- [Plan général, phases, règle d'une étape](developpement/_plan-general.md)
- [Modèle de domaine](developpement/_modele-domaine.md)
- [Conventions et qualité](developpement/_conventions-qualite.md)
- [Principes d'interface](developpement/_interface-principes.md)

Étapes (un fichier = une étape avec critères d'acceptation ; renommé `.DONE.md` quand elle est terminée) :

0. [Filet de sécurité](developpement/00-filet-securite.DONE.md)
1. [Catalogue, fournisseurs et prix](developpement/01-catalogue.DONE.md)
2. [Modèle de domaine et persistance](developpement/02-modele-persistance.DONE.md)
3. [Géométrie, pièces, cloisons, interface](developpement/03-geometrie.DONE.md)
4. [Sources de mesures](developpement/04-sources-mesures.DONE.md)
5. Systèmes et calcul : [a. moteur de couches](developpement/05a-moteur-couches.md), [b. cloisons et doublages](developpement/05b-cloisons-doublages.md), [c. plafond, pose, renforts](developpement/05c-plafond-pose-renforts.md), [d. finitions et thermique](developpement/05d-finitions-thermique.md), [e. agrégation et substitution](developpement/05e-agregation-substitution.md)
6. [Chiffrage et exports](developpement/06-chiffrage-exports.md)
7. [Électricité](developpement/07-electricite.md)
8. [Plomberie et ventilation](developpement/08-plomberie-ventilation.md)
9. [Vue 3D](developpement/09-vue-3d.md)

> Les valeurs normatives (entraxes, hauteurs, sections, résistances superficielles) sont données à titre
> d'ordre de grandeur et doivent être validées avec les DTU / NF C 15-100 en vigueur et les fiches
> techniques des fabricants avant d'être figées dans le catalogue ou le code.
