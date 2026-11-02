# Systèmes de revêtement et moteur de calcul

Connaissances : [03-isolation-thermique.md](../connaissances/03-isolation-thermique.md),
[04-platrerie.md](../connaissances/04-platrerie.md).

## Concepts

- **`ParoiExistante`** : composition de la paroi à rénover (voir plus bas). Optionnelle.
- **`DefinitionSysteme`** : code, libellé, types de surface compatibles (`mur`, `plafond_pan`, `sol`, `cloison`) : un mur, un pan incliné et un plafond horizontal sont la même géométrie, un système s'applique aux trois et seuls les matériaux changent,
  **emplacements de couches** (voir plus bas), paramètres (schéma). Un système est un **assemblage
  ordonné de couches**, pas un calculateur monolithique.
- **`EmplacementCouche`** : rôle, cardinalité (`min`..`max`, ex. 1 à 2 parements), catégorie de matériau
  attendue, position par rapport à l'ossature, orientation autorisée, `CalculateurCouche` associé.
- **`SystemeApplique`** (entité) : choix d'une définition pour une surface ; contient les couches
  réellement posées. **Plusieurs par surface = variantes**, une seule active.
- **`CalculateurCouche`** (interface) : `calculer(CoucheContexte, ContexteCouches): ResultatCouche`
  qui renvoie des **lignes de besoin** et le contexte mis à jour.
- Les définitions et les calculateurs de couche sont enregistrés par tag de service : ajouter un
  système ou un type de couche = une classe + un tag.

## Patterns

| Besoin | Pattern | Rôle |
|---|---|---|
| Lien mur → catalogue | **Spécification** | Une couche exprime une exigence (rôle, catégorie, critères sur le JSON) ; le catalogue se filtre avec elle ; un article épinglé est validé contre elle |
| Lien catalogue → fournisseur | **Stratégie** + **chaîne de responsabilité** | Choix du fournisseur (unique, meilleur prix, plus proche) puis substitution par un équivalent |
| Empilement des couches | **Pipeline** (composite ordonné) | Chaque couche reçoit le contexte des couches précédentes et le complète |
| Lecture typée du catalogue | **Façade** (`IsolantView`, `ProfilView`…) | Les calculateurs ne lisent ni JSON brut ni prix |
| Chiffrage figé | **Instantané** (Memento) | Voir `08-fournisseurs-chiffrage-export.md` |

### Spécification et résolution des matériaux

`MaterielSpecification` : rôle, catégorie, critères (clé JSON, opérateur, valeur), par exemple
`isolant`, `lambda <= 0.035`, `epaisseur >= 100`. Méthode `estSatisfaitePar(Materiau): bool`.

Une `CoucheAppliquee` porte la spécification et, éventuellement, un **matériau épinglé** :
- épinglé : le matériau doit satisfaire la spécification (validation), sinon avertissement ;
- non épinglé : `ResolveurMateriau` propose les candidats du catalogue qui la satisfont ; le premier
  (ou celui choisi par l'utilisateur) est retenu avant le calcul.

Au chiffrage, un article indisponible chez le fournisseur choisi est remplacé par un **équivalent**
(autre article satisfaisant la même spécification) **uniquement si la correspondance exacte n'est pas
disponible** et si l'utilisateur l'a autorisé. Chaîne de résolution (`ResolveurOffre`) : article épinglé
chez le fournisseur → équivalent chez ce fournisseur → épinglé ou équivalent chez un autre fournisseur
(sauf stratégie `FournisseurUnique`) → ligne « prix manquant ». Chaque maillon est testable seul.

**Un équivalent a d'autres caractéristiques** (longueur de barre, dimensions de plaque, rouleau, lot) : la
substitution **déclenche le recalcul de l'optimisation de coupes** (et du calepinage) de cette ligne avec
les caractéristiques de l'article retenu. Le moteur conserve donc, pour chaque matériau, les **besoins
bruts** (pièces à découper, surfaces, longueurs, nombres de pièces) et non seulement le nombre de barres.
L'optimisation et l'arrondi aux lots sont toujours exécutés **pour un article précis** ; l'ordre est :
besoins bruts → résolution de l'article (fournisseur, équivalent) → optimisation → marge → lots.

## Couches multiples, orientation et position

Une définition de système peut autoriser **plusieurs couches du même rôle** :

| Emplacement | Rôle | Cardinalité | Remarques |
|---|---|---|---|
| attache | fixation | 0..1 | Appuis, pattes, suspentes ; dépend de l'épaisseur isolée |
| isolant | isolant | 1..2 | 2ᵉ couche optionnelle, orientation `0` ou `90` (croisée) |
| ossature | ossature | 1 | Cloison ou doublage ; sa profondeur dépend des isolants « entre ossature » |
| parement | parement | 1..2 | 1 ou 2 peaux ; types hydro, phonique, feu, haute dureté…, tous des articles de la catégorie `plaque_platre` |

Chaque couche posée porte :
- **`orientation`** (degrés, relative à la direction de référence de la surface) : détermine la direction des
  coupes de la couche (voir `Surface::coupes()` dans `03-geometrie.md`). Une 2ᵉ couche d'isolant à 90° est
  croisée ; à 0° elle est simplement doublée.
- **`position`** par rapport à l'ossature : `entre_ossature` (l'isolant remplit la cavité, surface isolée
  = surface nette − ossature, pont thermique), `continue` (passe devant l'ossature) ou `sur_ossature`
  (parement).
- **`decalage`** (calepinage) : pour une 2ᵉ peau de plaques, décalage des joints par rapport à la peau
  précédente (≥ 0,40 m pour les joints verticaux, rangées horizontales décalées).

### Pipeline de couches et contexte

`PipelineCouches` parcourt les couches de la plus proche du support à la plus visible. À chaque étape,
le calculateur lit et enrichit un `ContexteCouches` (objet valeur immuable, remplacé à chaque étape) :

| Donnée du contexte | Lue par | Exemple |
|---|---|---|
| épaisseur cumulée | attache, ossature, vis | Longueur de l'appui d'isolant = épaisseur isolée |
| profondeur de cavité | ossature | Montant M70 si isolant 70 mm entre ossature |
| direction de l'ossature | isolant croisé, lattes | La 2ᵉ couche d'isolant croisée est perpendiculaire à la 1ʳᵉ |
| calepinage de la couche précédente | parement suivant | Joints de la 2ᵉ peau décalés |
| épaisseur de parement totale | visserie | Longueur de vis = épaisseur des peaux + pénétration dans l'ossature |
| R cumulé | thermique | Contrôle de l'objectif |

Règles d'ordre : un isolant « entre ossature » est évalué **après** le choix de l'ossature (sa
profondeur est une contrainte) ; le pipeline gère ces dépendances par une phase de planification
(profondeurs) puis une phase de calcul (besoins). Les erreurs de cohérence sont des avertissements
explicites (ex. « isolant 100 mm dans une ossature de 70 mm »).

### Exemple : isolant sur attaches, ossature, deux peaux

1. Attache d'isolant (appuis) — fixation ;
2. Isolant 1, 60 mm, 0° — isolant ;
3. Isolant 2, 60 mm, 90° (croisé) — isolant ;
4. Ossature de doublage (montants + rails) — ossature ;
5. Parement 1 BA13 hydro — parement ;
6. Parement 2 BA13 phonique, joints décalés — parement.

L'épaisseur cumulée (120 mm d'isolant + ossature + 2 × 12,5 mm de plaque) pilote les attaches, la
profondeur d'ossature et la longueur des vis ; le R total additionne les deux isolants.

### Mise en œuvre incrémentale

- v1 : cardinalités multiples, orientation `0`/`90`, position entre ossature / continue, contexte
  (épaisseur cumulée, profondeur de cavité) ; calepinage décalé simple (décalage fixe d'une demi-plaque).
- v2 : pont thermique d'ossature, optimisation du décalage selon les ouvertures, vis par épaisseur
  choisies automatiquement dans le catalogue.
- Les entraxes, pas d'attache et portées limites viennent des **fiches fabricants** : ce sont des
  paramètres du catalogue (`caracteristiques`), pas des formules codées en dur.

### Systèmes initiaux

Chaque système est un assemblage d'emplacements de couches ; les calculateurs actuels deviennent le
calculateur de la couche « ossature » (et « attache » pour les appuis).

| Code | Surfaces | Réutilise |
|---|---|---|
| `cloison_ossature` | cloison (deux faces) | `OssatureCloisonCalculator` (couche ossature) |
| `doublage_ossature` | mur | `OssatureDoublageCalculator` (couche ossature + attaches) |
| `doublage_colle` | mur | à écrire (complexe collé / plots de colle) |
| `plafond_suspendu_placo` | plafond_pan | à écrire (fourrures, suspentes, plaques) |
| `plafond_autoportant_placo` | plafond_pan | à écrire : ossature **horizontale**, portée = distance entre appuis ; utilise la table de portées en orientation `horizontale` et les renforts |
| `lambris_sur_lattes` | mur, plafond_pan | à écrire |
| `isolation_seule` | mur, plafond_pan | à écrire (isolant + membrane) |

## Cloison à deux faces

La cloison est un objet avec **un seul `SystemeApplique`**, des couches **communes** et des couches **par
face** :

| Emplacement | Portée | Remarques |
|---|---|---|
| ossature | commune (`face = null`) | une seule ossature pour les deux côtés ; profil, entraxe, renforts |
| isolant | commun | entre ossature (profondeur de cavité) ou continu |
| attache | commune | si le système en comporte |
| parement | **par face** (`A`, `B`) | 1 à 2 peaux, type et épaisseur propres à chaque côté ; calepinage, joints et visserie par face |

### Pipeline à deux faces
`PipelineCouches` pour une cloison parcourt la pile **de la face A vers la face B** : parement(s) A →
isolant/ossature → parement(s) B. L'épaisseur cumulée est la somme des deux parements et de l'ossature
(épaisseur de la cloison). Les calculs :
1. **Planification** (sans polygone) : **profilé choisi sur la hauteur maximale** de la cloison à l'axe,
   **épaisseur de la cloison** (publiée vers `Cloison.epaisseur`, voir `03-geometrie.md`), entraxe, renfort,
   profondeur de cavité (isolant), choix des vis **par face** (critère
   calculé sur l'épaisseur de parement de la face).
2. **Ossature** : calculée **une fois** pour la cloison (rails, montants, renforts, linteaux d'ouverture),
   dans le repère du tracé ; hauteur variable si plafond rampant.
3. **Parements** : calculés **une fois par face**, dans le repère de la face (ouvertures projetées, joints
   décalés de la 2ᵉ peau propres à la face).
4. **Agrégation** : les besoins de l'ossature ne sont comptés **qu'une fois** ; ceux des parements sont comptés
   par face puis agrégés par matériau pour le chiffrage de la pièce.

### Rails périphériques et montants d'extrémité
- **Rails** : bas (sol) et haut (plafond ou pans) sur toute la longueur ; pour un plafond rampant, la longueur
  du rail haut est la longueur réelle de la ligne de jonction avec le(s) pan(s).
- **Extrémité contre un mur existant** : montant de rive fixé au mur.
- **Extrémité contre une autre cloison ou un doublage de mur** : en plus du montant d'extrémité, un
  **montant supplémentaire de raccordement** est ajouté à la jonction (`montants_raccordement`, défaut 1,
  paramètre du système donné par le fabricant), rattaché à la structure qui reçoit, **hors de la grille
  d'entraxe**. Il est compté dans les besoins de montants de la cloison ou du doublage correspondant.
- Extrémité `libre` : montant d'extrémité seul, avertissement.

### Ouvertures d'une cloison
Une porte ou baie de cloison est portée par la cloison : linteau et montants d'encadrement sont communs
(l'ossature n'est comptée qu'une fois) ; la découpe des plaques est faite sur chaque face. Le **retour de
parement en tunnel** couvre l'épaisseur totale de la cloison (voir « Retour de parement ») ; la matière du
retour est celle du parement d'une face choisie (par défaut la face A, modifiable).

## Doublage : rails ou lisses

Un doublage de mur a toujours un profilé périphérique **haut et bas**. La famille dépend du système
(`type_profil_peripherique`) :
- `rail` : système de type cloison (rails + montants) ;
- `lisse` : système doublage (lisses haute et basse + fourrures).

Le calculateur de la couche ossature d'un doublage lit ce paramètre et sélectionne le profilé dans le
catalogue (`profil_ossature`, famille `rail` ou `lisse`). Les appuis intermédiaires et rails porteurs d'appuis
restent propres au système.

Un doublage peut recevoir une cloison (ancrage `doublage`) : le montant supplémentaire de raccordement est
alors ajouté à l'ossature du doublage à la position de la jonction. L'épaisseur du doublage (variante
active) décale l'extrémité de la cloison ancrée ; activer une autre variante régénère les faces de la cloison.

## Paramètres de mise en œuvre (entraxe, pas, portée, vis)

Entraxes, pas de fixation, portées limites et longueurs de vis changent les **quantités**. Ils sont
répartis sur trois niveaux (détail du stockage dans `02-catalogue.md`, groupe `pose`) :

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

### Vis et visserie

- Catégorie `visserie` : `longueur`, `usage` (`plaque_sur_metal`, `plaque_sur_bois`…), `epaisseur_fixable_min`
  et `max`.
- **Spécification à critère calculé** : `MaterielSpecification` accepte des valeurs tirées du contexte :
  `usage = plaque_sur_metal` et `longueur >= {contexte.epaisseurParementsPeau + contexte.penetrationVis}`.
  `ResolveurMateriau` retient la plus petite vis compatible. Une vis par peau si les peaux n'ont pas la même
  épaisseur cumulée (la 1ʳᵉ peau passe sa vis dans une seule plaque, la 2ᵉ dans deux).
- **Quantité** : nombre de points de fixation = longueur d'ossature de la peau ÷ pas de la peau (large pour
  la 1ʳᵉ peau, serré pour la dernière), plus les fixations de périphérie ; si le fabricant ne donne qu'une
  consommation au m², elle sert de repli. Les vis s'achètent en boîtes (lots entiers).

### Portée limite : un contrôle qui peut modifier la configuration

Elle ne donne pas une quantité directement : elle vérifie que la hauteur utile de la surface respecte la
limite du profilé pour la configuration retenue `(entraxe, renfort)`. En cas de dépassement, la phase de
planification **propose** des configurations (voir ci-dessous), l'utilisateur choisit ou laisse la
stratégie choisir.

## Renforts d'ossature (montants doublés)

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

### Configuration d'ossature

Objet valeur `ConfigurationOssature` : profilé, entraxe, renfort, **zone** d'application (`toutes_positions`,
`bords_ouvertures`, positions listées). Le renfort peut être général ou localisé (par exemple seulement
les montants qui encadrent une porte).

`ConfigurationOssatureResolveur` (phase de planification) :
1. liste les configurations possibles : lignes de la table de portées du profilé de **la bonne
   orientation** dont `longueur_max ≥` longueur du montant final (hauteur utile ou portée entre appuis)
   et dont l'entraxe respecte la priorité ci-dessus ;
2. applique une **stratégie** de choix (`StrategieConfiguration`) : la plus économe en matériel,
   la plus économe en coût (avec le fournisseur choisi), ou imposée par l'utilisateur ;
3. écrit la configuration retenue dans `ContexteCouches` ; si aucune ne convient, avertissement bloquant
   (« hauteur 3,2 m : aucun profil M48 ne convient, essayer M70 ou un renfort décalé »).

L'utilisateur peut aussi proposer une configuration qui n'est pas dans la table : acceptée avec
avertissement (hors données fabricant), jamais silencieusement.

### Calcul des quantités avec renfort

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

## Marge de sécurité et arrondi aux lots

Chaque matériau a une **marge de sécurité en pourcentage** (casse, erreur de découpe, imprévus), distincte
des chutes déjà calculées par l'optimisation de coupes. Elle s'applique **une fois**, sur la quantité
agrégée du matériau (après optimisation et agrégation de toutes les surfaces/faces du chiffrage) :

1. `quantiteBesoin` : quantité nécessaire dans l'**unité d'achat** (barres pour les profilés après
   optimisation, plaques, m² d'isolant, rouleaux, pièces, boîtes de vis…) ;
2. `quantiteAvecMarge = quantiteBesoin × (1 + marge/100)` ;
3. `nombreLots = ceil(quantiteAvecMarge / unitesParLot)` : **arrondi au supérieur selon la quantité par
   lot** ; un seul arrondi, pas un par étape ;
4. `quantiteAchetee = nombreLots × unitesParLot` ; chute totale = `quantiteAchetee − quantiteBesoin`.

La marge est lue par matériau selon la priorité de `02-catalogue.md` (chiffrage > projet > matériau >
catégorie > défaut global). Elle est copiée dans la ligne de chiffrage pour traçabilité
(`margePct`, `quantiteAvecMarge`). Les profilés optimisés en barres : la marge s'applique au nombre de barres
(`barres × (1 + marge)`), pas aux longueurs unitaires.

## Retour de parement (ouvertures en tunnel)

Une ouverture en pose `tunnel` impose un **retour de parement** ; en pose `applique`, rien.

Calcul (calculateur de la couche parement, avec accès au `ContexteCouches`) :
- `profondeurRetour = épaisseur cumulée du complexe devant le support + refoncement` de l'ouverture
  (règle validée ; surcharge manuelle possible) ;
- côtés : linteau + côtés (et appui pour une fenêtre) ; chaque côté est une **bande** de largeur
  `profondeurRetour` et de longueur celle du côté de l'ouverture (+ chevauchement éventuel) ;
- les bandes entrent dans le **calepinage des plaques** comme pièces supplémentaires (même type de plaque
  que le parement, peau visible) et dans l'optimisation de coupes ;
- joints : angles sortants (retour/parement) en bande armée ou cornière, angles rentrants (retour/appui) en
  bande à joint plat ; ils sont ajoutés aux besoins en joints ;
- ossature d'encadrement : rails/montants d'ouverture (déjà présents pour les linteaux) suffisent à porter
  le retour tant que la profondeur ne dépasse pas la capacité du système (sinon avertissement) ;
- visserie : les bandes de retour comptent dans la surface de peau pour le calcul des vis.

L'aperçu 2D du mur affiche ces retours ; le mode de pose est modifiable à tout moment par ouverture
(voir `06-interface.md`).

## Paroi existante

- Liste de couches du support actuel : matériau existant (catégorie catalogue `paroi_existante` :
  parpaing, béton, brique, pierre, plâtre, bois…) + épaisseur, ou un **R forfaitaire** saisi.
- Sert au calcul du R total : `RT = Rsi + R(paroi existante) + Σ R(système) + Rse`.
- Sert aux contrôles : épaisseur totale, support compatible avec le système (ex. doublage collé).
- Pas de prix, pas de lot : les articles de cette catégorie ne figurent jamais dans une nomenclature.

## Variantes

- L'utilisateur crée une variante (copie du système actif, modifiable) et la nomme.
- Écran de comparaison pour une surface, une pièce ou un projet : par variante, R/U, coût (selon un
  fournisseur ou le meilleur prix, voir `08-fournisseurs-chiffrage-export.md`), épaisseur totale.
- Le calcul global, le chiffrage et les exports utilisent **la variante active** de chaque surface.
- `ServiceVariantes::comparer(Surface|Piece|Projet): list<ComparaisonVariantes>` calcule sans modifier
  l'état actif ; activer une variante est une action explicite.

## Chaîne de calcul

```
Projet
 └─ pour chaque Piece
     └─ pour chaque Surface (variante active)
          ├─ ThermiqueCalculator  : R par couche, RT, U (paroi existante comprise)
          ├─ PipelineCouches      : résout les matériaux (spécifications), puis parcourt les couches
          │    └─ CalculateurCouche : attache, isolant (surface nette, chute), ossature (rails, montants),
          │                           membrane (rouleaux, recouvrements), parement (calepinage, joints, vis)
          └─ ResultatSurface
     └─ ResultatPiece
 └─ BesoinsProjet : agrégation par matériau
        └─ MateriauxCalculator : optimisation de coupes 1D, chute, taux de perte,
              marge de sécurité par matériau, arrondi au lot supérieur
              └─ (les prix sont appliqués au chiffrage, voir 08-…)
```

Les calculs géométriques d'ossature utilisent les coupes `Surface::coupes()` dans le repère de la
surface (voir `03-geometrie.md`), donc valables pour les plafonds à pans. Les besoins de plusieurs
surfaces du même matériau sont **agrégés avant optimisation**, pour que les chutes d'un mur servent à
un autre (option : calcul par surface ou par projet).

## Isolant et thermique

- `IsolantView` : λ, épaisseur, R certifié (prioritaire sur `e/λ`).
- `ThermiqueCalculator::resistanceTotale(Surface, ParoiExistante, list<Couche>, Rsi, Rse)` ; Rsi/Rse selon
  le type de surface, constantes documentées.
- Option v2 : pont thermique d'ossature (fractions de surface).
- Affichage : R par couche, RT, U, objectif saisi, delta.

## Membrane

Surface = surface isolée ; rouleaux = `ceil(surface × (1 + recouvrement) / surface utile du rouleau)` ;
adhésif en ml selon jonctions de lés et périphérie ; R = 0.

## Évolutions de l'existant

1. Passer les calculateurs en mm et leur donner une `SurfaceCalculable` (polygone + ouvertures + repère)
   au lieu de `Mur`.
2. Supprimer le catalogue passé en paramètre : lire les `Materiau` choisis dans le système appliqué.
3. Remplacer les tableaux associatifs de résultat par des objets typés (`LigneBesoin`, `ResultatCouche`, `ContexteCouches`).
4. `MateriauxCalculator` prend des **besoins bruts** et un `Materiau` précis (ses dimensions, son lot) et
   renvoie des `LigneBesoinOptimise` (barres, lots, chutes) sans prix ; il est rappelé quand l'article change
   (substitution par équivalent).
5. Le calepinage des plaques (déjà ébauché) devient un composant partagé `CalepinagePlaques`.
6. Le défaut actuel du contrôleur (catalogue codé en dur ignorant la saisie) disparaît avec le point 2.

## Critères d'acceptation

- Les résultats de cloison/doublage restent identiques aux tests de caractérisation de la phase 0
  (tolérance sur les arrondis mm).
- Ajouter un nouveau système n'exige aucune modification du moteur ni des entités.
- Le R total d'un mur de test (paroi existante + 100 mm de laine λ 0,035 + BA13) est conforme au calcul manuel.
- Un plafond double pente avec fourrures est calculé avec les longueurs réelles de pente.
- Deux variantes sur une surface se comparent sans changer la variante active.
- Système « 2 isolants croisés + ossature + 2 parements » : les couches s'enchaînent, l'épaisseur
  cumulée pilote profondeur d'ossature, attaches et vis ; le R total additionne les deux isolants.
- Une 2ᵉ couche d'isolant à 90° est coupée perpendiculairement à la 1ʳᵉ ; à 0° elle est parallèle.
- Les joints de la 2ᵉ peau de plaques sont décalés de ≥ 0,40 m de ceux de la 1ʳᵉ.
- Ouverture en tunnel : des bandes de retour (linteau, côtés, appui pour une fenêtre) de largeur
  `profondeurRetour` s'ajoutent aux plaques, joints et vis ; en applique, rien n'est ajouté.
- Changer le mode de pose d'une ouverture (applique ↔ tunnel) change les quantités sans autre modification.
- Une couche non épinglée est résolue par spécification ; un article épinglé qui ne la satisfait pas
  déclenche un avertissement.
- Priorité des paramètres de pose : surcharge utilisateur > `min(défaut du système, limites des articles)` ;
  un entraxe utilisateur hors limite est accepté avec avertissement ; une limite supérieure au défaut ne
  remplace pas le défaut.
- Vis choisie par critère calculé : plus petite longueur ≥ épaisseur des peaux + pénétration mini ; quantité
  = longueur d'ossature de la peau ÷ pas de la peau (boîtes entières).
- Mur plus haut que la portée du montant simple : le résolveur propose entraxe réduit, profilé plus fort,
  doublage accolé ou doublage décalé avec éclisses, selon la table du fabricant.
- Montant `double_decale` : doublé sur **toute** sa longueur ; chaque ligne aboutée avec des joints décalés
  d'au moins `decalage_joints_min_mm` de ceux de l'autre ligne ; une éclisse par joint ; vis d'assemblage
  au pas fabricant.
- Même profilé, même renfort : la longueur maximale diffère en orientation verticale et horizontale ; le
  résolveur lit la ligne correspondant à l'orientation de la surface.
- Montant plus court qu'une barre en `double_decale` : pas de joint, pas d'éclisse de joint, mais longueur
  max du renfort décalé conservée.
- Renfort localisé (bords d'ouverture) : seuls les montants concernés sont doublés.
- Remplacement par un équivalent chez le fournisseur choisi, **seulement en l'absence de correspondance
  exacte**, puis ligne « prix manquant » en dernier recours.
- Un équivalent de longueur différente (rail 2,6 m au lieu de 3 m) produit une optimisation de coupes
  recalculée avec sa longueur, donc un nombre de barres et des chutes différents.
- Cloison à deux faces : l'ossature et l'isolant sont comptés une seule fois ; les parements sont comptés
  par face ; deux types de plaques différents sur les deux côtés produisent deux lignes.
- Extrémité de cloison contre une autre cloison ou un doublage : un montant supplémentaire de raccordement est
  compté (1 par défaut, selon le fabricant) ; contre un mur existant : seulement le montant de rive.
- Cloison sous un plafond rampant : hauteur de la face variable, longueur de rail haut = longueur réelle.
- Une ouverture de cloison apparaît sur les deux faces ; son retour en tunnel couvre l'épaisseur totale.
- Doublage avec un système cloison : rails haut et bas ; avec un système doublage : lisses haute et basse.
- Marge 10 % sur 11 plaques avec 1 plaque par lot : `ceil(12,1) = 13` ; avec 5 plaques par lot : 3 lots
  (15 plaques achetées).
- La marge d'un matériau suit la priorité chiffrage > projet > matériau > catégorie > défaut global.
- Un projet de 2 pièces produit des besoins agrégés avec lots entiers et chutes.
