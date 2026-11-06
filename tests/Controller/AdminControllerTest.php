<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Materiau;
use App\Service\ImportateurDefauts;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    public function testLesEcransDAdministrationSAffichent(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->creerSchema();
        self::getContainer()->get(ImportateurDefauts::class)->importer(new \DateTimeImmutable());
        $materiau = $em->getRepository(Materiau::class)->findOneBy([]);
        self::assertInstanceOf(Materiau::class, $materiau);

        foreach ([
            '/catalogue/materiaux',
            '/catalogue/materiaux/nouveau',
            '/catalogue/materiaux/nouveau?categorie=profil_ossature',
            '/catalogue/materiaux/'.$materiau->getId().'/modifier',
            '/catalogue/materiaux/'.$materiau->getId().'/prix',
            '/catalogue/fournisseurs',
            '/catalogue/fournisseurs/nouveau',
            '/catalogue/import',
            '/projets',
        ] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }
}
