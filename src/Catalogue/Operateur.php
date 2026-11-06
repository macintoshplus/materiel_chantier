<?php

declare(strict_types=1);

namespace App\Catalogue;

enum Operateur: string
{
    case Egal = '=';
    case Different = '!=';
    case Inferieur = '<';
    case InferieurOuEgal = '<=';
    case Superieur = '>';
    case SuperieurOuEgal = '>=';
    case Dans = 'in';

    public function evaluer(mixed $valeur, mixed $reference): bool
    {
        return match ($this) {
            self::Egal => $valeur === $reference || (is_numeric($valeur) && is_numeric($reference) && (float) $valeur === (float) $reference),
            self::Different => !self::Egal->evaluer($valeur, $reference),
            self::Inferieur => is_numeric($valeur) && is_numeric($reference) && $valeur < $reference,
            self::InferieurOuEgal => is_numeric($valeur) && is_numeric($reference) && $valeur <= $reference,
            self::Superieur => is_numeric($valeur) && is_numeric($reference) && $valeur > $reference,
            self::SuperieurOuEgal => is_numeric($valeur) && is_numeric($reference) && $valeur >= $reference,
            self::Dans => \is_array($reference) && \in_array($valeur, $reference, true),
        };
    }
}
