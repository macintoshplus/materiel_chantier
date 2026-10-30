<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DoublageControllerTest extends WebTestCase
{
    public function testLaPageAffichageLeFormulaire(): void
    {
        $client = static::createClient();
        $client->request('GET', '/doublage/mur');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}
