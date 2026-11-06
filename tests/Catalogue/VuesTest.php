<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\Vue\CaracteristiqueManquante;
use App\Catalogue\Vue\Orientation;
use App\Catalogue\Vue\ProfilView;
use App\Entity\Materiau;
use PHPUnit\Framework\TestCase;

final class VuesTest extends TestCase
{
    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function profil(array $caracteristiques): Materiau
    {
        $m = new Materiau();
        $m->setCategorie('profil_ossature');
        $m->setCaracteristiques($caracteristiques);

        return $m;
    }

    public function testLesPorteesSontFiltreesParOrientation(): void
    {
        $vue = new ProfilView($this->profil(['pose' => ['entraxe_defaut' => 600, 'portees' => [
            ['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 2600],
            ['renfort' => 'simple', 'orientation' => 'horizontale', 'entraxe' => 600, 'longueur_max' => 2000],
        ]]]));

        self::assertSame(600, $vue->entraxeDefaut());
        self::assertCount(2, $vue->portees());
        $verticales = $vue->portees(Orientation::Verticale);
        self::assertCount(1, $verticales);
        self::assertSame(2600, $verticales[0]->longueurMax);
    }

    public function testUneCaracteristiqueManquanteLeveUneException(): void
    {
        $vue = new ProfilView($this->profil([]));

        $this->expectException(CaracteristiqueManquante::class);
        $vue->entraxeDefaut();
    }

    public function testUneCategorieIncorrecteEstRefusee(): void
    {
        $m = new Materiau();
        $m->setCategorie('isolant');

        $this->expectException(\InvalidArgumentException::class);
        new ProfilView($m);
    }
}
