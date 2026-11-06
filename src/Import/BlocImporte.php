<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Point2D;

/**
 * Insertion de bloc (porte, fenêtre, appareillage…) : `insertion` est le point d'insertion, `centre` le centre de la
 * géométrie du bloc (le point d'insertion d'une porte est souvent une charnière) ; en mm.
 */
final readonly class BlocImporte
{
    /**
     * @param array<string, string> $attributs étiquette (majuscules) => valeur
     * @param int|null              $largeur   étendue de la géométrie du bloc, mise à l'échelle (mm)
     */
    public function __construct(
        public string $nom,
        public string $calque,
        public Point2D $insertion,
        public Point2D $centre,
        public float $rotation = 0.0,
        public float $echelleX = 1.0,
        public float $echelleY = 1.0,
        public ?int $largeur = null,
        public array $attributs = [],
    ) {
    }

    /**
     * Valeur numérique (mm) du premier attribut présent parmi `$etiquettes`.
     *
     * @param list<string> $etiquettes
     */
    public function attributMm(array $etiquettes): ?int
    {
        foreach ($etiquettes as $etiquette) {
            $valeur = $this->attributs[strtoupper($etiquette)] ?? null;
            if (null !== $valeur && 1 === preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(?:mm)?\s*$/i', $valeur, $m)) {
                return (int) round((float) str_replace(',', '.', $m[1]));
            }
        }

        return null;
    }
}
