<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Calcul\Calepinage\Calepinage;
use App\Catalogue\Vue\Renfort;
use App\Geometrie\Direction;

/**
 * Contexte immuable transmis de couche en couche (remplacé à chaque étape). Il porte d'une part les valeurs
 * **prévues** par la phase de planification (cavité, épaisseur isolée et de parement totales, paramètres de pose
 * retenus) et d'autre part les valeurs **cumulées** au fil du calcul. Valeurs entières : mm, et millièmes de
 * m².K/W pour le R. Les paramètres de pose (`pasSuspente`, `pasVisParPeau`, `renfort`, `penetrationVis`) restent
 * à leur valeur neutre pour un système qui ne les planifie pas.
 */
final readonly class ContexteCouches
{
    /**
     * @param array<int, int> $pasVisParPeau pas de vis (mm) par ordre de couche de parement
     */
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
        public ?int $pasSuspente = null,
        public array $pasVisParPeau = [],
        public ?Renfort $renfort = null,
        public ?int $penetrationVis = null,
    ) {
    }

    public function avecPlanification(int $profondeurCavite, int $epaisseurIsoleePrevue, int $epaisseurParementPrevue): self
    {
        return $this->copie(profondeurCavite: $profondeurCavite, epaisseurIsoleePrevue: $epaisseurIsoleePrevue, epaisseurParementPrevue: $epaisseurParementPrevue);
    }

    public function avecEpaisseurCumulee(int $epaisseurCumulee): self
    {
        return $this->copie(epaisseurCumulee: $epaisseurCumulee);
    }

    public function avecEpaisseurParementCumulee(int $epaisseurParementCumulee): self
    {
        return $this->copie(epaisseurParementCumulee: $epaisseurParementCumulee);
    }

    public function avecRCumuleMilli(int $rCumuleMilli): self
    {
        return $this->copie(rCumuleMilli: $rCumuleMilli);
    }

    public function avecCalepinage(Calepinage $calepinage): self
    {
        return $this->copie(calepinagePrecedent: $calepinage);
    }

    public function avecEntraxe(int $entraxe): self
    {
        return $this->copie(entraxeRetenu: $entraxe);
    }

    public function avecRenfort(Renfort $renfort): self
    {
        return $this->copie(renfort: $renfort);
    }

    /**
     * Paramètres de pose retenus par la planification.
     *
     * @param array<int, int> $pasVisParPeau
     */
    public function avecPose(?int $entraxe, ?int $pasSuspente, array $pasVisParPeau, ?int $penetrationVis): self
    {
        return $this->copie(entraxeRetenu: $entraxe ?? $this->entraxeRetenu, pasSuspente: $pasSuspente, pasVisParPeau: $pasVisParPeau, penetrationVis: $penetrationVis);
    }

    /**
     * @param array<int, int>|null $pasVisParPeau
     */
    private function copie(
        ?int $epaisseurCumulee = null,
        ?int $profondeurCavite = null,
        ?int $epaisseurIsoleePrevue = null,
        ?int $epaisseurParementPrevue = null,
        ?int $epaisseurParementCumulee = null,
        ?int $rCumuleMilli = null,
        ?Calepinage $calepinagePrecedent = null,
        ?int $entraxeRetenu = null,
        ?int $pasSuspente = null,
        ?array $pasVisParPeau = null,
        ?Renfort $renfort = null,
        ?int $penetrationVis = null,
    ): self {
        return new self(
            $this->directionOssature,
            $epaisseurCumulee ?? $this->epaisseurCumulee,
            $profondeurCavite ?? $this->profondeurCavite,
            $epaisseurIsoleePrevue ?? $this->epaisseurIsoleePrevue,
            $epaisseurParementPrevue ?? $this->epaisseurParementPrevue,
            $epaisseurParementCumulee ?? $this->epaisseurParementCumulee,
            $rCumuleMilli ?? $this->rCumuleMilli,
            $calepinagePrecedent ?? $this->calepinagePrecedent,
            $entraxeRetenu ?? $this->entraxeRetenu,
            $pasSuspente ?? $this->pasSuspente,
            $pasVisParPeau ?? $this->pasVisParPeau,
            $renfort ?? $this->renfort,
            $penetrationVis ?? $this->penetrationVis,
        );
    }
}
