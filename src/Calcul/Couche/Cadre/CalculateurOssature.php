<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Cadre;

use App\Calcul\Cadre\ConfigurationOssature;
use App\Calcul\Cadre\ConfigurationOssatureResolveur;
use App\Calcul\Cadre\MontantComposite;
use App\Calcul\Cadre\PlanOssature;
use App\Calcul\Cadre\ResolutionOssature;
use App\Calcul\Cadre\StrategieConfiguration;
use App\Calcul\Cadre\ZoneRenfort;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Calcul\Materiau\SourceCandidats;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Pose\OrientationPose;
use App\Catalogue\Critere;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
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
    public function __construct(
        private SourceCandidats $source,
        private RegistreCategories $categories,
        private ConfigurationOssatureResolveur $resolveur = new ConfigurationOssatureResolveur(),
    ) {
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

        $orientation = OrientationPose::deduire($surface->geometrie->plan, Direction::Verticale);
        $resolution = $this->configuration($couche, $profil, $orientation, $surface->boite()->hauteur(), $surface->boite()->largeur());
        array_push($avertissements, ...$resolution->avertissements);
        if (null !== $resolution->erreur || null === $resolution->configuration) {
            return new ResultatCouche([], $contexte, $avertissements, [$resolution->erreur ?? new Avertissement('framing_height_exceeded', ['height' => $surface->boite()->hauteur(), 'profile' => $profil->profondeur()])]);
        }
        $configuration = $resolution->configuration;
        $entraxe = $configuration->entraxe;
        $renfort = $configuration->renfort;

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

        [$simples, $doubles] = $this->repartir($plan, $configuration);
        if (Renfort::Simple !== $renfort && ZoneRenfort::ToutesPositions !== $configuration->zone) {
            $erreur = $this->resolveur->verifierNonRenforces($profil, $orientation, $configuration, $simples);
            if (null !== $erreur) {
                return new ResultatCouche([], $contexte, $avertissements, [$erreur]);
            }
        }
        $longueurBarre = $montant->valeur('longueur');
        $composites = [];
        foreach ($doubles as $longueur) {
            $composites[] = MontantComposite::creer(
                $longueur,
                $renfort,
                \is_int($longueurBarre) && $longueurBarre > 0 ? $longueurBarre : $longueur,
                $configuration->portee->decalageJointsMinMm ?? 0,
            );
        }
        $montants = $simples;
        foreach ($composites as $composite) {
            array_push($montants, ...$composite->segments());
        }
        $double = [] !== $composites;

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
        array_push($lignes, ...$this->accessoires($e, $configuration, $composites));
        if ($profil->profondeur() < $contexte->profondeurCavite) {
            $avertissements[] = new Avertissement('insulation_deeper_than_framing', ['cavity' => $contexte->profondeurCavite, 'profile' => $profil->profondeur()]);
        }

        return new ResultatCouche($lignes, $contexte->avecEntraxe($entraxe)->avecRenfort($renfort)->avecOssature($profil->profondeur(), [...$plan->railsBas, ...$plan->railsHaut]), $avertissements);
    }

    /**
     * Configuration retenue : imposée par le paramètre `renfort` (zone `renfort_zone`), sinon choisie par le résolveur
     * dans la table du profilé. Sans table, l'entraxe par défaut du profil et un montant simple.
     */
    private function configuration(CoucheContexte $couche, ProfilView $profil, OrientationPose $orientation, int $hauteur, int $largeur): ResolutionOssature
    {
        $imposee = Renfort::tryFrom($this->texte($couche, 'renfort', ''));
        $zone = ZoneRenfort::tryFrom($this->texte($couche, 'renfort_zone', '')) ?? ZoneRenfort::ToutesPositions;
        if (null !== $imposee) {
            $entraxe = null;
            foreach ($this->resolveur->lignes($profil, $orientation) as $ligne) {
                if ($ligne->renfort === $imposee && (null === $entraxe || $ligne->entraxe > $entraxe)) {
                    $entraxe = $ligne->entraxe;
                }
            }
            $configuration = new ConfigurationOssature($profil->profondeur(), $entraxe ?? $profil->entraxeDefaut(), $imposee, $zone);

            return $this->resolveur->resoudre($profil, $orientation, $hauteur, $largeur, strategie: StrategieConfiguration::Imposee, imposee: $configuration);
        }
        if ([] === $profil->portees()) {
            return new ResolutionOssature(new ConfigurationOssature($profil->profondeur(), $profil->entraxeDefaut(), Renfort::Simple), []);
        }

        return $this->resolveur->resoudre($profil, $orientation, $hauteur, $largeur);
    }

    /**
     * Montants simples et montants doublés selon la zone du renfort.
     *
     * @return array{list<int>, list<int>}
     */
    private function repartir(PlanOssature $plan, ConfigurationOssature $configuration): array
    {
        if (Renfort::Simple === $configuration->renfort) {
            return [$plan->montants, []];
        }
        if (ZoneRenfort::BordsOuvertures !== $configuration->zone) {
            return [[], $plan->montants];
        }
        $simples = $plan->montants;
        foreach ($plan->montantsBords as $bord) {
            $cle = array_search($bord, $simples, true);
            if (false !== $cle) {
                unset($simples[$cle]);
            }
        }

        return [array_values($simples), $plan->montantsBords];
    }

    /**
     * Éclisses (une règle par joint) et vis d'assemblage (au pas du fabricant) des montants composites.
     *
     * @param list<MontantComposite> $composites
     *
     * @return list<LigneBesoin>
     */
    private function accessoires(string $emplacement, ConfigurationOssature $configuration, array $composites): array
    {
        $portee = $configuration->portee;
        if (null === $portee || [] === $composites) {
            return [];
        }
        $lignes = [];
        $joints = array_sum(array_map(static fn (MontantComposite $c): int => $c->nombreJoints(), $composites));
        foreach ($portee->accessoires as $accessoire) {
            if (null !== $accessoire->parJoint && $joints > 0) {
                $lignes[] = LigneBesoin::nombre('eclisses', $emplacement, 'accessoire_ossature', $joints * $accessoire->parJoint, [], $this->accessoire($accessoire->specification));
            }
        }
        if (null !== $portee->pasVisAssemblageMm) {
            $vis = 0;
            foreach ($composites as $composite) {
                $vis += (int) ceil($composite->longueur / $portee->pasVisAssemblageMm);
            }
            $lignes[] = LigneBesoin::nombre('vis_assemblage', $emplacement, 'visserie', $vis);
        }

        return $lignes;
    }

    /**
     * Article de l'accessoire quand sa spécification est une spécification sérialisée (JSON) ; sinon null.
     */
    private function accessoire(string $specification): ?MaterielLisible
    {
        $donnees = json_decode($specification, true);
        if (!\is_array($donnees)) {
            return null;
        }
        try {
            return $this->source->candidats((new SpecificationSerialiseur($this->categories))->depuis($donnees))[0] ?? null;
        } catch (\InvalidArgumentException) {
            return null;
        }
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
