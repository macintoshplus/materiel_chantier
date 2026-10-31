# Conventions et qualité

## Outillage obligatoire

| Besoin | Outil |
|---|---|
| Serveur web | `symfony server:start -d` |
| PHP, Composer, console | `symfony php …`, `symfony composer …`, `symfony console …` (jamais `php`/`composer` direct) |
| Services (MariaDB, Mailpit) | `docker compose up -d` |
| Tests | PHPUnit : `symfony php vendor/bin/phpunit` |
| Analyse statique | PHPStan niveau 8 : `symfony php vendor/bin/phpstan analyse` |
| CI | GitHub Actions `.github/workflows/ci.yml` (PHPStan + PHPUnit + contrôle des traductions) |

## Règles de code

- `declare(strict_types=1)`, classes `final` par défaut, objets valeur `readonly`.
- Aucun `@phpstan-ignore` ni baseline : corriger les types (`@phpstan-type`, génériques `AbstractType<T>`).
- Respecter le style du code voisin (nommage français du domaine existant, commentaires peu nombreux).
- Domaine (`src/Geometry`, `src/Calcul`) sans dépendance Symfony/Doctrine.
- Migrations Doctrine pour tout changement de schéma, jamais de modification directe.

## Tests

**Principe : les tests couvrent uniquement le code du projet**, en priorité les éléments importants. On ne
teste pas le framework ni les bibliothèques (routage Symfony, rendu de formulaire, validation de contraintes
natives, hydratation Doctrine basique, getters/setters triviaux).

### À tester en priorité (obligatoire)
- **Calculateurs** : ossature (cloison, doublage, plafond), isolant, membrane, parement (calepinage, joints,
  vis), retour de parement en tunnel, thermique (R, U), optimisation de coupes, renforts et joints décalés, marge de sécurité et arrondi aux lots,
  montants de raccordement, cloison à deux faces (ossature une seule fois, parement par face).
- **Stratégies et résolveurs** : `StrategieFournisseur` (unique, meilleur prix, plus proche, manuelle),
  chaîne de substitution (`ResolveurOffre`), `ResolveurMateriau`, `ConfigurationOssatureResolveur`,
  `StrategieConfiguration`, priorité des paramètres de pose, `PrixService` (historique).
- **Géométrie** : polygones, plans, jonctions, coupes, placement des ouvertures, édition d'une pièce
  (conservation des murs et des ouvertures).
- **Imports** : lecteur et interpréteur DXF, CSV, saisie par côtés et angles (avec fixtures).

### À tester si le code a une logique propre (utile, non prioritaire)
Services de chiffrage, génération du gabarit de PDF (contenu intermédiaire), schémas de catégorie du
catalogue (validation de caractéristiques), `DistanceCalculator`.

### À ne pas tester
Contrôleurs sans logique, formulaires Symfony, templates Twig, mapping Doctrine trivial. Un seul test de
fumée de page est toléré tant que la page existe ; aucun test fonctionnel de parcours n'est exigé.

### Méthode
- **Caractérisation** avant refactor : figer les résultats actuels cloison/doublage (phase 0).
- Valeurs attendues vérifiées à la main par un calcul indépendant, pas copiées de la sortie du code.
- Tests unitaires purs (sans base) pour le domaine ; tests d'intégration seulement là où le code projet
  interroge la base (historique de prix, recherche de candidats du catalogue) avec la MariaDB de la CI.
- La couverture est mesurée sur `src/` (config PHPUnit `<source>`) ; elle sert de repère, pas d'objectif
  chiffré : une ligne triviale non couverte n'est pas un problème, un calculateur sans test en est un.

## Textes et traductions

- Tous les textes visibles sont écrits **en anglais** dans les gabarits, formulaires et messages ; la
  traduction française est dans `translations/messages.fr.xlf` (ou `+intl-icu`).
- Clés = texte anglais complet (pas d'identifiants opaques) ; pluriels et variables en ICU.
- Pas de texte en dur dans le PHP : labels de formulaires, messages de validation, avertissements de
  calcul passent par `TranslatableMessage` ou le traducteur.
- CI : `symfony console translation:extract fr --force --format=xlf` puis `git diff --exit-code translations/`
  doit être vide, et `symfony console lint:xliff translations` doit passer. Le contrôle est à ajuster si
  l'outillage de traduction change.
- Le code et les noms de classe du domaine restent dans le vocabulaire déjà utilisé (français).

## Données de test

Fixtures (DataFixtures ou fichiers) pour un projet exemple : pièce rectangulaire, forme en L, pièce
sous rampant, avec ouvertures (applique et tunnel) et équipements.

## Sécurité

Téléversement DXF : limite de taille, extension et contenu vérifiés, stockage hors `public/`, nom
généré. Aucune interprétation de contenu actif. Dépendances : `symfony composer audit` régulièrement
(des avis de sécurité sont déjà remontés).

## Git

Une phase = une série de petits commits ; une branche par phase ; la CI doit être verte avant fusion.
