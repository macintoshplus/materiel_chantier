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
        public float $x,
        public float $y,
        public readonly float $longeur,
        public readonly float $largeur,
        public readonly string $typeJointGauche,
        public readonly string $typeJointDroit,
        public readonly string $typeJointHaut,
        public readonly string $typeJointBas,
        //Si null = toute la surface est utilisé
        private readonly ?float $surfaceUtilisee = null,
        //liste des points représentant la plaque, origine en bas à gauche
        /** @var list<array{x: float, y: float}> */
        private readonly array $polygonePoints = [],
    ) {
    }

    public function getSurface(): float
    {
        return $this->largeur * $this->longeur;
    }

    public function getSurfaceUtilisee(): float
    {
        return $this->surfaceUtilisee ?? $this->getSurface();
    }

    /** @return list<array{x: float, y: float}> */
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
