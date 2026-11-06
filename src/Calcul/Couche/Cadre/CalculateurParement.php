<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Cadre;

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
 */
final readonly class CalculateurParement implements CalculateurCouche
{
    private const int PENETRATION = 10;

    public function __construct(
        private CalepinagePlaques $calepinage,
        private SourceCandidats $source,
        private RegistreCategories $categories,
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

        $penetration = $contexte->penetrationVis ?? self::PENETRATION;
        $visserie = null;
        if (null !== $contexte->penetrationVis) {
            $visserie = $this->vis($cumule, $penetration);
            if (null === $visserie) {
                $avertissements[] = new Avertissement('screw_no_candidate', ['thickness' => $cumule, 'penetration' => $penetration]);
            }
        }
        $longueurVis = $visserie?->valeur('longueur');

        return new ResultatCouche([
            LigneBesoin::nombre('plaques_'.$couche->couche->ordre, $couche->emplacement->code, 'plaque_platre', \count($plan->plaques), ['epaisseur_mm' => $plaque->epaisseur()], $materiau),
            LigneBesoin::nombre('vis_'.$couche->couche->ordre, $couche->emplacement->code, 'visserie', $vis, ['longueur_mm' => \is_int($longueurVis) ? $longueurVis : $cumule + $penetration], $visserie),
        ], $contexte->avecCalepinage($plan)->avecEpaisseurParementCumulee($cumule), $avertissements);
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
