<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Geometrie\ConstructeurSurface;
use App\Geometrie\ContrainteSommet;
use App\Geometrie\ErreurGeometrique;
use PHPUnit\Framework\TestCase;

final class ConstructeurSurfaceTest extends TestCase
{
    public function testRectangleParDistances(): void
    {
        $contour = (new ConstructeurSurface())->construire([
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 4000),
            ContrainteSommet::distance('c', 'b', 'v', 2500),
            ContrainteSommet::distance('d', 'a', 'v', 2500),
        ]);

        self::assertSame(['a', 'b', 'c', 'd'], $contour->ids());
        self::assertSame(10_000_000, $contour->polygone()->aire());
        self::assertSame(2500, $contour->point('d')->y);
    }

    public function testAngleEntreAretes(): void
    {
        // pignon : a(0,0) b(4000,0) puis c à 90° de l'arête a->b, distance 2000, puis d à 135° de b->c
        $contour = (new ConstructeurSurface())->construire([
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 4000),
            ContrainteSommet::angle('c', 'b', 'a', 900, 2000),
            ContrainteSommet::angle('d', 'c', 'b', 900, 4000),
        ]);

        self::assertSame(4000, $contour->point('c')->x);
        self::assertSame(2000, $contour->point('c')->y);
        self::assertSame(0, $contour->point('d')->x);
        self::assertSame(2000, $contour->point('d')->y);
    }

    /**
     * @param list<ContrainteSommet> $contraintes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('erreurs')]
    public function testErreursDeContraintes(array $contraintes, string $code, ?string $champ): void
    {
        try {
            (new ConstructeurSurface())->construire($contraintes);
            self::fail('Une erreur était attendue.');
        } catch (ErreurGeometrique $e) {
            self::assertSame($code, $e->erreur);
            self::assertSame($champ, $e->champ);
        }
    }

    /**
     * @return iterable<string, array{list<ContrainteSommet>, string, string|null}>
     */
    public static function erreurs(): iterable
    {
        yield 'sur-définition' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 1000),
            ContrainteSommet::distance('b', 'a', 'v', 1000),
        ], 'constraint_duplicate_vertex', 'b'];
        yield 'référence inconnue' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'zzz', 'u', 1000),
        ], 'constraint_unknown_reference', 'b'];
        yield 'distance nulle' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 0),
        ], 'constraint_invalid_value', 'b'];
        yield 'contrôle incohérent' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 4000),
            ContrainteSommet::distance('c', 'b', 'v', 3000),
            ContrainteSommet::distance('d', 'a', 'v', 3000),
            ContrainteSommet::controle('x', 'a', 'c', 5200),
        ], 'constraint_inconsistent', 'x'];
        yield 'trop peu de sommets' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 4000),
        ], 'constraint_too_few_vertices', null];
        yield 'contour dégénéré' => [[
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 4000),
            ContrainteSommet::distance('c', 'b', 'u', 1000),
        ], 'constraint_degenerate', null];
    }

    public function testUnControleCoherentEstAccepte(): void
    {
        $contour = (new ConstructeurSurface())->construire([
            ContrainteSommet::fixe('a', 0, 0),
            ContrainteSommet::distance('b', 'a', 'u', 3000),
            ContrainteSommet::distance('c', 'b', 'v', 4000),
            ContrainteSommet::controle('x', 'a', 'c', 5000),
        ]);

        self::assertCount(3, $contour->sommets);
    }
}
