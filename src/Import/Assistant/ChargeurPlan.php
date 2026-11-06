<?php

declare(strict_types=1);

namespace App\Import\Assistant;

use App\Geometrie\ErreurGeometrique;
use App\Import\Csv\ReleveCsvReader;
use App\Import\Dxf\DxfInterpreter;
use App\Import\Dxf\DxfReader;
use App\Import\FormatCsv;
use App\Import\PlanImporte;
use App\Import\UniteImport;

/**
 * Relit le fichier temporaire et l'interprète avec l'unité choisie : changer l'unité ne demande donc pas de
 * téléverser à nouveau le fichier.
 */
final readonly class ChargeurPlan
{
    public function __construct(
        private DxfReader $dxf,
        private DxfInterpreter $interprete,
        private ReleveCsvReader $csv,
    ) {
    }

    /**
     * Unité proposée à l'envoi : `$INSUNITS` ou estimation pour un DXF, millimètres pour un CSV.
     *
     * @return array{UniteImport, bool}
     *
     * @throws ErreurGeometrique
     */
    public function proposerUnite(string $source, string $chemin): array
    {
        if ('dxf' !== $source) {
            return [UniteImport::Millimetre, false];
        }

        return $this->interprete->proposerUnite($this->dxf->lireFichier($chemin));
    }

    /**
     * @throws ErreurGeometrique
     */
    public function charger(string $chemin, EtatAssistant $etat): PlanImporte
    {
        $unite = UniteImport::tryFrom($etat->unite) ?? UniteImport::Millimetre;
        if ('csv' === $etat->source) {
            return $this->csv->lireFichier($chemin, $unite, null === $etat->format ? null : FormatCsv::tryFrom($etat->format));
        }

        return $this->interprete->interpreter($this->dxf->lireFichier($chemin), $unite, $etat->uniteDevinee && !$etat->parametresConfirmes);
    }
}
