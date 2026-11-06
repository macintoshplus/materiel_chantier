<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class HauteurPlate implements HauteurPlafond
{
    public function __construct(private int $hauteur, private ?string $cle = null)
    {
    }

    public function hauteurA(Point2D $point): int
    {
        return $this->hauteur;
    }

    public function ruptures(Segment $segment): array
    {
        return [];
    }

    public function cleA(Point2D $point): ?string
    {
        return $this->cle;
    }
}
