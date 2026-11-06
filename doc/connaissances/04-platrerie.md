# Plâtrerie : cloisons, doublages, plafonds

Références : NF DTU 25.41 (ouvrages en plaques de plâtre à faces cartonnées : cloisons **et plafonds**, suspendus ou fixés directement), NF DTU 25.42 (doublages et habillages). Le DTU 25.51 concerne le **staff** (pas les plaques) et le NF DTU 58.1 les plafonds suspendus **modulaires** (dalles) : aucun des deux ne s'applique ici. Vérifier l'indice en vigueur avant de figer les valeurs.

## Matériel courant

| Élément | Remarques |
|---|---|
| Plaque de plâtre (BA13) | 1,20 m de large, hauteurs 2,00 à 3,00 m ; types : standard, hydro, feu, haute dureté, acoustique |
| Rail (R48/R70…) | Fixé sol/plafond ; 3 m en général |
| Montant (M48/M70…) | Entraxe 0,60 m (0,40 m pour certains parements) |
| Fourrure | Ossature de doublage / plafond (F530), entraxe ≈ 0,60 m |
| Appui intermédiaire | Fixe l'ossature de doublage au mur, répartition ≈ tous les 1,20 m |
| Suspente / tige | Plafond suspendu : ≈ 1 suspente par 0,60–1,20 m² selon charge |
| Bande à joint | Papier/armée, rouleau de 30 ou 75 m |
| Enduit, visserie | Au m² de plaque : visserie ≈ 15 vis/m² ; enduit ≈ 0,3–0,5 kg/m² |

## Systèmes

### Cloison (sur ossature)
Rails haut et bas, montants à l'entraxe, parement 1 ou 2 peaux de chaque côté, isolant entre montants
(optionnel). Autour de chaque ouverture : montants doublés, **linteau** (rail horizontal) avec
sur-longueur de fixation (le code ajoute 0,20 m).

### Doublage (sur ossature, contre un mur existant)
Mêmes éléments, plus **appuis intermédiaires** (hauteur de référence 1,20 m dans le code) et rails
porteurs d'appuis. Isolant entre ossature. Pour un doublage collé (hors v1), il n'y a pas d'ossature : plots de colle
(mortier adhésif) ou complexe collé (voir catalogue).

### Plafond suspendu sous plaque
Ossature de fourrures suspendues par suspentes, avec lisse (profilé) sur la périphérie ; primaires et secondaires selon fabricant. Valable pour un plafond horizontal comme pour un pan sous rampant. Géré en v1.

### Plafond autoportant
Pas de suspentes, rails et montants/fourrures rattachés aux murs, portée limitée (≈ 3–4 m selon profil
et charge). Sa portée maximale suit la table du fabricant en orientation **horizontale**, avec les mêmes
renforts (montants doublés, doublés en décalé avec éclisses) que les cloisons. Le système de calcul est à
écrire (voir `developpement/04-systemes-et-calcul.md`).

### Lambris sur lattes
Lattes entraxe ≈ 0,40–0,50 m, lames posées perpendiculairement ; calcul en m² de lames avec chute de
10–15 %, lattes en ml.

### Autres
La liste des systèmes est **extensible** : un système n'est pas du code figé, c'est une définition
(assemblage de couches + règles, chaque couche ayant son calculateur ; voir `developpement/04-systemes-et-calcul.md`).

## Cloison à deux faces

- **Ossature et isolant communs** : une seule ossature (rails, montants, renforts) et un seul isolant entre
  les montants servent les deux côtés.
- **Parement par face** : chaque côté a sa propre composition (1 ou 2 peaux, type de plaque : hydro d'un côté,
  standard de l'autre, phonique, feu…), son calepinage et ses joints. Les quantités sont calculées par face.
- **Épaisseur** = parement A + ossature + parement B (les peaux comptent des deux côtés).
- **Ouvertures** : une porte ou une baie traverse la cloison ; linteau et montants d'encadrement sont
  communs ; le retour de parement en tunnel (voir plus bas) couvre l'épaisseur totale de la cloison.
- **Extrémités** : contre un mur existant, un montant de rive est fixé au mur ; contre une autre cloison ou un
  doublage de mur, on ajoute un **montant supplémentaire de raccordement** pour fixer et raidir la jonction
  (nombre par jonction donné par la règle du fabricant, 1 par défaut).

## Doublage : rails ou lisses

L'ossature périphérique d'un doublage dépend du système :
- **système cloison** (ossature à rails et montants) : **rails haut et bas**, comme une cloison ;
- **système doublage** (lisses et fourrures) : **lisses haute et basse** à la place des rails.

Dans les deux cas le doublage a un profilé haut et un profilé bas ; seule la famille de profilés change
(`rail` ou `lisse` dans le catalogue). Les appuis intermédiaires et les rails porteurs d'appuis s'ajoutent
selon le système.

## Ouvertures : applique ou tunnel

- **Applique** : le parement vient buter sur le contour de l'ouverture ; le tour est fini par un joint ou une
  baguette de finition.
- **Tunnel** : on fait un **retour de parement** : bandes de plaque (ou de lambris, d'enduit) qui
  habillent le tour de l'ouverture, de la face visible du parement jusqu'au dormant. Elles sont portées par
  l'ossature de l'ouverture (rails et montants d'encadrement) et reçoivent des joints aux angles
  (cornières ou bandes armées sur les angles sortants).
- Profondeur du retour : épaisseur totale du complexe + refoncement éventuel ; si le complexe est épais,
  une fenêtre déjà posée peut nécessiter un retour plus profond ou un doublage d'ébrasement.
- Côtés : porte → linteau + 2 côtés ; fenêtre → linteau + 2 côtés + appui. Les retours augmentent les
  besoins en plaque (bandes), en joints, en visserie et en ossature d'encadrement.

## Renforcer une ossature de cloison

Quand une cloison est plus haute que la portée d'un montant simple, ou doit mieux résister aux charges,
le fabricant autorise :

- **réduire l'entraxe** (600 → 400 mm) : plus de montants ;
- **doubler les montants accolés** (vissés ensemble, dos à dos ou côte à côte selon le système) : 2 montants
  par position renforcée ;
- **doubler les montants en décalé** : le montant final est doublé **sur toute sa longueur**, avec deux
  lignes de profilés vissées ensemble, chacune aboutée avec des **joints décalés** d'une ligne à l'autre ;
  des **éclisses de liaison** renforcent chaque joint. Cela permet de **dépasser la longueur maximale**
  du montant simple. Écart minimal entre joints, pas de vis d'assemblage et nombre d'éclisses sont donnés
  par le fabricant ;
- la **longueur maximale diffère selon l'orientation de pose** : verticale (cloison, doublage : hauteur) ou
  horizontale (plafond autoportant : portée entre appuis) ;
- **localiser** le renfort : seulement aux abords d'une ouverture ou d'une charge, plutôt que sur toute la
  cloison.

Ces règles dépendent du profilé et de la marque : elles ne sont pas déductibles d'une formule. Elles sont
stockées comme données de l'article (voir `06-catalogue-materiaux.md`) et validées avec la fiche fabricant.

## Systèmes multicouches

- **Plusieurs peaux de plaques** : 2 peaux améliorent l'acoustique, le feu et la résistance. Les joints
  de la 2ᵉ peau sont **décalés** de ceux de la 1ʳᵉ (au moins ≈ 40 cm en vertical, rangées décalées) ; vis
  plus longues (épaisseur des peaux + pénétration dans l'ossature) ; la 1ʳᵉ peau est fixée à un pas plus
  large, la dernière à un pas serré (valeurs fabricant).
- **Types de plaques** : standard, hydro (pièces humides), feu, phonique, haute dureté, etc. Ils sont des
  caractéristiques de l'article ; les noms commerciaux sont des références fabricant.
- **Isolant en deux couches** : posées côte à côte (non croisées) ou **croisées** (2ᵉ couche perpendiculaire à
  la 1ʳᵉ, pour traiter les ponts thermiques de l'ossature et limiter les joints alignés). Avec des attaches
  d'isolant (appuis ou pattes), la longueur d'attache dépend de l'épaisseur isolée totale.
- **Isolant entre ossature ou continu** : entre ossature, la profondeur du profilé doit au moins égaler
  l'épaisseur d'isolant ; en continu (devant l'ossature), l'ossature est dimensionnée par ailleurs.
- L'épaisseur totale du complexe est la somme de tout ce qui est empilé ; elle commande les retours
  d'ouverture et les longueurs de visserie.

## Règles de calcul de base

- **Montants** : positions à `x = k × entraxe` depuis l'origine de l'ossature, + montant de fin, + montants
  doublés de chaque côté d'une ouverture ; aucun montant dans la largeur d'une ouverture, sauf
  petits montants au-dessus/au-dessous (impostes, allèges).
- **Rails** : bas, haut, + linteau (et seuil pour une fenêtre) avec sur-longueur de fixation.
- **Plaques** : calepinage en rangées à joints décalés (≥ 0,40 m), découpe autour des ouvertures ;
  surface utilisée vs surface achetée → chutes réutilisables.
- **Joints** : bande sur chaque jonction de plaques ; joint « armé » (angles sortants) vs plat.
- **Optimisation de coupes** : problème de découpe 1D (bin packing). Heuristique actuelle : tri
  décroissant + first-fit, suffisante tant que l'on affiche la chute.
- **Lots** : on achète des lots entiers : `ceil(quantité / unité par lot)`.

## Contraintes pratiques

Épaisseur de l'ensemble (réduction de surface habitable), hauteur sous plafond mini, continuité du
pare-vapeur, jeu de 10 mm entre plaque et sol, étanchéité à l'air.
