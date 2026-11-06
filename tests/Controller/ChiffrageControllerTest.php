<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Chiffrage;
use App\Entity\Fournisseur;
use App\Entity\Projet;
use App\Enum\TypeStrategie;
use App\Service\ChiffrageService;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChiffrageControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    public function testLesEcransDeChiffrageEtDExportSAffichent(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('Maison');
        $fournisseur = new Fournisseur();
        $fournisseur->setNom('Alpha');
        $em->persist($projet);
        $em->persist($fournisseur);
        $em->flush();
        $service = self::getContainer()->get(ChiffrageService::class);
        $a = $service->creer($projet, TypeStrategie::MeilleurPrix, new \DateTimeImmutable(), [], 'A');
        $b = $service->creer($projet, TypeStrategie::PlusProche, new \DateTimeImmutable(), [], 'B');
        $base = '/projets/'.$projet->getId().'/chiffrages';

        foreach ([
            $base,
            $base.'/'.$a->getId(),
            $base.'/comparer?a='.$a->getId().'&b='.$b->getId(),
            $base.'/'.$a->getId().'/exports',
            $base.'/'.$a->getId().'/exports/liste?type=piece&prix=0',
            $base.'/'.$a->getId().'/exports/liste?type=fournisseur',
        ] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }

        $client->request('GET', $base.'/'.$a->getId().'/exports/liste?type=projet&format=pdf');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
        self::assertInstanceOf(Chiffrage::class, $em->find(Chiffrage::class, $b->getId()));
    }
}
