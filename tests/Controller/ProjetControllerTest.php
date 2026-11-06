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
        $client->submitForm('Enregistrer', ['nouvelle_piece[nom]' => 'Salon', 'nouvelle_piece[hauteurSousPlafond]' => '2500']);
        self::assertResponseRedirects();

        $em->clear();
        $pieces = $em->getRepository(Piece::class)->findAll();
        self::assertCount(1, $pieces);
        self::assertSame(2500, $pieces[0]->getHauteurSousPlafond());
        self::assertSame(['x' => 4000, 'y' => 3000], $pieces[0]->getFormeSol()[2]);
    }

    public function testLeContourAuSolPeutEtreSaisiParSommets(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('Maison');
        $em->persist($projet);
        $em->flush();

        $client->request('GET', '/projets/'.$projet->getId().'/pieces/nouvelle');
        $client->submitForm('Enregistrer', [
            'nouvelle_piece[nom]' => 'Salon',
            'nouvelle_piece[mode]' => 'sommets',
            'nouvelle_piece[formeSol][0][x]' => '0', 'nouvelle_piece[formeSol][0][y]' => '0',
            'nouvelle_piece[formeSol][1][x]' => '4500', 'nouvelle_piece[formeSol][1][y]' => '0',
            'nouvelle_piece[formeSol][2][x]' => '4500', 'nouvelle_piece[formeSol][2][y]' => '3000',
            'nouvelle_piece[formeSol][3][x]' => '0', 'nouvelle_piece[formeSol][3][y]' => '3000',
        ]);
        self::assertResponseRedirects();

        $em->clear();
        $piece = $em->getRepository(Piece::class)->findOneBy(['nom' => 'Salon']);
        self::assertSame(['x' => 4500, 'y' => 3000], $piece?->getFormeSol()[2]);
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
