# Conventions et qualité

## Outillage obligatoire

| Besoin | Outil |
|---|---|
| Serveur web | `symfony server:start -d` |
| PHP, Composer, console | `symfony php …`, `symfony composer …`, `symfony console …` (jamais `php`/`composer` direct) |
| Services (MariaDB, Mailpit) | `docker compose up -d` |
| Tests | PHPUnit : `symfony php vendor/bin/phpunit` |
| Analyse statique | PHPStan niveau 8 : `symfony php vendor/bin/phpstan analyse` |
| CI | GitHub Actions `.github/workflows/ci.yml` (PHPStan + PHPUnit) |

## Règles de code

- `declare(strict_types=1)`, classes `final` par défaut, objets valeur `readonly`.
- Aucun `@phpstan-ignore` ni baseline : corriger les types (`@phpstan-type`, génériques `AbstractType<T>`).
- Respecter le style du code voisin (nommage français du domaine, commentaires peu nombreux).
- Domaine (`src/Geometry`, `src/Calcul`) sans dépendance Symfony/Doctrine.
- Migrations Doctrine pour tout changement de schéma, jamais de modification directe.

## Tests

- **Unitaires** : géométrie, thermique, calculateurs, optimisation de coupes.
- **Caractérisation** : figer les résultats actuels de cloison/doublage avant tout refactor (phase 0).
- **Intégration** : persistance Doctrine, import DXF avec fichiers dans `tests/fixtures/dxf/`.
- **Fonctionnels** : parcours principaux (WebTestCase).
- Valeurs attendues vérifiées à la main (calcul indépendant), pas copiées de la sortie du code.

## Données de test

Fixtures (DataFixtures ou fichiers) pour projet exemple : pièce rectangulaire, forme en L, pièce
sous rampant, avec ouvertures et éléments électriques.

## Sécurité

Téléversement DXF : limite de taille, extension et contenu vérifiés, stockage hors `public/`, nom
généré. Aucune interprétation de contenu actif. Dépendances : `symfony composer audit` régulièrement
(des avis de sécurité sont déjà remontés).

## Git

Une phase = une série de petits commits ; une branche par phase ; la CI doit être verte avant fusion.
