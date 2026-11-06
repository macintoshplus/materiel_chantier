<?php
declare(strict_types=1);

namespace App\Model\Chantier;


final class PlaquePlatre
{
    public const string TYPE_JOINT_AUCUN = 'aucun';
    public const string TYPE_JOINT_ENTRANT = 'entrant';
    public const string TYPE_JOINT_SORTANT = 'sortant';

    public function __construct(
        public readonly string $id,
        public int $x,
        public int $y,
        public readonly int $longeur,
        public readonly int $largeur,
        public readonly string $typeJointGauche,
        public readonly string $typeJointDroit,
        public readonly string $typeJointHaut,
        public readonly string $typeJointBas,
        //Si null = toute la surface est utilisé
        private readonly ?int $surfaceUtilisee = null,
        //liste des points représentant la plaque, origine en bas à gauche
        /** @var list<array{x: int, y: int}> */
        private readonly array $polygonePoints = [],
    ) {
    }

    public function getSurface(): int
    {
        return $this->largeur * $this->longeur;
    }

    public function getSurfaceUtilisee(): int
    {
        return $this->surfaceUtilisee ?? $this->getSurface();
    }

    /** @return list<array{x: int, y: int}> */
    public function getPolygonePoints(): array
    {
        if (\count($this->polygonePoints) === 0) {
            return [
                ['x' => 0, 'y' => 0],
                ['x' => 0, 'y' => $this->longeur],
                ['x' => $this->largeur, 'y' => $this->longeur],
                ['x' => $this->largeur, 'y' => 0],
            ];
        }
        return $this->polygonePoints;
    }
}
