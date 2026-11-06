<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\FamilleEquipement;
use App\Service\TypesEquipement;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Libellé anglais (clé de traduction) d'un type d'équipement à partir de sa famille et de son code.
 */
final class EquipementExtension extends AbstractExtension
{
    public function __construct(private readonly TypesEquipement $types)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('libelle_equipement', $this->libelle(...))];
    }

    public function libelle(string $famille, string $type): string
    {
        $f = FamilleEquipement::tryFrom($famille);

        return null === $f ? $type : $this->types->libelle($f, $type);
    }
}
