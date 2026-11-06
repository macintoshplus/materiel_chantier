<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Données d'un réseau pour le cheminement : point de départ, éléments, liaisons et section (en centièmes de mm²).
 */
final readonly class ReseauCalcul
{
    public const int RESERVE_DEFAUT_PCT = 10;

    /**
     * @param array<string, ElementReseau> $elements indexés par clé
     * @param list<Liaison>                $liaisons
     */
    public function __construct(
        public string $nom,
        public string $depart,
        public array $elements,
        public array $liaisons,
        public int $sectionCentiemes,
        public int $reservePct = self::RESERVE_DEFAUT_PCT,
    ) {
    }
}
