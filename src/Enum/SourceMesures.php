<?php

declare(strict_types=1);

namespace App\Enum;

enum SourceMesures: string
{
    case Saisie = 'saisie';
    case Dxf = 'dxf';
    case Csv = 'csv';
}
