# Chiffrage, fournisseurs et exports PDF

Dépend de [02-catalogue.md](02-catalogue.md) (fournisseurs, prix) et de
[04-systemes-et-calcul.md](04-systemes-et-calcul.md) (besoins).

## Chiffrage

Un **chiffrage** est un instantané immuable des besoins d'un projet, valorisés à une date donnée.

### `Chiffrage`
id, projet, libelle, dateCreation, strategieFournisseur, parametres JSON (marge de chute, fournisseur
imposé, adresse de référence), totalTtc.

### `LigneChiffrage`
chiffrage, piece (nullable : ligne agrégée), surface (nullable), materiau (identité figée : référence,
libellé, dimensions copiés), fournisseur, referenceFournisseur, quantiteBesoin, unitesParLot, nombreLots,
prixLotTtc, totalTtc, chute, tauxPerte, prixMateriauId (traçabilité).

Les lignes **copient** les libellés, dimensions et prix : renommer ou supprimer un article ensuite ne
modifie pas un chiffrage existant.

### Stratégie de fournisseur
Interface `StrategieFournisseur::choisir(Materiau, DateTimeImmutable, Contexte): ?PrixMateriau` :

| Stratégie | Règle |
|---|---|
| `FournisseurUnique` | Tous les articles chez le même fournisseur ; les articles absents sont signalés |
| `MeilleurPrix` | Prix de lot le moins cher par article (peut mélanger les fournisseurs) |
| `PlusProche` | Fournisseur le plus proche de l'adresse du chantier qui vend l'article, repli sur le suivant |
| `Manuelle` | Choix par ligne dans l'écran |

Articles sans prix chez le fournisseur choisi : ligne marquée « prix manquant », jamais un 0 silencieux.

### Service
`ChiffrageService::creer(Projet, Strategie, DateTimeImmutable, parametres): Chiffrage` calcule les
besoins (variantes actives), les optimise, applique la stratégie, enregistre. Les besoins étant
agrégés par matériau, les lignes de pièce/surface sont obtenues par répartition proportionnelle de la
quantité (pour les exports par pièce) ; le total par article est celui de l'agrégat.

Comparaison de deux chiffrages (écart par ligne et total) : utile avant/après changement de prix ou de
variante.

## Exports PDF

Liste de courses en **PDF**, avec ou sans prix :

| Export | Contenu |
|---|---|
| Par pièce | Articles nécessaires à une pièce, regroupés par catégorie |
| Par fournisseur | Articles à acheter chez un fournisseur (tous projets/pièces confondus, ou une pièce), avec adresse du fournisseur |
| Projet | Récapitulatif global (option) |

Options communes : avec ou sans prix, regroupement (catégorie / pièce), date et libellé du chiffrage en
entête, mention « Prix TTC à la date du … ».

Mise en œuvre : gabarit Twig → HTML → PDF. Bibliothèque à évaluer (Dompdf en PHP pur pour éviter un
binaire externe ; Snappy/wkhtmltopdf ou Chromium en alternative si le rendu l'exige). Le rendu passe par
un service `ListeCoursesPdf::generer(Chiffrage, Portee, bool $avecPrix): string`. Le même gabarit sert
l'aperçu HTML.

## Critères d'acceptation

- Un chiffrage créé aujourd'hui ne change pas si un prix est modifié demain.
- Stratégie `MeilleurPrix` : si A vend 12 € et B 10 €, la ligne est chez B.
- Stratégie `PlusProche` : classement correct à partir de coordonnées de test (distance connue).
- Article absent du fournisseur choisi : ligne « prix manquant » visible, total partiel signalé.
- PDF par pièce avec et sans prix : mêmes articles, colonnes de prix présentes ou non.
- PDF par fournisseur : un document par fournisseur (ou une section par fournisseur) avec son adresse.
- Tests : stratégies (unitaire), service de chiffrage (intégration), génération PDF (fichier non vide,
  texte attendu extrait ou rendu HTML intermédiaire vérifié).
