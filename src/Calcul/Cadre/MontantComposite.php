<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Catalogue\Vue\Renfort;

/**
 * Montant final de longueur `longueur` constitué d'une ou deux lignes, chaque ligne étant une suite de segments
 * découpés dans des barres standard.
 */
final readonly class MontantComposite
{
    /**
     * @param list<list<int>> $lignes   longueurs des segments de chaque ligne
     * @param list<list<int>> $joints   positions des joints de chaque ligne
     */
    public function __construct(public int $longueur, public Renfort $renfort, public array $lignes, public array $joints)
    {
    }

    public static function creer(int $longueur, Renfort $renfort, int $longueurBarre, int $decalageMin = 0): self
    {
        if (Renfort::Simple === $renfort) {
            return new self($longueur, $renfort, [[$longueur]], [[]]);
        }
        if (Renfort::DoubleAccole === $renfort) {
            return new self($longueur, $renfort, [[$longueur], [$longueur]], [[], []]);
        }
        $joints = PlanificateurJoints::joints($longueur, $longueurBarre, $decalageMin);

        return new self(
            $longueur,
            $renfort,
            [PlanificateurJoints::segments($longueur, $joints[0]), PlanificateurJoints::segments($longueur, $joints[1])],
            $joints,
        );
    }

    /**
     * @return list<int> segments de toutes les lignes, pièces à découper
     */
    public function segments(): array
    {
        return array_merge(...$this->lignes);
    }

    public function nombreJoints(): int
    {
        return array_sum(array_map('count', $this->joints));
    }

    public function nombreLignes(): int
    {
        return \count($this->lignes);
    }
}
