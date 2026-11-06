<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Catalogue\Aplatisseur;
use App\Catalogue\CategorieMateriau;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<array<array-key, mixed>, array<string, mixed>>
 */
final class CaracteristiquesTransformer implements DataTransformerInterface
{
    private readonly Aplatisseur $aplatisseur;

    public function __construct(private readonly CategorieMateriau $categorie)
    {
        $this->aplatisseur = new Aplatisseur();
    }

    public function transform(mixed $value): array
    {
        return $this->aplatisseur->aplatir($this->categorie, \is_array($value) ? $value : []);
    }

    public function reverseTransform(mixed $value): array
    {
        try {
            return $this->aplatisseur->imbriquer($this->categorie, \is_array($value) ? $value : []);
        } catch (\JsonException $e) {
            throw new TransformationFailedException('Invalid JSON.', 0, $e, 'The list must be valid JSON.');
        }
    }
}
