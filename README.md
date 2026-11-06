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

### Appliquer un système à une surface ou à une cloison

Sans système appliqué, les pages de comparaison des variantes (`/projets/{id}/variants`), de calcul
(`/projets/{id}/calculate`) et de chiffrage (`/projets/{id}/chiffrages`) restent vides. Parcours :

1. Ouvrir une surface (`/rooms/{pièce}/walls/{surface}` : mur, pan de plafond) ou une cloison
   (`/rooms/{pièce}/partitions/{cloison}`) et suivre **Gérer les systèmes** (groupe « Systèmes » de la colonne de droite).
2. La liste (`.../systems`) montre les systèmes déjà appliqués, avec leur statut (active ou variante), et propose les
   définitions compatibles du support : doublage sur ossature, cloison sur ossature métallique, plafond suspendu.
3. Choisir une définition ouvre le formulaire : nom de la variante, paramètres de la définition (vides : valeur par
   défaut du moteur), puis les couches par emplacement. Pour chaque couche : article (la liste est filtrée par la
   spécification de l'emplacement ; « Non imposé » laisse le moteur choisir), orientation, position, décalage et face
   (cloison) quand ils sont permis. « Ajouter une couche » et « Retirer » respectent le minimum et le maximum de
   l'emplacement. Rien n'est enregistré avant **Enregistrer** ; une saisie invalide réaffiche le formulaire (HTTP 422).
4. Après l'enregistrement, la page de modification affiche le calcul du système (R, épaisseur) et ses avertissements
   (spécification non satisfaite, aucun candidat, etc.). Le premier système d'un support est activé d'office.
5. Créer d'autres variantes en répétant l'étape 3 ; **Activer** (liste ou comparaison) choisit celle qui alimente le
   calcul et le chiffrage ; **Comparer les variantes** montre R, U, épaisseur et coût. **Supprimer** retire un système.

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
