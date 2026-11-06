<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

use App\Catalogue\MaterielLisible;

/**
 * Lecture typée et stricte d'une caractéristique : une clé absente ou d'un mauvais type lève une exception.
 */
final readonly class Lecteur
{
    public function __construct(private MaterielLisible $materiel)
    {
    }

    public function entier(string $chemin): int
    {
        $v = $this->materiel->valeur($chemin);

        return \is_int($v) ? $v : throw CaracteristiqueManquante::pour($chemin);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function liste(string $chemin): array
    {
        $v = $this->materiel->valeur($chemin);
        if (!\is_array($v) || !array_is_list($v)) {
            throw CaracteristiqueManquante::pour($chemin);
        }
        $lignes = [];
        foreach ($v as $ligne) {
            if (!\is_array($ligne)) {
                throw CaracteristiqueManquante::pour($chemin);
            }
            $lignes[] = $ligne;
        }

        return $lignes;
    }
}
