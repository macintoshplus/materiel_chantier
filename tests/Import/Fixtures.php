<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Geometrie\ErreurGeometrique;
use App\Import\Csv\ReleveCsvReader;
use App\Import\Dxf\DxfInterpreter;
use App\Import\Dxf\DxfReader;
use App\Import\PlanImporte;
use App\Import\UniteImport;

final class Fixtures
{
    public static function chemin(string $relatif): string
    {
        return \dirname(__DIR__).'/fixtures/'.$relatif;
    }

    public static function dxf(string $nom, ?UniteImport $unite = null): PlanImporte
    {
        $document = (new DxfReader())->lireFichier(self::chemin('dxf/'.$nom));
        $interprete = new DxfInterpreter();
        [$propose, $devinee] = $interprete->proposerUnite($document);

        return $interprete->interpreter($document, $unite ?? $propose, null === $unite && $devinee);
    }

    public static function csv(string $nom, UniteImport $unite = UniteImport::Millimetre): PlanImporte
    {
        return (new ReleveCsvReader())->lireFichier(self::chemin('csv/'.$nom), $unite);
    }

    public static function codeErreur(\Closure $f): string
    {
        try {
            $f();
        } catch (ErreurGeometrique $e) {
            return $e->erreur;
        }

        return '';
    }
}
