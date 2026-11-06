<?php

declare(strict_types=1);

namespace App\Catalogue;

final class RegistreCategories
{
    /** @var array<string, CategorieMateriau> */
    private array $categories = [];

    /**
     * @param iterable<CategorieMateriau> $categories
     */
    public function __construct(iterable $categories)
    {
        foreach ($categories as $categorie) {
            $this->categories[$categorie->code()] = $categorie;
        }
        ksort($this->categories);
    }

    public function possede(string $code): bool
    {
        return isset($this->categories[$code]);
    }

    public function get(string $code): CategorieMateriau
    {
        return $this->categories[$code] ?? throw new \InvalidArgumentException(\sprintf('Unknown material category "%s".', $code));
    }

    /**
     * @return array<string, CategorieMateriau>
     */
    public function toutes(): array
    {
        return $this->categories;
    }
}
