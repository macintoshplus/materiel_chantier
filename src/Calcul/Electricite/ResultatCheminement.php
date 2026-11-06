<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

use App\Geometrie\Avertissement;

/**
 * Résultat du cheminement d'un réseau. `gaines` : longueur (mm) par diamètre. `cables` : longueur (mm) par nombre de
 * conducteurs, terre comprise (« 3G1,5 » = 3 conducteurs), pour la section du réseau (centièmes de mm²).
 */
final readonly class ResultatCheminement
{
    /**
     * @param list<Troncon>                                                  $troncons
     * @param array<int, int>                                                $gaines          diamètre => longueur
     * @param list<array{section: int, conducteurs: int, longueur: int}>     $cables
     * @param list<Avertissement>                                            $avertissements
     * @param list<Avertissement>                                            $erreurs
     */
    public function __construct(
        public string $reseau,
        public int $sectionCentiemes,
        public array $troncons,
        public array $gaines,
        public array $cables,
        public array $avertissements,
        public array $erreurs,
    ) {
    }

    /**
     * @param list<Troncon>       $troncons
     * @param list<Avertissement> $avertissements
     * @param list<Avertissement> $erreurs
     */
    public static function depuisTroncons(string $reseau, int $sectionCentiemes, array $troncons, array $avertissements, array $erreurs): self
    {
        $gaines = [];
        $cables = [];
        foreach ($troncons as $troncon) {
            if (null !== $troncon->diametreGaine) {
                $gaines[$troncon->diametreGaine] = ($gaines[$troncon->diametreGaine] ?? 0) + $troncon->longueurMm;
            }
            $n = $troncon->conducteurs();
            $cables[$n] = ['section' => $sectionCentiemes, 'conducteurs' => $n, 'longueur' => ($cables[$n]['longueur'] ?? 0) + $troncon->longueurCableMm];
        }
        ksort($gaines);
        ksort($cables);

        return new self($reseau, $sectionCentiemes, $troncons, $gaines, array_values($cables), $avertissements, $erreurs);
    }

    /**
     * Part du cheminement dont l'élément d'arrivée est dans la liste (pour répartir un réseau entre les pièces).
     *
     * @param list<string> $enfants
     */
    public function pourEnfants(array $enfants): self
    {
        return self::depuisTroncons($this->reseau, $this->sectionCentiemes, array_values(array_filter($this->troncons, static fn (Troncon $t): bool => \in_array($t->enfant, $enfants, true))), [], []);
    }

    public function longueurGaineTotale(): int
    {
        return array_sum($this->gaines);
    }

    public function longueurCableTotale(): int
    {
        return array_sum(array_column($this->cables, 'longueur'));
    }
}
