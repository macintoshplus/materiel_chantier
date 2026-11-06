<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Cadre;

use App\Calcul\Cadre\BandeRetour;
use App\Calcul\Cadre\RetourParement;
use App\Calcul\Calepinage\CalepinageBandes;
use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Cadre\FourruresPlafond;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Calcul\Materiau\SourceCandidats;
use App\Catalogue\Critere;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
use App\Catalogue\Vue\PlaqueView;
use App\Catalogue\Vue\VisserieView;
use App\Enum\TypeSurface;
use App\Geometrie\Avertissement;

/**
 * Une peau de plaques : calepinage (décalée d'une demi-plaque de la peau précédente sauf décalage explicite) et
 * vis dont la longueur est l'épaisseur de parement cumulée plus 10 mm de pénétration. Le nombre de vis suit les
 * montants à l'entraxe retenu par l'ossature.
 *
 * Quand la planification a retenu des paramètres de pose (système qui déclare `entraxe`), la pénétration et le pas
 * de la peau viennent du contexte et la vis est choisie dans le catalogue : usage `plaque_sur_metal`, plage
 * d'épaisseur fixable qui contient l'épaisseur cumulée, puis la plus courte de longueur au moins épaisseur + pénétration.
 *
 * Retour de parement : sur la dernière peau (peau visible), chaque ouverture en tunnel ajoute des bandes de plaque
 * (linteau, côtés, appui) de largeur `profondeurRetour` = épaisseur du complexe + refoncement (ou paramètre
 * `retour_profondeur`). Elles entrent dans le calepinage et l'optimisation de coupes (plaques supplémentaires), dans le
 * nombre de vis (une rangée au pas de la peau le long de chaque bande) et dans la surface de parement (enduit). Les
 * faces de cloison sont exclues : leur retour couvre l'épaisseur totale de la cloison et est calculé par le système.
 */
final readonly class CalculateurParement implements CalculateurCouche
{
    private const int PENETRATION = 10;

    public function __construct(
        private CalepinagePlaques $calepinage,
        private SourceCandidats $source,
        private RegistreCategories $categories,
        private CalepinageBandes $bandes = new CalepinageBandes(),
    ) {
    }

    public function code(): string
    {
        return 'parement.plaques';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The facing layer has no material.');
        $plaque = new PlaqueView($materiau);
        $boite = $couche->surface->boite();
        $decalage = $couche->couche->decalage
            ?? (null !== $contexte->calepinagePrecedent ? $this->calepinage->decalageDemiPlaque($plaque->largeur()) : 0);
        $plan = $this->calepinage->calepiner($boite->largeur(), $boite->hauteur(), $plaque->largeur(), $plaque->longueur(), $decalage);

        $avertissements = [];
        if (null !== $contexte->calepinagePrecedent) {
            $avertissement = $this->calepinage->verifierDecalage($contexte->calepinagePrecedent, $plan);
            if ($avertissement instanceof Avertissement) {
                $avertissements[] = $avertissement;
            }
        }

        $cumule = $contexte->epaisseurParementCumulee + ($couche->epaisseur() ?? $plaque->epaisseur());
        $pose = $plaque->pose();
        $derniere = $cumule >= $contexte->epaisseurParementPrevue;
        $premiere = 0 === $contexte->epaisseurParementCumulee;
        $pas = $contexte->pasVisParPeau[$couche->couche->ordre] ?? match (true) {
            $premiere && $derniere => $pose->pasVisPeauUnique,
            $premiere => $pose->pasVisPremierePeau,
            default => $pose->pasVisDernierePeau,
        };
        $longueurMontants = 0;
        if (TypeSurface::PlafondPan === $couche->surface->type && null !== $contexte->penetrationVis) {
            $longueurMontants = array_sum(FourruresPlafond::longueurs($couche->surface, $contexte->directionOssature, $contexte->entraxeRetenu ?? $pose->entraxeMax));
        } else {
            foreach ($couche->surface->coupes($contexte->directionOssature, $contexte->entraxeRetenu ?? $pose->entraxeMax) as $segment) {
                $longueurMontants += $segment->longueur();
            }
        }
        $vis = (int) ceil($longueurMontants / $pas);

        $groupes = [];
        $profondeurRetour = 0;
        if ($derniere && TypeSurface::CloisonFace !== $couche->surface->type) {
            [$groupes, $profondeurRetour, $avertissementsRetour] = $this->retours($couche, $contexte);
            array_push($avertissements, ...$avertissementsRetour);
        }
        $bandes = array_merge(...$groupes);
        $plaquesRetour = [] === $bandes ? 0 : $this->bandes->plaques($bandes, $plaque->largeur(), $plaque->longueur());
        $visRetour = 0;
        $surfaceRetour = 0;
        foreach ($bandes as $bande) {
            $visRetour += (int) ceil($bande->longueur / $pas);
            $surfaceRetour += $bande->surface();
        }
        $sortants = 0;
        $rentrants = 0;
        foreach ($groupes as $groupe) {
            $sortants += RetourParement::longueurAnglesSortants($groupe);
            $rentrants += RetourParement::longueurAnglesRentrants($groupe, RetourParement::estFerme($groupe));
        }

        $penetration = $contexte->penetrationVis ?? self::PENETRATION;
        $visserie = null;
        if (null !== $contexte->penetrationVis) {
            $visserie = $this->vis($cumule, $penetration);
            if (null === $visserie) {
                $avertissements[] = new Avertissement('screw_no_candidate', ['thickness' => $cumule, 'penetration' => $penetration]);
            }
        }
        $longueurVis = $visserie?->valeur('longueur');
        $retourPlaques = 0 === $plaquesRetour ? [] : ['retour_plaques' => $plaquesRetour, 'retour_profondeur_mm' => $profondeurRetour];
        $retourVis = 0 === $visRetour ? [] : ['retour_vis' => $visRetour];

        return new ResultatCouche([
            LigneBesoin::nombre('plaques_'.$couche->couche->ordre, $couche->emplacement->code, 'plaque_platre', \count($plan->plaques) + $plaquesRetour, ['epaisseur_mm' => $plaque->epaisseur()] + $retourPlaques, $materiau),
            LigneBesoin::nombre('vis_'.$couche->couche->ordre, $couche->emplacement->code, 'visserie', $vis + $visRetour, ['longueur_mm' => \is_int($longueurVis) ? $longueurVis : $cumule + $penetration] + $retourVis, $visserie),
        ], $contexte->avecCalepinage($plan)->avecEpaisseurParementCumulee($cumule)->avecSurfaceParement($couche->surface->surfaceNette() + $surfaceRetour)->avecAnglesRetour($sortants, $rentrants), $avertissements);
    }

    /**
     * Bandes de retour de toutes les ouvertures en tunnel de la surface.
     *
     * @return array{list<list<BandeRetour>>, int, list<Avertissement>} bandes par ouverture en tunnel, profondeur du retour de la dernière ouverture, avertissements
     */
    private function retours(CoucheContexte $couche, ContexteCouches $contexte): array
    {
        $complexe = max($contexte->profondeurOssature, $contexte->epaisseurCumulee - $contexte->epaisseurParementCumulee) + $contexte->epaisseurParementPrevue;
        $imposee = $couche->parametreEntier('retour_profondeur', 0);
        $sol = $couche->surface->boite()->minY;
        $groupes = [];
        $avertissements = [];
        $profondeur = 0;
        foreach ($couche->surface->ouvertures as $ouverture) {
            $profondeur = RetourParement::profondeur($imposee > 0 ? $imposee : $complexe, $ouverture);
            $ouvertureBandes = RetourParement::bandes($ouverture, $profondeur, $sol);
            if ([] !== $ouvertureBandes && $profondeur > $complexe + $ouverture->refoncement) {
                $avertissements[] = new Avertissement('reveal_deeper_than_framing', ['depth' => $profondeur, 'capacity' => $complexe + $ouverture->refoncement]);
            }
            if ([] !== $ouvertureBandes) {
                $groupes[] = $ouvertureBandes;
            }
        }

        return [$groupes, $profondeur, $avertissements];
    }

    /**
     * Plus petite vis (longueur) dont la plage fixable contient l'épaisseur cumulée et dont la longueur couvre
     * épaisseur + pénétration.
     */
    private function vis(int $epaisseur, int $penetration): ?MaterielLisible
    {
        $specification = MaterielSpecification::creer('vis', $this->categories->get('visserie'), [
            new Critere('usage', Operateur::Egal, 'plaque_sur_metal'),
        ]);
        $retenue = null;
        $longueurRetenue = 0;
        foreach ($this->source->candidats($specification) as $vis) {
            $longueur = $vis->valeur('longueur');
            if (!\is_int($longueur) || $longueur < $epaisseur + $penetration) {
                continue;
            }
            $plage = (new VisserieView($vis))->fixable();
            if (!$plage->contient($epaisseur)) {
                continue;
            }
            if (null === $retenue || $longueur < $longueurRetenue) {
                $retenue = $vis;
                $longueurRetenue = $longueur;
            }
        }

        return $retenue;
    }
}
