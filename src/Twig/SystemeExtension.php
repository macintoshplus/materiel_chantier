<?php

declare(strict_types=1);

namespace App\Twig;

use App\Calcul\Systeme\RegistreSystemes;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Libellé anglais (à traduire) de la définition d'un système appliqué, à partir de son code.
 */
final class SystemeExtension extends AbstractExtension
{
    public function __construct(private readonly RegistreSystemes $systemes)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('libelle_systeme', $this->libelle(...))];
    }

    public function libelle(string $code): string
    {
        return $this->systemes->possede($code) ? $this->systemes->get($code)->libelle() : $code;
    }
}
