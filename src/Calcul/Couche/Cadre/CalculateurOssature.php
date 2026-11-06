<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Cadre;

use App\Calcul\Cadre\PlanOssature;
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
use App\Catalogue\Vue\Orientation;
use App\Catalogue\Vue\ProfilView;
use App\Catalogue\Vue\Renfort;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;

/**
 * Ossature d'une cloison ou d'un doublage. Le matériau de la couche est le montant ; le rail (ou la lisse, selon
 * le paramètre `type_profil_peripherique`) est la famille correspondante de la même série. L'entraxe et le renfort
 * viennent de la table de portées du montant pour la hauteur maximale de la surface.
 */
final readonly class CalculateurOssature implements CalculateurCouche
{
    public function __construct(private SourceCandidats $source, private RegistreCategories $categories)
    {
    }

    public function code(): string
    {
        return 'ossature.cadre';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $montant = $couche->materiau ?? throw new \LogicException('The framing layer has no material.');
        $profil = new ProfilView($montant);
        $surface = $couche->surface;
        $avertissements = [];

        [$entraxe, $renfort] = $this->portee($profil, $surface->boite()->hauteur(), $avertissements);

        $plan = PlanOssature::calculer(
            $surface,
            $entraxe,
            $this->texte($couche, 'extremite_debut', 'mur'),
            $this->texte($couche, 'extremite_fin', 'mur'),
            max(0, $couche->parametreEntier('montants_raccordement', 1)),
            $this->positions($this->texte($couche, 'positions_raccordement', '')),
        );
        array_push($avertissements, ...$plan->avertissements);

        $famille = 'lisse' === $this->texte($couche, 'type_profil_peripherique', 'rail') ? 'lisse' : 'rail';
        $rail = $this->rail($famille, $profil->profondeur());
        if (null === $rail) {
            $avertissements[] = new Avertissement('framing_rail_missing', ['family' => $famille, 'series' => $profil->profondeur()]);
        }

        $double = Renfort::Simple !== $renfort;
        $montants = $double ? [...$plan->montants, ...$plan->montants] : $plan->montants;
        $e = $couche->emplacement->code;
        $direction = Direction::Verticale;
        $lignes = [
            LigneBesoin::pieces('rail_bas', $e, 'profil_ossature', $plan->railsBas, Direction::Horizontale, ['profondeur_mm' => $profil->profondeur()], $rail),
            LigneBesoin::pieces('rail_haut', $e, 'profil_ossature', $plan->railsHaut, Direction::Horizontale, ['profondeur_mm' => $profil->profondeur()], $rail),
        ];
        if ([] !== $plan->railsOuvertures) {
            $lignes[] = LigneBesoin::pieces('rails_ouverture', $e, 'profil_ossature', $plan->railsOuvertures, Direction::Horizontale, ['profondeur_mm' => $profil->profondeur()], $rail);
        }
        $lignes[] = LigneBesoin::pieces('montants', $e, 'profil_ossature', $montants, $direction, ['profondeur_mm' => $profil->profondeur(), 'renfort_double' => $double ? 1 : 0], $montant);
        if ([] !== $plan->raccordements) {
            $lignes[] = LigneBesoin::pieces('montants_raccordement', $e, 'profil_ossature', $plan->raccordements, $direction, ['profondeur_mm' => $profil->profondeur()], $montant);
        }
        if ($profil->profondeur() < $contexte->profondeurCavite) {
            $avertissements[] = new Avertissement('insulation_deeper_than_framing', ['cavity' => $contexte->profondeurCavite, 'profile' => $profil->profondeur()]);
        }

        return new ResultatCouche($lignes, $contexte->avecEntraxe($entraxe), $avertissements);
    }

    /**
     * Première portée (renfort simple d'abord, puis double) dont la longueur maximale couvre la hauteur ; à défaut
     * l'entraxe par défaut du profil avec un avertissement.
     *
     * @param list<Avertissement> $avertissements
     *
     * @return array{int, Renfort}
     */
    private function portee(ProfilView $profil, int $hauteur, array &$avertissements): array
    {
        $portees = $profil->portees(Orientation::Verticale);
        if ([] === $portees) {
            return [$profil->entraxeDefaut(), Renfort::Simple];
        }
        foreach ([Renfort::Simple, Renfort::DoubleAccole, Renfort::DoubleDecale] as $renfort) {
            $retenue = null;
            foreach ($portees as $p) {
                if ($p->renfort === $renfort && $p->longueurMax >= $hauteur && (null === $retenue || $p->entraxe > $retenue)) {
                    $retenue = $p->entraxe;
                }
            }
            if (null !== $retenue) {
                return [$retenue, $renfort];
            }
        }
        $avertissements[] = new Avertissement('framing_height_exceeded', ['height' => $hauteur, 'profile' => $profil->profondeur()]);

        return [$profil->entraxeDefaut(), Renfort::Simple];
    }

    private function rail(string $famille, int $serie): ?MaterielLisible
    {
        $specification = MaterielSpecification::creer('ossature', $this->categories->get('profil_ossature'), [
            new Critere('famille', Operateur::Egal, $famille),
            new Critere('serie', Operateur::Egal, $serie),
        ]);

        return $this->source->candidats($specification)[0] ?? null;
    }

    private function texte(CoucheContexte $couche, string $nom, string $defaut): string
    {
        $v = $couche->parametres[$nom] ?? null;

        return \is_string($v) && '' !== $v ? $v : $defaut;
    }

    /**
     * @return list<int>
     */
    private function positions(string $liste): array
    {
        $positions = [];
        foreach (explode(',', $liste) as $p) {
            $p = trim($p);
            if ('' !== $p && ctype_digit($p)) {
                $positions[] = (int) $p;
            }
        }

        return $positions;
    }
}
