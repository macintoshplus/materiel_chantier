<?php

declare(strict_types=1);

namespace App\Catalogue;

final readonly class ErreurCaracteristique
{
    public const string REQUIS = 'required';
    public const string CLE_INCONNUE = 'unknown_key';
    public const string TYPE_INVALIDE = 'invalid_type';
    public const string CHOIX_INVALIDE = 'invalid_choice';
    public const string HORS_BORNES = 'out_of_range';
    public const string DOUBLON = 'duplicate_line';

    /**
     * @param array<string, scalar> $parametres
     */
    public function __construct(
        public string $code,
        public string $chemin,
        public array $parametres = [],
    ) {
    }
}
