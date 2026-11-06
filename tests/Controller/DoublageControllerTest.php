<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\ImportateurDefauts;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DoublageControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    public function testLaPageAffichageLeFormulaire(): void
    {
        $client = static::createClient();
        $this->creerSchema();
        $importateur = self::getContainer()->get(ImportateurDefauts::class);
        $importateur->importer(new \DateTimeImmutable());

        $client->request('GET', '/doublage/mur');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}
