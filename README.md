# Calcul matériaux de chantier

Tout passe par le [Symfony CLI](https://symfony.com/download) (serveur web, Composer, console et PHP) ;
Docker fournit les services (MariaDB, Mailpit).

## Démarrage

```bash
symfony composer install
docker compose up -d                          # MariaDB (bases `app` et `app_test`), Mailpit
symfony console doctrine:migrations:migrate   # schéma de la base
symfony server:start -d                       # serveur web (HTTPS : `symfony server:ca:install` une fois)
```

Pour un serveur en HTTP simple : `symfony server:start -d --no-tls`.

**Première page : <https://127.0.0.1:8000/projets>** (liste des projets ; il n'y a pas de page `/`).
Sans TLS : <http://127.0.0.1:8000/projets>. Le port peut différer : `symfony server:status` l'indique.

Parcours : créer un projet → créer une pièce → éditer la pièce (`/rooms/{id}`) : murs, ouvertures, cloisons.

### Catalogue (optionnel)

Le catalogue de matériaux et les fournisseurs se gèrent dans l'interface :

- <https://127.0.0.1:8000/catalogue/materiaux>
- <https://127.0.0.1:8000/catalogue/fournisseurs>
- <https://127.0.0.1:8000/catalogue/import> (import CSV)

L'ancien écran de calcul d'un mur reste disponible : <https://127.0.0.1:8000/doublage/mur>.

## Qualité

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse   # niveau 8
symfony console doctrine:schema:validate
```

Les tests utilisent la base `app_test` (jamais la base de développement).
La CI (GitHub Actions, `.github/workflows/ci.yml`) lance PHPStan et PHPUnit.

## Documentation

Voir [doc/README.md](doc/README.md) : connaissances métier et étapes de développement.
