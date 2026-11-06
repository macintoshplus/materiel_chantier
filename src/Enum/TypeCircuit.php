<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de circuit électrique, avec la section (mm², en texte décimal) et la protection (A) usuelles.
 */
enum TypeCircuit: string
{
    case Eclairage = 'eclairage';
    case Prises = 'prises';
    case Chauffage = 'chauffage';
    case Cuisson = 'cuisson';
    case Specialise = 'specialise';

    public function sectionDefaut(): string
    {
        return match ($this) {
            self::Eclairage, self::Chauffage => '1.5',
            self::Prises, self::Specialise => '2.5',
            self::Cuisson => '6',
        };
    }

    public function protectionDefaut(): int
    {
        return match ($this) {
            self::Eclairage, self::Chauffage => 16,
            self::Prises, self::Specialise => 20,
            self::Cuisson => 32,
        };
    }
}
