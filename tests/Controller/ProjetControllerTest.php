<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Cloison;
use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\CoteCloison;
use App\Enum\TypeSurface;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjetControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    public function testCreationDUnProjetEtDUnePiece(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->creerSchema();

        $client->request('GET', '/projets/nouveau');
        $client->submitForm('Enregistrer', ['projet[nom]' => 'Maison']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Maison');

        $projet = $em->getRepository(Projet::class)->findOneBy(['nom' => 'Maison']);
        self::assertInstanceOf(Projet::class, $projet);

        $client->request('GET', '/projets/'.$projet->getId().'/pieces/nouvelle');
        $client->submitForm('Enregistrer', ['piece[nom]' => 'Salon', 'piece[hauteurSousPlafond]' => '2,5']);
        self::assertResponseRedirects();

        $em->clear();
        $pieces = $em->getRepository(Piece::class)->findAll();
        self::assertCount(1, $pieces);
        self::assertSame(2500, $pieces[0]->getHauteurSousPlafond());
    }

    public function testLaSuppressionDUnProjetSupprimeToutEnCascade(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->creerSchema();

        $projet = new Projet();
        $projet->setNom('A supprimer');
        $piece = new Piece($projet);
        $piece->setNom('Chambre');
        $mur = new Surface($piece, TypeSurface::Mur);
        $cloison = new Cloison($piece);
        $face = new Surface($piece, TypeSurface::CloisonFace);
        $face->rattacherACloison($cloison, CoteCloison::A);
        $em->persist($projet);
        $em->persist($mur);
        $em->persist($face);
        $em->persist(new Ouverture($mur));
        $em->persist(new Ouverture(null, $cloison));
        $em->flush();
        $id = $projet->getId();
        $em->clear();

        $client->request('GET', '/projets/'.$id);
        $client->submitForm('Supprimer le projet');
        self::assertResponseRedirects('/projets');

        $em->clear();
        foreach ([Projet::class, Piece::class, Surface::class, Cloison::class, Ouverture::class] as $classe) {
            self::assertCount(0, $em->getRepository($classe)->findAll(), $classe);
        }
    }
}
