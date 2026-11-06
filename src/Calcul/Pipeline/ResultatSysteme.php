<?php

declare(strict_types=1);

namespace App\Calcul\Pipeline;

use App\Geometrie\Avertissement;

/**
 * Résultat d'un système complet (toutes ses faces). Un besoin commun n'est compté qu'une fois ; les parements
 * le sont par face. Valeurs entières : mm, millièmes de m².K/W.
 */
final readonly class ResultatSysteme
{
    /**
     * @param list<LigneSysteme>  $lignes
     * @param list<Avertissement> $avertissements
     * @param list<Avertissement> $erreurs
     */
    public function __construct(
        public array $lignes,
        public int $rSystemeMilli,
        public ?int $epaisseurTotale,
        public array $avertissements = [],
        public array $erreurs = [],
    ) {
    }

    public function estValide(): bool
    {
        return [] === $this->erreurs;
    }

    /**
     * @return list<LigneSysteme>
     */
    public function lignesDeLaFace(?string $face): array
    {
        return array_values(array_filter($this->lignes, static fn (LigneSysteme $l): bool => $l->face === $face));
    }

    public function ligne(string $cle, ?string $face = null): ?LigneSysteme
    {
        foreach ($this->lignes as $l) {
            if ($l->ligne->cle === $cle && $l->face === $face) {
                return $l;
            }
        }

        return null;
    }
}
