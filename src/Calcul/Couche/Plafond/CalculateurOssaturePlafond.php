<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Plafond;

use App\Calcul\Cadre\FourruresPlafond;
use App\Calcul\Cadre\PlanificateurJoints;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Calcul\Materiau\SourceCandidats;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
use App\Geometrie\Avertissement;

/**
 * Ossature d'un plafond suspendu : lisse sur la périphérie du pan (longueur réelle des arêtes, sans les arêtes
 * ouvertes du paramètre `aretes_ouvertes`, sans les ouvertures de trémie) et fourrures à l'entraxe retenu par
 * la planification, aboutées avec des éclisses si elles dépassent la longueur de la barre.
 */
final readonly class CalculateurOssaturePlafond implements CalculateurCouche
{
    public const int ENTRAXE_DEFAUT = 600;

    public function __construct(private SourceCandidats $source, private RegistreCategories $categories)
    {
    }

    public function code(): string
    {
        return 'ossature.plafond';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $fourrure = $couche->materiau ?? throw new \LogicException('The ceiling framing layer has no material.');
        $surface = $couche->surface;
        $avertissements = [];
        $e = $couche->emplacement->code;

        $lisse = $this->lisse();
        if (null === $lisse) {
            $avertissements[] = new Avertissement('ceiling_perimeter_profile_missing');
        }
        $ouvertes = $this->aretesOuvertes($couche->parametres['aretes_ouvertes'] ?? '');
        $perimetre = [];
        foreach ($surface->geometrie->contour->aretes() as [$a, $b]) {
            if (isset($ouvertes[$a.':'.$b]) || isset($ouvertes[$b.':'.$a])) {
                continue;
            }
            $perimetre[] = $surface->geometrie->longueurAreteReelle($a, $b);
        }

        $barre = $fourrure->valeur('longueur');
        $pieces = [];
        $eclisses = 0;
        foreach (FourruresPlafond::longueurs($surface, $contexte->directionOssature, $contexte->entraxeRetenu ?? self::ENTRAXE_DEFAUT) as $longueur) {
            if (!\is_int($barre) || $barre <= 0 || $longueur <= $barre) {
                $pieces[] = $longueur;

                continue;
            }
            $joints = [];
            for ($p = $barre; $p < $longueur; $p += $barre) {
                $joints[] = $p;
            }
            array_push($pieces, ...PlanificateurJoints::segments($longueur, $joints));
            $eclisses += \count($joints);
        }

        $lignes = [
            LigneBesoin::pieces('lisses_peripheriques', $e, 'profil_ossature', $perimetre, null, [], $lisse),
            LigneBesoin::pieces('fourrures', $e, 'profil_ossature', $pieces, $contexte->directionOssature, [], $fourrure),
        ];
        if ($eclisses > 0) {
            $lignes[] = LigneBesoin::nombre('eclisses_fourrures', $e, 'accessoire_ossature', $eclisses);
        }

        return new ResultatCouche($lignes, $contexte, $avertissements);
    }

    private function lisse(): ?\App\Catalogue\MaterielLisible
    {
        $specification = MaterielSpecification::creer('lisse', $this->categories->get('profil_ossature'), [
            new Critere('famille', Operateur::Egal, 'lisse'),
        ]);

        return $this->source->candidats($specification)[0] ?? null;
    }

    /**
     * @param scalar|null $liste arêtes `a:b` séparées par des virgules
     *
     * @return array<string, true>
     */
    private function aretesOuvertes(mixed $liste): array
    {
        $ouvertes = [];
        foreach (explode(',', \is_string($liste) ? $liste : '') as $arete) {
            $arete = trim($arete);
            if ('' !== $arete) {
                $ouvertes[$arete] = true;
            }
        }

        return $ouvertes;
    }
}
