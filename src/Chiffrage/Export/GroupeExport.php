<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

/**
 * @phpstan-type Liste list<ItemExport>
 */
final readonly class GroupeExport
{
    /**
     * @param string       $titre  catégorie (code) ou nom de pièce
     * @param list<ItemExport> $items
     */
    public function __construct(public string $titre, public bool $categorie, public array $items)
    {
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (ItemExport $i): int => $i->total, $this->items));
    }
}
