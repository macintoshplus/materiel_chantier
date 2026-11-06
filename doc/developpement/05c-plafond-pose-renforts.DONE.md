# Étape 05c — Plafond suspendu, paramètres de pose et renforts d'ossature

> Statut : terminée (vérifiée le 2026-11-06 : phpunit, phpstan niveau 8, schéma en phase). Règles : [_plan-general.md](_plan-general.md#règle-dune-étape).

## Objectif

Calculer un plafond suspendu (horizontal ou sous rampant) et appliquer la priorité des paramètres de pose, les portées limites et les renforts d'ossature.

## Périmètre

Système `plafond_suspendu_placo`, paramètres de mise en œuvre (vis, portée limite), renforts (configuration, quantités). Autoportant hors v1.

## Prérequis

Étape 05a terminée (05b recommandée pour les renforts de montants).

## Livrables

Définition `plafond_suspendu_placo`, calculateurs lisse/fourrure/suspente, résolveur de portée, `ConfigurationOssature`, tables de portées dans le catalogue.

## Spécification

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

### Plafond suspendu (pan horizontal ou sous rampant)

Géré dès la v1 (système `plafond_suspendu_placo`). Un plafond horizontal et un pan sous rampant ont la même
logique ; seules les longueurs réelles (pente) changent. Couches et calculs :

| Élément | Règle |
|---|---|
| Lisse périphérique | Profilé (`profil_ossature`, famille `lisse`) sur **toute la périphérie** du pan (comme les rails/lisses d'un doublage), longueur réelle des arêtes ; pas posé sur les arêtes ouvertes (faîtage, jonction avec un autre pan) ni sur les ouvertures de trémie, selon le paramètre `peripherie` de chaque arête |
| Fourrures | Profilé de famille `fourrure`, direction de l'ossature de la surface, `entraxe` (≈ 0,60 m, bornes de l'article et de la plaque), longueur réelle de chaque position (`Surface::coupes()`), aboutées avec éclisses si plus longues que la barre |
| Suspentes | Catégorie `suspente`, une par `pas` le long de chaque fourrure (`pas` et `charge_max` de l'article, défaut ≈ 1 suspente par 0,60–1,20 m² selon charge) ; la longueur de suspente dépend de la hauteur de chute (plénum) saisie, paramètre du système |
| Plaques, joints, visserie | Comme les autres systèmes (couche parement, vis par peau) |
| Isolant | Dans le plénum, en position `continue` (ou `entre_ossature` selon le système) |

La table de portées ne sert pas ici (pas de portée libre) ; en revanche le pas de suspente et l'entraxe de
fourrure respectent les limites des articles (même règle de priorité que ci-dessous). Le plafond
**autoportant** reste hors v1.

### Paramètres de mise en œuvre (entraxe, pas, portée, vis)

Entraxes, pas de fixation, portées limites et longueurs de vis changent les **quantités**. Ils sont
répartis sur trois niveaux (détail du stockage dans `01-catalogue.DONE.md`, groupe `pose`) :

| Niveau | Contenu | Exemple |
|---|---|---|
| Article (`Materiau.caracteristiques.pose`) | Données fabricant propres à l'article | Table des portées d'un profilé, pas par défaut d'un appui, plage d'épaisseur fixable d'une vis, `entraxe_max` imposé par une plaque |
| Système (`DefinitionSysteme`, `EmplacementCouche`) | Choix de pose, avec défaut et bornes | Entraxe (défaut 600 mm), pas de vis de la 1ʳᵉ peau et de la dernière, pénétration mini de la vis |
| `SystemeApplique` | Surcharge par l'utilisateur | Entraxe imposé à 400 mm |

**Priorité** (phase de planification du pipeline) :
1. **surcharge de l'utilisateur**, acceptée avec un avertissement si elle dépasse une limite d'un article ;
2. sinon `min(défaut du système, limites de tous les articles du système)` (l'article le plus contraignant
   gagne, mais une limite plus large que le défaut ne le remplace jamais) ;
3. sans défaut de système : le minimum des limites ; sans aucune donnée : erreur explicite.

Les valeurs retenues sont écrites dans `ContexteCouches` (`entraxe`, `pasVisParPeau`, `renfort`,
`penetrationVis`), puis copiées dans le résultat de la surface et le chiffrage, pour expliquer les
quantités (« 14 montants car entraxe 400 mm imposé par la plaque »).

#### Vis et visserie

- Catégorie `visserie` : `longueur` (information), `usage` (`plaque_sur_metal`, `plaque_sur_bois`…), `epaisseur_fixable_min`
  et `max` (**critère de sélection unique** : plage d'épaisseur de plaques que la vis traverse, pénétration dans l'ossature incluse selon la fiche).
- **Spécification à critère calculé** : `MaterielSpecification` accepte des valeurs tirées du contexte :
  `usage = plaque_sur_metal` et `epaisseur_fixable_min <= {contexte.epaisseurParementsPeau} <= epaisseur_fixable_max`.
  `ResolveurMateriau` retient la plus petite vis compatible (longueur la plus courte). Une vis par peau si les peaux n'ont pas la même
  épaisseur cumulée (la 1ʳᵉ peau passe sa vis dans une seule plaque, la 2ᵉ dans deux).
- **Quantité** : nombre de points de fixation = longueur d'ossature de la peau ÷ pas de la peau (large pour
  la 1ʳᵉ peau, serré pour la dernière), plus les fixations de périphérie ; si le fabricant ne donne qu'une
  consommation au m², elle sert de repli. Les vis s'achètent en boîtes (lots entiers).

#### Portée limite : un contrôle qui peut modifier la configuration

Elle ne donne pas une quantité directement : elle vérifie que la hauteur utile de la surface respecte la
limite du profilé pour la configuration retenue `(entraxe, renfort)`. En cas de dépassement, la phase de
planification **propose** des configurations (voir ci-dessous), l'utilisateur choisit ou laisse la
stratégie choisir.

### Renforts d'ossature (montants doublés)

Pour une ossature, le fabricant autorise de renforcer les montants afin d'augmenter la résistance ou de
dépasser la longueur maximale d'un profilé simple. Cette **longueur maximale dépend de l'orientation de
pose** : verticale (cloison, doublage : c'est une hauteur) ou horizontale (plafond autoportant : c'est une
portée entre appuis). Les valeurs sont distinctes dans la table du fabricant.

| Renfort | Principe | Effet sur les quantités |
|---|---|---|
| `simple` | Un montant par position | Référence |
| `double_accole` | Deux montants vissés ensemble à chaque position renforcée | Montants ×2 aux positions concernées, vis d'assemblage |
| `double_decale` | Le montant final est **doublé sur toute sa longueur** : deux lignes de profilés vissées ensemble, chacune faite de barres aboutées, dont les **joints sont décalés** d'une ligne à l'autre ; **éclisses de liaison** à chaque joint ; permet de dépasser la longueur maximale du montant simple | Longueur de profilé ×2, pièces de longueurs différentes (position des joints), éclisses par joint, vis d'assemblage au pas fabricant |

Ces informations viennent du fabricant : elles sont **des données de l'article** (le profilé), pas du
code. Chaque ligne de la table `pose.portees` du profilé porte `renfort`, **`orientation`**
(`verticale` | `horizontale`), `entraxe`, **`longueur_max`** (longueur maximale du montant final dans cette
orientation) et, pour les renforts, les `accessoires` (spécification de l'éclisse, règle de quantité),
`decalage_joints_min_mm` (écart minimal entre les joints des deux lignes), `pas_vis_assemblage_mm`.

L'**orientation de pose** d'une surface se déduit de la direction de l'ossature dans le repère de la
surface (`Surface::coupes()`) : verticale pour un mur, horizontale pour un plafond autoportant. Un cas
intermédiaire (pan incliné) suit la règle du fabricant ; en l'absence de donnée, avertissement et
orientation la plus défavorable.

#### Configuration d'ossature

Objet valeur `ConfigurationOssature` : profilé, entraxe, renfort, **zone** d'application (`toutes_positions`,
`bords_ouvertures`, positions listées). Le renfort peut être général ou localisé (par exemple seulement
les montants qui encadrent une porte).

`ConfigurationOssatureResolveur` (phase de planification) :
1. liste les configurations possibles : lignes de la table de portées du profilé de **la bonne
   orientation** dont `longueur_max ≥` longueur du montant final (hauteur utile ou portée entre appuis)
   et dont l'entraxe respecte la priorité ci-dessus ;
2. applique une **stratégie** de choix (`StrategieConfiguration`) : la plus économe en matériel,
   la plus économe en coût (avec le fournisseur choisi), ou imposée par l'utilisateur ;
3. **vérifie chaque position** contre la ligne de la table qui lui correspond (renforcée ou non) : un montant non renforcé dont la longueur dépasse la `longueur_max` du montant simple est une erreur bloquante, sauf si le renfort localisé couvre cette position ;
4. écrit la configuration retenue dans `ContexteCouches` ; si aucune ne convient, avertissement bloquant
   (« hauteur 3,2 m : aucun profil M48 ne convient, essayer M70 ou un renfort décalé »).

L'utilisateur peut aussi proposer une configuration qui n'est pas dans la table : acceptée avec
avertissement (hors données fabricant), jamais silencieusement.

#### Calcul des quantités avec renfort

Le calculateur de la couche « ossature » produit des **montants composites** (`MontantComposite`) : un
montant final de longueur `L` constitué d'une ou deux **lignes**, chaque ligne étant une suite de
**segments** (début, fin) découpés dans des barres standard.

- `simple` : une ligne.
- `double_accole` : deux lignes identiques, vis d'assemblage au pas fabricant.
- `double_decale` : deux lignes couvrant **toute** la longueur `L`. Si `L` dépasse la longueur d'une barre,
  chaque ligne est aboutée avec un ou plusieurs joints ; les joints de la ligne 2 sont décalés de ceux de la
  ligne 1 d'au moins `decalage_joints_min_mm` (et ne tombent pas au même endroit qu'un autre joint de la
  ligne). Chaque joint reçoit une **éclisse** (règle de l'accessoire : par joint, éventuellement plus
  longue en doublage). Si `L` tient dans une barre, il n'y a pas de joint : le montant se réduit à un
  doublage accolé, avec la longueur max du renfort décalé.

Les **segments de toutes les lignes** sont envoyés comme pièces à découper à `MateriauxCalculator`
(optimisation de coupes 1D) : le nombre de barres dépend donc de la position des joints. Les accessoires
(éclisses, vis d'assemblage) deviennent des `LigneBesoin` via leur spécification (catégorie
`accessoire_ossature`) : nombre d'éclisses = nombre de joints, vis d'assemblage = longueur du montant
final ÷ pas.

Positionnement des joints : `PlanificateurJoints::joints(L, longueurBarre, decalageMin): list<list<int>>`
(positions par ligne), choisi pour limiter les chutes (stratégie simple en v1 : première ligne coupée à la
longueur de barre, seconde ligne coupée à la moitié ; ajustée pour respecter le décalage minimal).

La **comparaison automatique** des configurations (entraxe plus serré, doublage accolé, doublage décalé)
réutilise les variantes : chaque configuration candidate est évaluable sans modifier le système actif,
avec son coût pour comparaison.

## Critères d'acceptation

- [x] Un plafond double pente avec fourrures est calculé avec les longueurs réelles de pente.
- [x] Priorité des paramètres de pose : surcharge utilisateur > `min(défaut du système, limites des articles)` ; un entraxe utilisateur hors limite est accepté avec avertissement ; une limite supérieure au défaut ne remplace pas le défaut.
- [x] Vis choisie par critère calculé : plus petite longueur ≥ épaisseur des peaux + pénétration mini ; quantité = longueur d'ossature de la peau ÷ pas de la peau (boîtes entières).
- [x] Mur plus haut que la portée du montant simple : le résolveur propose entraxe réduit, profilé plus fort, doublage accolé ou doublage décalé avec éclisses, selon la table du fabricant.
- [x] Montant `double_decale` : doublé sur **toute** sa longueur ; chaque ligne aboutée avec des joints décalés d'au moins `decalage_joints_min_mm` de ceux de l'autre ligne ; une éclisse par joint ; vis d'assemblage au pas fabricant.
- [x] Même profilé, même renfort : la longueur maximale diffère en orientation verticale et horizontale ; le résolveur lit la ligne correspondant à l'orientation de la surface.
- [x] Montant plus court qu'une barre en `double_decale` : pas de joint, pas d'éclisse de joint, mais longueur max du renfort décalé conservée.
- [x] Renfort localisé (bords d'ouverture) : seuls les montants concernés sont doublés.
- [x] Plafond suspendu : lisse périphérique sur la périphérie en longueur réelle, sauf arêtes ouvertes (faîtage, jonction avec un autre pan) selon `peripherie`.
- [x] Plafond suspendu : fourrures dans la direction de l'ossature à l'entraxe retenu (bornes article et plaque), suspentes au `pas` de l'article le long de chaque fourrure, longueur de suspente liée à la hauteur de plénum.
- [x] Plafond suspendu sous rampant : mêmes règles que le plafond horizontal, seules les longueurs réelles (pente) changent.

## Vérifications automatiques

Toutes doivent être vertes avant de renommer le fichier en `.DONE.md` :

```bash
symfony php vendor/bin/phpunit
symfony php vendor/bin/phpstan analyse
symfony console doctrine:schema:validate
```

## Décisions et écarts

**Couverture :** tous les critères sont cochés et couverts par un test : `tests/Integration/PlafondSuspenduIntegrationTest.php` (double pente réelle, dérivation jonctions vers `aretes_ouvertes`), `tests/Calcul/PlafondSuspenduTest.php` (vis, boîtes entières via `OptimiseurBesoins`, fourrure de rive), `tests/Calcul/OssatureRenfortsTest.php` (éclisses, vis d'assemblage, montant court, renfort localisé).

**Écarts :**
- Paramètre `aretes_ouvertes` (paires `a:b` séparées par des virgules) à la place de `peripherie` ; ouvertes = faîtage, noue, plat ; la sablière garde sa lisse. Dérivé des jonctions dans `CalculSysteme::parametresSurface`.
- Pas de champ `entraxe_max` ajouté au schéma du profilé : bornes issues de la plaque (`pose.entraxe_max`) et de la suspente (`pas`).
- Vis : spécification construite en code (valeurs concrètes filtrées en PHP), pas de gabarit `{contexte.*}`. Fixations de périphérie non comptées. La spécification des accessoires n'est résolue que si c'est du JSON sérialisé.
- Comparaison de candidats par variantes non câblée : le résolveur propose des candidats avec un coût optionnel (coût indisponible avant l'étape 06).
- Moteur : `ResultatCouche->erreurs` ajouté et le pipeline s'arrête dessus ; `framing_height_exceeded` devient bloquant. `PlanificateurPose` ajouté au pipeline (actif seulement si la définition déclare `entraxe`). `ContexteCouches` étendu (pasSuspente, pasVisParPeau, renfort, penetrationVis). `CalculateurParement` : constructeur modifié ; comptage des vis d'un plafond suspendu via `FourruresPlafond::longueurs` (fourrure de rive comprise).
- Paramètres `renfort` et `renfort_zone` ajoutés aux définitions cloison et doublage ; `PlanOssature::montantsBords` ajouté.

**Non vérifié :** rien dans un navigateur ; les longueurs de pente sont arrondies au mm (1803 pour 1802,8).
