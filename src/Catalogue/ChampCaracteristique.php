<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Description d'une clé du JSON `caracteristiques`. La clé peut être pointée (`pose.entraxe_max`).
 * Pour une liste, `sousChamps` décrit chaque ligne et `cleUnique` les clés qui identifient une ligne.
 */
final readonly class ChampCaracteristique
{
    /**
     * @param list<string>                $choix
     * @param list<ChampCaracteristique>  $sousChamps
     * @param list<string>                $cleUnique
     */
    public function __construct(
        public string $cle,
        public TypeChamp $type,
        public bool $obligatoire = false,
        public ?string $unite = null,
        public array $choix = [],
        public array $sousChamps = [],
        public array $cleUnique = [],
        public int|float|null $min = null,
        public int|float|null $max = null,
    ) {
    }
}
