<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Rectangle dans le repère d'une surface (u, v) : coin bas-gauche + dimensions, en mm.
 */
final readonly class Rectangle
{
    public function __construct(public int $u, public int $v, public int $largeur, public int $hauteur)
    {
    }

    public function polygone(): Polygone
    {
        return new Polygone([
            new Point2D($this->u, $this->v),
            new Point2D($this->u + $this->largeur, $this->v),
            new Point2D($this->u + $this->largeur, $this->v + $this->hauteur),
            new Point2D($this->u, $this->v + $this->hauteur),
        ]);
    }
}
