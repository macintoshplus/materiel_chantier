<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Choix de l'utilisateur à la fin de l'assistant : contour retenu (index dans `PlanImporte::contours`), mode et
 * épaisseur de mur, hauteur sous plafond, rôles des calques et rôles imposés à certains blocs (index dans
 * `PlanImporte::blocs`). Identique quelle que soit la source.
 */
final readonly class ChoixImport
{
    /**
     * @param array<string, RoleImport> $calques calque => rôle
     * @param array<int, RoleImport>    $blocs   index du bloc => rôle imposé
     */
    public function __construct(
        public ?int $contour,
        public int $hauteurSousPlafond = 2500,
        public ModeContour $mode = ModeContour::Interieur,
        public int $epaisseurMur = 100,
        public string $nom = '',
        public array $calques = [],
        public array $blocs = [],
    ) {
    }

    public function decalage(): int
    {
        return $this->mode->decalage($this->epaisseurMur);
    }

    /**
     * Rôle d'un bloc : rôle imposé, sinon rôle de son calque (porte, fenêtre, équipement, ignoré), sinon deviné d'après
     * son nom, sinon équipement.
     */
    public function roleDuBloc(int $index, BlocImporte $bloc): RoleImport
    {
        if (isset($this->blocs[$index])) {
            return $this->blocs[$index];
        }
        $role = $this->calques[$bloc->calque] ?? null;
        if (null !== $role && RoleImport::Contour !== $role) {
            return $role;
        }

        return RoleImport::devinerBloc($bloc->nom) ?? RoleImport::Equipement;
    }
}
