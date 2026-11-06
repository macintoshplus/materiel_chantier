<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Geometrie\ContrainteSommet;
use App\Geometrie\TypeContrainte;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Valeur réglable d'un sommet d'une surface : distance (mm) et, pour une contrainte d'angle, angle en degrés.
 */
final class ContrainteSaisie
{
    public string $id = '';

    /** `distance` ou `angle` (information, non modifiable). */
    public string $type = 'distance';

    /** Sommet de départ de la mesure (information). */
    public ?string $depuis = null;

    /** Direction d'une contrainte de distance (information). */
    public ?string $direction = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $distance = null;

    #[Assert\Positive]
    #[Assert\LessThan(360)]
    public ?float $angle = null;

    public static function depuis(ContrainteSommet $contrainte): self
    {
        $saisie = new self();
        $saisie->id = $contrainte->id;
        $saisie->type = TypeContrainte::Angle === $contrainte->type ? 'angle' : 'distance';
        $saisie->depuis = $contrainte->depuis;
        $saisie->direction = $contrainte->direction;
        $saisie->distance = $contrainte->distance;
        $saisie->angle = null === $contrainte->angle ? null : $contrainte->angle / 10;

        return $saisie;
    }
}
