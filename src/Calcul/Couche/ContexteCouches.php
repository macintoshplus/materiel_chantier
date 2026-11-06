<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Calcul\Calepinage\Calepinage;
use App\Geometrie\Direction;

/**
 * Contexte immuable transmis de couche en couche (remplacé à chaque étape). Il porte d'une part les valeurs
 * **prévues** par la phase de planification (cavité, épaisseur isolée et de parement totales) et d'autre part
 * les valeurs **cumulées** au fil du calcul. Valeurs entières : mm, et millièmes de m².K/W pour le R.
 */
final readonly class ContexteCouches
{
    public function __construct(
        public Direction $directionOssature = Direction::Verticale,
        public int $epaisseurCumulee = 0,
        public int $profondeurCavite = 0,
        public int $epaisseurIsoleePrevue = 0,
        public int $epaisseurParementPrevue = 0,
        public int $epaisseurParementCumulee = 0,
        public int $rCumuleMilli = 0,
        public ?Calepinage $calepinagePrecedent = null,
        public ?int $entraxeRetenu = null,
    ) {
    }

    public function avecPlanification(int $profondeurCavite, int $epaisseurIsoleePrevue, int $epaisseurParementPrevue): self
    {
        return new self($this->directionOssature, $this->epaisseurCumulee, $profondeurCavite, $epaisseurIsoleePrevue, $epaisseurParementPrevue, $this->epaisseurParementCumulee, $this->rCumuleMilli, $this->calepinagePrecedent, $this->entraxeRetenu);
    }

    public function avecEpaisseurCumulee(int $epaisseurCumulee): self
    {
        return new self($this->directionOssature, $epaisseurCumulee, $this->profondeurCavite, $this->epaisseurIsoleePrevue, $this->epaisseurParementPrevue, $this->epaisseurParementCumulee, $this->rCumuleMilli, $this->calepinagePrecedent, $this->entraxeRetenu);
    }

    public function avecEpaisseurParementCumulee(int $epaisseurParementCumulee): self
    {
        return new self($this->directionOssature, $this->epaisseurCumulee, $this->profondeurCavite, $this->epaisseurIsoleePrevue, $this->epaisseurParementPrevue, $epaisseurParementCumulee, $this->rCumuleMilli, $this->calepinagePrecedent, $this->entraxeRetenu);
    }

    public function avecRCumuleMilli(int $rCumuleMilli): self
    {
        return new self($this->directionOssature, $this->epaisseurCumulee, $this->profondeurCavite, $this->epaisseurIsoleePrevue, $this->epaisseurParementPrevue, $this->epaisseurParementCumulee, $rCumuleMilli, $this->calepinagePrecedent, $this->entraxeRetenu);
    }

    public function avecCalepinage(Calepinage $calepinage): self
    {
        return new self($this->directionOssature, $this->epaisseurCumulee, $this->profondeurCavite, $this->epaisseurIsoleePrevue, $this->epaisseurParementPrevue, $this->epaisseurParementCumulee, $this->rCumuleMilli, $calepinage, $this->entraxeRetenu);
    }

    public function avecEntraxe(int $entraxe): self
    {
        return new self($this->directionOssature, $this->epaisseurCumulee, $this->profondeurCavite, $this->epaisseurIsoleePrevue, $this->epaisseurParementPrevue, $this->epaisseurParementCumulee, $this->rCumuleMilli, $this->calepinagePrecedent, $entraxe);
    }
}
