<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Article du catalogue associé à un type d'élément (boîte, mécanisme, plaque, connecteur) : une catégorie et des
 * critères d'égalité. Le domaine ne connaît pas le catalogue en base ; l'application résout l'article.
 */
final readonly class MaterielAssocie
{
    /**
     * @param array<string, int|string> $criteres
     */
    public function __construct(public string $categorie, public array $criteres = [])
    {
    }
}
