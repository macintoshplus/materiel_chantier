<?php

declare(strict_types=1);

namespace App\Import\Dxf;

/**
 * Définition de bloc (section BLOCKS) : nom, point de base et entités.
 */
final class DxfBloc
{
    /** @var list<DxfEntite> */
    private array $entites = [];

    public function __construct(public readonly string $nom, public readonly float $baseX = 0.0, public readonly float $baseY = 0.0)
    {
    }

    public function ajouter(DxfEntite $entite): void
    {
        $this->entites[] = $entite;
    }

    /**
     * @return list<DxfEntite>
     */
    public function entites(): array
    {
        return $this->entites;
    }
}
