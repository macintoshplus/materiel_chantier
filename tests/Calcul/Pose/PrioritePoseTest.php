<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Pose;

use App\Calcul\Pose\PrioritePose;
use App\Calcul\Pose\SourceValeur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrioritePoseTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, ?int, list<int>, ?int, ?SourceValeur, list<string>}>
     */
    public static function cas(): iterable
    {
        yield 'surcharge dans les limites' => [400, 600, [600], 400, SourceValeur::Utilisateur, []];
        yield 'surcharge hors limite : acceptee avec avertissement' => [800, 600, [600], 800, SourceValeur::Utilisateur, ['pose_override_exceeds_limit']];
        yield 'surcharge sans limite' => [800, 600, [], 800, SourceValeur::Utilisateur, []];
        yield 'defaut du systeme sans limite' => [null, 600, [], 600, SourceValeur::Systeme, []];
        yield 'limite plus stricte que le defaut' => [null, 600, [400, 500], 400, SourceValeur::Article, []];
        yield 'limite plus large que le defaut : le defaut reste' => [null, 600, [1000], 600, SourceValeur::Systeme, []];
        yield 'sans defaut : minimum des limites' => [null, null, [600, 450], 450, SourceValeur::Article, []];
        yield 'aucune donnee : erreur explicite' => [null, null, [], null, null, []];
    }

    /**
     * @param list<int>    $limites
     * @param list<string> $codes
     */
    #[DataProvider('cas')]
    public function test_priorite(?int $surcharge, ?int $defaut, array $limites, ?int $attendu, ?SourceValeur $source, array $codes): void
    {
        $r = PrioritePose::resoudre('entraxe', $surcharge, $defaut, $limites);

        self::assertSame($attendu, $r->valeur);
        self::assertSame($source, $r->source);
        self::assertSame($codes, array_map(static fn ($a): string => $a->code, $r->avertissements));
        self::assertSame(null === $attendu ? 'pose_parameter_unknown' : null, $r->erreur?->code);
    }
}
