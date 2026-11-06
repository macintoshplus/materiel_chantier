<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\Aplatisseur;
use App\Catalogue\Categorie\ProfilOssature;
use PHPUnit\Framework\TestCase;

final class AplatisseurTest extends TestCase
{
    public function testAllerRetour(): void
    {
        $donnees = [
            'famille' => 'montant',
            'serie' => 48,
            'pose' => [
                'entraxe_defaut' => 600,
                'portees' => [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 2600]],
            ],
        ];
        $categorie = new ProfilOssature();
        $aplatisseur = new Aplatisseur();

        $plat = $aplatisseur->aplatir($categorie, $donnees);

        self::assertSame(600, $plat['pose__entraxe_defaut']);
        self::assertIsString($plat['pose__portees']);
        self::assertSame($donnees, $aplatisseur->imbriquer($categorie, $plat));
    }

    public function testUnJsonInvalideLeveUneException(): void
    {
        $this->expectException(\JsonException::class);
        (new Aplatisseur())->imbriquer(new ProfilOssature(), ['pose__portees' => '{pas du json']);
    }
}
