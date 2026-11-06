<?php

declare(strict_types=1);

namespace App\Chiffrage\Strategie;

use App\Catalogue\DistanceCalculator;
use App\Chiffrage\Contexte;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\SourcePrix;
use App\Chiffrage\StrategieFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;

/**
 * Le fournisseur le plus proche du chantier qui vend l'article, repli sur le suivant. Sans position de chantier, ou
 * pour les fournisseurs sans position (placés en dernier), l'ordre est alphabétique.
 */
final readonly class PlusProche implements StrategieFournisseur
{
    use ChoixParArticle;

    public function __construct(private SourcePrix $source, private DistanceCalculator $distance = new DistanceCalculator())
    {
    }

    public function choisir(Materiau $materiau, QuantiteBrute $quantite, \DateTimeImmutable $date, Contexte $contexte): ?PrixMateriau
    {
        foreach ($this->classement($contexte) as $fournisseur) {
            $prix = $this->source->prix($materiau, $fournisseur, $date);
            if (null !== $prix) {
                return $prix;
            }
        }

        return null;
    }

    /**
     * @return list<Fournisseur>
     */
    public function classement(Contexte $contexte): array
    {
        $cles = [];
        foreach ($this->source->fournisseurs() as $i => $fournisseur) {
            $km = null;
            if ($contexte->aUnePosition() && $fournisseur->aUnePosition()) {
                $km = $this->distance->distanceKm((float) $contexte->latitude, (float) $contexte->longitude, (float) $fournisseur->getLatitude(), (float) $fournisseur->getLongitude());
            }
            $cles[] = [null === $km ? 1 : 0, $km ?? 0.0, $fournisseur->getNom(), $i, $fournisseur];
        }
        usort($cles, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2], $a[3]] <=> [$b[0], $b[1], $b[2], $b[3]]);

        return array_map(static fn (array $c): Fournisseur => $c[4], $cles);
    }
}
