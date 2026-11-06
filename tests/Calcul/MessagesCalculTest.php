<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\MessagesCalcul;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagesCalculTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function codes(): iterable
    {
        foreach (MessagesCalcul::codes() as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('codes')]
    public function test_chaque_code_a_une_traduction_francaise(string $code): void
    {
        $xml = simplexml_load_file(__DIR__.'/../../translations/validators.fr.xlf');
        self::assertNotFalse($xml);
        $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $sources = [];
        foreach ($xml->xpath('//x:trans-unit/x:source') ?: [] as $noeud) {
            $sources[] = (string) $noeud;
        }

        self::assertContains(MessagesCalcul::message($code), $sources, $code);
    }
}
