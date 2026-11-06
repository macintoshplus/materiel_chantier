<?php

declare(strict_types=1);

namespace App\Tests\Chiffrage;

use App\Chiffrage\Montant;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\Repartition;
use App\Tests\Chiffrage\Support\CatalogueMemoire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutilsChiffrageTest extends TestCase
{
    #[DataProvider('montants')]
    public function test_montant_en_centimes(?string $decimal, int $attendu): void
    {
        self::assertSame($attendu, Montant::centimes($decimal));
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function montants(): iterable
    {
        yield 'entier' => ['3', 300];
        yield 'deux décimales' => ['12.50', 1250];
        yield 'virgule' => ['12,5', 1250];
        yield 'au-delà du centime' => ['0.019', 1];
        yield 'nul' => [null, 0];
        yield 'invalide' => ['abc', 0];
    }

    /**
     * @param list<int> $poids
     * @param list<int> $attendu
     */
    #[DataProvider('repartitions')]
    public function test_repartition_sans_perte(int $total, array $poids, array $attendu): void
    {
        $parts = Repartition::proportionnelle($total, $poids);

        self::assertSame($attendu, $parts);
        self::assertSame($total > 0 && array_sum($poids) > 0 ? $total : 0, array_sum($parts));
    }

    /**
     * @return iterable<string, array{int, list<int>, list<int>}>
     */
    public static function repartitions(): iterable
    {
        yield 'égal' => [100, [1, 1], [50, 50]];
        yield 'tiers' => [100, [1, 1, 1], [34, 33, 33]];
        yield 'proportionnel' => [1000, [3, 1], [750, 250]];
        yield 'poids nuls' => [100, [0, 0], [0, 0]];
        yield 'total nul' => [0, [1, 2], [0, 0]];
        yield 'aucune part' => [10, [], []];
    }

    public function test_quantite_brute_recalcule_pour_un_candidat_de_dimensions_differentes(): void
    {
        $origine = CatalogueMemoire::article('R3');
        $origine->setLongueur(3000);
        $court = CatalogueMemoire::article('R26');
        $court->setLongueur(2600);
        $copie = CatalogueMemoire::article('R3b');
        $copie->setLongueur(3000);
        $quantite = new QuantiteBrute($origine, 10, static fn ($m): int => 12);

        self::assertSame(10, $quantite->pour($origine));
        self::assertSame(10, $quantite->pour($copie));
        self::assertSame(12, $quantite->pour($court));
    }
}
