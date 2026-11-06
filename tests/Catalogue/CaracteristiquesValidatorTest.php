<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\CaracteristiquesValidator;
use App\Catalogue\ErreurCaracteristique;
use PHPUnit\Framework\TestCase;

final class CaracteristiquesValidatorTest extends TestCase
{
    /**
     * @param list<ErreurCaracteristique> $erreurs
     *
     * @return list<string>
     */
    private static function codes(array $erreurs): array
    {
        return array_map(static fn (ErreurCaracteristique $e): string => $e->code.'@'.$e->chemin, $erreurs);
    }

    public function testDesCaracteristiquesValidesNeDonnentAucuneErreur(): void
    {
        self::assertSame([], (new CaracteristiquesValidator())->valider(new Isolant(), ['lambda' => 0.032, 'famille' => 'roche']));
    }

    public function testUneCleObligatoireManquanteEstRefusee(): void
    {
        $erreurs = (new CaracteristiquesValidator())->valider(new Isolant(), ['famille' => 'roche']);

        self::assertSame(['required@lambda'], self::codes($erreurs));
    }

    public function testUneCleInconnueEstRefusee(): void
    {
        $erreurs = (new CaracteristiquesValidator())->valider(new Isolant(), ['lambda' => 0.03, 'inconnue' => 1]);

        self::assertSame(['unknown_key@inconnue'], self::codes($erreurs));
    }

    public function testUnChoixInvalidEtUneValeurHorsBornesSontRefuses(): void
    {
        $erreurs = (new CaracteristiquesValidator())->valider(new Isolant(), ['lambda' => -1, 'famille' => 'mousse']);

        self::assertSame(['out_of_range@lambda', 'invalid_choice@famille'], self::codes($erreurs));
    }

    public function testUnMauvaisTypeEstRefuse(): void
    {
        $erreurs = (new CaracteristiquesValidator())->valider(new Isolant(), ['lambda' => 'abc']);

        self::assertSame(['invalid_type@lambda'], self::codes($erreurs));
    }

    public function testUneLigneDePorteeEnDoubleEstRefusee(): void
    {
        $ligne = ['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 2600];
        $erreurs = (new CaracteristiquesValidator())->valider(new ProfilOssature(), [
            'famille' => 'montant',
            'pose' => ['portees' => [$ligne, $ligne]],
        ]);

        self::assertSame(['duplicate_line@pose.portees[1]'], self::codes($erreurs));
    }

    public function testDeuxLignesDePorteeDistinctesSontAcceptees(): void
    {
        $ligne = ['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 2600];
        $erreurs = (new CaracteristiquesValidator())->valider(new ProfilOssature(), [
            'famille' => 'montant',
            'pose' => ['portees' => [$ligne, [...$ligne, 'entraxe' => 400]]],
        ]);

        self::assertSame([], $erreurs);
    }
}
