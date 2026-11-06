<?php

declare(strict_types=1);

namespace App\Calcul\Surface;

use App\Enum\TypeSurface;
use App\Geometrie\BoiteEnglobante;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\Direction;
use App\Geometrie\Plan;
use App\Geometrie\Polygone;
use App\Geometrie\Segment;
use App\Geometrie\SurfaceGeometrique;

/**
 * Ce que les calculateurs voient d'une surface : polygone, ouvertures et repère, en mm. Sans Doctrine ni Symfony.
 */
final readonly class SurfaceCalculable
{
    public SurfaceGeometrique $geometrie;

    /**
     * @param list<OuvertureCalculable> $ouvertures
     */
    public function __construct(
        public TypeSurface $type,
        Plan $plan,
        ContourIdentifie $contour,
        public array $ouvertures = [],
    ) {
        $this->geometrie = new SurfaceGeometrique($plan, $contour, array_map(static fn (OuvertureCalculable $o): Polygone => $o->forme, $ouvertures));
    }

    public function polygone(): Polygone
    {
        return $this->geometrie->polygone();
    }

    public function boite(): BoiteEnglobante
    {
        return $this->polygone()->boiteEnglobante();
    }

    /** Surface brute (mm²). */
    public function surface(): int
    {
        return $this->polygone()->aire();
    }

    public function surfaceOuvertures(): int
    {
        $total = 0;
        foreach ($this->ouvertures as $ouverture) {
            $total += $ouverture->forme->aire();
        }

        return $total;
    }

    /** Surface nette : surface brute moins les ouvertures (mm²). */
    public function surfaceNette(): int
    {
        return $this->surface() - $this->surfaceOuvertures();
    }

    /**
     * @return list<Segment>
     */
    public function coupes(Direction $direction, int $pas): array
    {
        return $this->geometrie->coupes($direction, $pas);
    }
}
