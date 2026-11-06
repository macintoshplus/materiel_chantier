<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class PlafondGenere
{
    /**
     * @param list<SurfaceGeneree> $pans
     * @param list<SurfaceGeneree> $pignons murs de remplacement (pentagones), identifiés par `areteSol`
     */
    public function __construct(public array $pans, public array $pignons = [])
    {
    }
}
