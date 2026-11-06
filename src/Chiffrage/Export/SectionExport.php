<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

final readonly class SectionExport
{
    /**
     * @param list<GroupeExport>  $groupes
     * @param array<string, int>  $livraisons forfaits de livraison (fournisseur → centimes) inclus dans cette section
     */
    public function __construct(
        public ?string $titre,
        public ?string $adresse,
        public array $groupes,
        public array $livraisons = [],
    ) {
    }

    public function sousTotal(): int
    {
        return array_sum(array_map(static fn (GroupeExport $g): int => $g->total(), $this->groupes));
    }

    public function total(): int
    {
        return $this->sousTotal() + array_sum($this->livraisons);
    }

    public function aUnPrixManquant(): bool
    {
        foreach ($this->groupes as $groupe) {
            foreach ($groupe->items as $item) {
                if ($item->manquant) {
                    return true;
                }
            }
        }

        return false;
    }
}
