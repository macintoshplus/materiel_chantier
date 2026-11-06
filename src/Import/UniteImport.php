<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Unité des coordonnées d'un fichier importé ; `enMillimetres()` est le facteur vers le millimètre.
 */
enum UniteImport: string
{
    case Millimetre = 'mm';
    case Centimetre = 'cm';
    case Metre = 'm';
    case Pouce = 'in';
    case Pied = 'ft';

    /**
     * Libellé anglais (clé de traduction).
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Millimetre => 'Millimetres (mm)',
            self::Centimetre => 'Centimetres (cm)',
            self::Metre => 'Metres (m)',
            self::Pouce => 'Inches (in)',
            self::Pied => 'Feet (ft)',
        };
    }

    public function enMillimetres(): float
    {
        return match ($this) {
            self::Millimetre => 1.0,
            self::Centimetre => 10.0,
            self::Metre => 1000.0,
            self::Pouce => 25.4,
            self::Pied => 304.8,
        };
    }

    /**
     * Valeur de `$INSUNITS` d'un DXF (0 ou valeur non gérée : null).
     */
    public static function depuisInsUnits(int $code): ?self
    {
        return match ($code) {
            1 => self::Pouce,
            2 => self::Pied,
            4 => self::Millimetre,
            5 => self::Centimetre,
            6 => self::Metre,
            default => null,
        };
    }

    /**
     * Unités proposées pour un fichier CSV.
     *
     * @return list<self>
     */
    public static function pourCsv(): array
    {
        return [self::Millimetre, self::Centimetre, self::Metre];
    }
}
