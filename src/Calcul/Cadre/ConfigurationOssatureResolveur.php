<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Calcul\Pose\OrientationPose;
use App\Catalogue\Vue\Portee;
use App\Catalogue\Vue\ProfilView;
use App\Catalogue\Vue\Renfort;
use App\Geometrie\Avertissement;

/**
 * Phase de planification de l'ossature : liste les configurations `(entraxe, renfort)` de la table du fabricant
 * qui couvrent la longueur du montant final dans l'orientation de la surface, applique une stratégie de choix et
 * vérifie les positions non renforcées. Pur : sans base ni calcul de prix (le coût est fourni par l'appelant).
 */
final class ConfigurationOssatureResolveur
{
    private const array RANG_RENFORT = [Renfort::Simple->value => 0, Renfort::DoubleAccole->value => 1, Renfort::DoubleDecale->value => 2];

    /**
     * Configurations possibles, de la plus simple à la plus renforcée puis de l'entraxe le plus large au plus serré.
     *
     * @return list<ConfigurationOssature>
     */
    public function candidates(ProfilView $profil, OrientationPose $orientation, int $longueur, int $entraxeMax = \PHP_INT_MAX): array
    {
        $candidates = [];
        foreach ($this->lignes($profil, $orientation) as $ligne) {
            if ($ligne->longueurMax >= $longueur && $ligne->entraxe <= $entraxeMax) {
                $candidates[] = new ConfigurationOssature($profil->profondeur(), $ligne->entraxe, $ligne->renfort, portee: $ligne);
            }
        }
        usort($candidates, static fn (ConfigurationOssature $a, ConfigurationOssature $b): int => [self::RANG_RENFORT[$a->renfort->value], -$a->entraxe] <=> [self::RANG_RENFORT[$b->renfort->value], -$b->entraxe]);

        return $candidates;
    }

    /**
     * @param \Closure(ConfigurationOssature): ?int|null $cout coût de la configuration (stratégie économe en coût) ; repli sur le matériel si null
     */
    public function resoudre(
        ProfilView $profil,
        OrientationPose $orientation,
        int $longueur,
        int $largeur,
        int $entraxeMax = \PHP_INT_MAX,
        StrategieConfiguration $strategie = StrategieConfiguration::EconomeMateriel,
        ?ConfigurationOssature $imposee = null,
        ?\Closure $cout = null,
    ): ResolutionOssature {
        $candidates = $this->candidates($profil, $orientation, $longueur, $entraxeMax);
        $avertissements = [];
        if ($orientation->intermediaire) {
            $avertissements[] = new Avertissement('framing_orientation_intermediate', ['orientation' => $this->lignesOrientation($orientation)]);
        }

        if (StrategieConfiguration::Imposee === $strategie && null !== $imposee) {
            return $this->imposee($profil, $orientation, $imposee, $candidates, $avertissements);
        }
        if ([] === $candidates) {
            return new ResolutionOssature(null, [], $avertissements, new Avertissement('framing_height_exceeded', ['height' => $longueur, 'profile' => $profil->profondeur()]));
        }

        $retenue = $candidates[0];
        $meilleur = null;
        foreach ($candidates as $candidate) {
            $mesure = $this->mesure($candidate, $longueur, $largeur, StrategieConfiguration::EconomeCout === $strategie ? $cout : null);
            if (null === $meilleur || $mesure < $meilleur) {
                $meilleur = $mesure;
                $retenue = $candidate;
            }
        }

        return new ResolutionOssature($retenue, $candidates, $avertissements);
    }

    /**
     * Vérifie chaque montant non renforcé contre la ligne « simple » de la table.
     *
     * @param list<int> $longueursNonRenforcees
     */
    public function verifierNonRenforces(ProfilView $profil, OrientationPose $orientation, ConfigurationOssature $configuration, array $longueursNonRenforcees): ?Avertissement
    {
        $maxSimple = null;
        foreach ($this->lignes($profil, $orientation) as $ligne) {
            if (Renfort::Simple === $ligne->renfort && $ligne->entraxe >= $configuration->entraxe) {
                $maxSimple = max($maxSimple ?? 0, $ligne->longueurMax);
            }
        }
        if (null === $maxSimple) {
            return null;
        }
        $plus = [] === $longueursNonRenforcees ? 0 : max($longueursNonRenforcees);
        if ($plus > $maxSimple) {
            return new Avertissement('framing_unreinforced_stud_too_long', ['length' => $plus, 'maximum' => $maxSimple]);
        }

        return null;
    }

    /**
     * Lignes de la table pour l'orientation ; pour une orientation intermédiaire, la plus défavorable
     * (longueur maximale la plus faible) par couple `(renfort, entraxe)`.
     *
     * @return list<Portee>
     */
    public function lignes(ProfilView $profil, OrientationPose $orientation): array
    {
        if (!$orientation->intermediaire) {
            return $profil->portees($orientation->orientation);
        }
        $fusion = [];
        foreach ($profil->portees() as $ligne) {
            $cle = $ligne->renfort->value.'|'.$ligne->entraxe;
            if (!isset($fusion[$cle]) || $ligne->longueurMax < $fusion[$cle]->longueurMax) {
                $fusion[$cle] = $ligne;
            }
        }

        return array_values($fusion);
    }

    private function lignesOrientation(OrientationPose $orientation): string
    {
        return $orientation->intermediaire ? 'intermediaire' : $orientation->orientation->value;
    }

    /**
     * @param list<ConfigurationOssature> $candidates
     * @param list<Avertissement>         $avertissements
     */
    private function imposee(ProfilView $profil, OrientationPose $orientation, ConfigurationOssature $imposee, array $candidates, array $avertissements): ResolutionOssature
    {
        foreach ($this->lignes($profil, $orientation) as $ligne) {
            if ($ligne->renfort === $imposee->renfort && $ligne->entraxe === $imposee->entraxe) {
                $configuration = new ConfigurationOssature($imposee->serie, $imposee->entraxe, $imposee->renfort, $imposee->zone, $imposee->positions, $ligne);

                return new ResolutionOssature($configuration, $candidates, $avertissements);
            }
        }
        $avertissements[] = new Avertissement('framing_configuration_off_table', ['renfort' => $imposee->renfort->value, 'spacing' => $imposee->entraxe]);

        return new ResolutionOssature($imposee, $candidates, $avertissements);
    }

    /**
     * @param \Closure(ConfigurationOssature): ?int|null $cout
     *
     * @return array{int, int, int}
     */
    private function mesure(ConfigurationOssature $c, int $longueur, int $largeur, ?\Closure $cout): array
    {
        $lignes = Renfort::Simple === $c->renfort ? 1 : 2;
        $profile = ((int) ceil($largeur / $c->entraxe) + 1) * $longueur * $lignes;
        $prix = null === $cout ? null : $cout($c);

        return [$prix ?? $profile, self::RANG_RENFORT[$c->renfort->value], -$c->entraxe];
    }
}
