# Calcul matériaux de chantier

Tout passe par le [Symfony CLI](https://symfony.com/download) (serveur web, Composer, console et PHP) ;
Docker fournit les services (MariaDB, Mailpit).

## Installation

```bash
symfony composer install
docker compose up -d
symfony server:start -d
```

## Qualité

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse   # niveau 8
```

La CI (GitHub Actions, `.github/workflows/ci.yml`) lance PHPStan et PHPUnit.

## Doublage des murs

URL : https://127.0.0.1:8000/doublage/mur
