<?php

declare(strict_types=1);

namespace App\Import;

enum FormatCsv: string
{
    case Points = 'points';
    case CotesAngles = 'cotes_angles';
}
