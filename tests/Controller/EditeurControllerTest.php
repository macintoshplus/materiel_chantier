<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Cloison;
use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\TypeSurface;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EditeurControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $pieceId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('Maison');
        $this->em->persist($projet);
        $this->em->flush();

        $this->client->request('GET', '/projets/'.$projet->getId().'/pieces/nouvelle');
        $this->client->submitForm('Enregistrer', ['nouvelle_piece[nom]' => 'Salon']);
        self::assertResponseRedirects();
        $this->em->clear();
        $piece = $this->em->getRepository(Piece::class)->findOneBy(['nom' => 'Salon']);
        self::assertInstanceOf(Piece::class, $piece);
        $this->pieceId = (int) $piece->getId();
    }

    private function mur(int $rang = 0): Surface
    {
        $this->em->clear();
        $murs = $this->em->getRepository(Surface::class)->findBy(['piece' => $this->pieceId, 'type' => TypeSurface::Mur], ['id' => 'ASC']);

        return $murs[$rang];
    }

    public function testLaPageDeLaPieceAfficheApercuMursEtCloisons(): void
    {
        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg.apercu');
        self::assertGreaterThanOrEqual(4, $crawler->filter('li[data-highlight-key^="wall-"]')->count());
        self::assertSelectorExists('a[href$="/partitions/new"]');
    }

    public function testRenommerLaPieceEnregistreSansConfirmation(): void
    {
        $this->client->request('GET', '/rooms/'.$this->pieceId);
        $this->client->submitForm('Enregistrer', ['piece_saisie[nom]' => 'Séjour']);
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Séjour', $this->em->find(Piece::class, $this->pieceId)?->getNom());
    }

    public function testUneSeuleOperationDeSommetParEnregistrement(): void
    {
        $this->client->request('GET', '/rooms/'.$this->pieceId);
        $this->client->submitForm('Enregistrer', [
            'piece_saisie[sommets][1][x]' => '4200',
            'piece_saisie[sommets][2][x]' => '4200',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDeplacerUnSommetAvecCloisonDemandeConfirmation(): void
    {
        $base = '/rooms/'.$this->pieceId.'/partitions';
        $crawler = $this->client->request('GET', $base.'/new');
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['cloison_saisie'] = array_merge($values['cloison_saisie'], ['debutX' => '0', 'debutY' => '1500', 'finX' => '2000', 'finY' => '1500']);
        $this->client->request('POST', $base.'/new', $values);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId);
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['piece_saisie']['sommets'][2]['x'] = '5000';
        $this->client->request('POST', '/rooms/'.$this->pieceId, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('input[name="_confirmer"]');
        $this->em->clear();
        self::assertSame(4000, $this->em->find(Piece::class, $this->pieceId)?->getFormeSol()[2]['x']);

        $values['_confirmer'] = '1';
        $this->client->request('POST', '/rooms/'.$this->pieceId, $values);
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame(5000, $this->em->find(Piece::class, $this->pieceId)?->getFormeSol()[2]['x']);
    }

    public function testLaPageMurPermetDeRenommerEtAjouterUneOuverture(): void
    {
        $mur = $this->mur();
        $url = '/rooms/'.$this->pieceId.'/walls/'.$mur->getId();
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg.apercu');
        self::assertGreaterThan(0, $crawler->filter('a:contains("Mur suivant")')->count());

        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['surface_saisie']['nom'] = 'Mur nord';
        $values['surface_saisie']['ouvertures'][0] = [
            'nom' => '', 'type' => 'fenetre', 'largeur' => '1000', 'hauteur' => '1200',
            'u' => '500', 'v' => '900', 'typePose' => 'tunnel', 'refoncement' => '100',
        ];
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();

        $this->em->clear();
        $mur = $this->em->find(Surface::class, $mur->getId());
        self::assertSame('Mur nord', $mur?->getNom());
        $ouvertures = $this->em->getRepository(Ouverture::class)->findAll();
        self::assertCount(1, $ouvertures);
        self::assertSame('Window 1', $ouvertures[0]->getNom());
        self::assertSame(100, $ouvertures[0]->getRefoncement());

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg.apercu .retour');
    }

    public function testUneOuvertureQuiDepasseSignaleSansBloquer(): void
    {
        $mur = $this->mur();
        $url = '/rooms/'.$this->pieceId.'/walls/'.$mur->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['surface_saisie']['ouvertures'][0] = [
            'nom' => 'Trop grande', 'type' => 'porte', 'largeur' => '9000', 'hauteur' => '2000',
            'u' => '0', 'v' => '0', 'typePose' => 'applique', 'refoncement' => '0',
        ];
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('[data-testid="controles"]');
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Ouverture::class)->findAll());
    }

    public function testLaRedirectionContinuerSeLimiteAuSite(): void
    {
        $mur = $this->mur();
        $url = '/rooms/'.$this->pieceId.'/walls/'.$mur->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['_continue'] = '/rooms/'.$this->pieceId;
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects('/rooms/'.$this->pieceId);

        $values['_continue'] = '//evil.example';
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects();
        self::assertStringNotContainsString('evil', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testUneSurfaceDUneAutrePieceEstIntrouvable(): void
    {
        $mur = $this->mur();
        $this->client->request('GET', '/rooms/'.($this->pieceId + 99).'/walls/'.$mur->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreationModificationEtSuppressionDUneCloison(): void
    {
        $base = '/rooms/'.$this->pieceId.'/partitions';
        $crawler = $this->client->request('GET', $base.'/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller~="plan-picker"]');

        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['cloison_saisie'] = array_merge($values['cloison_saisie'], [
            'debutX' => '0', 'debutY' => '1500', 'finX' => '2000', 'finY' => '1500',
        ]);
        $this->client->request('POST', $base.'/new', $values);
        self::assertResponseRedirects();

        $this->em->clear();
        $cloisons = $this->em->getRepository(Cloison::class)->findAll();
        self::assertCount(1, $cloisons);
        $id = $cloisons[0]->getId();
        self::assertSame('Partition 1', $cloisons[0]->getNom());

        foreach (['A', 'B'] as $cote) {
            $this->client->request('GET', $base.'/'.$id.'?side='.$cote);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('svg.apercu');
        }

        $this->client->request('GET', '/rooms/'.$this->pieceId);
        self::assertSelectorExists('li[data-highlight-key="partition-'.$id.'"]');

        $crawler = $this->client->request('GET', $base.'/'.$id);
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Cloison::class)->findAll());
    }

    /**
     * @param array<string, string> $champs
     */
    private function creerCloison(array $champs): void
    {
        $base = '/rooms/'.$this->pieceId.'/partitions/new';
        $crawler = $this->client->request('GET', $base);
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['cloison_saisie'] = array_merge($values['cloison_saisie'], $champs);
        $this->client->request('POST', $base, $values);
        self::assertResponseRedirects();
    }

    public function testSupprimerUneCloisonSignaleLesCloisonsAncreesDessus(): void
    {
        $this->creerCloison(['debutX' => '0', 'debutY' => '1500', 'finX' => '3000', 'finY' => '1500']);
        $this->creerCloison(['debutX' => '1000', 'debutY' => '1500', 'finX' => '1000', 'finY' => '2800']);
        $this->em->clear();
        $premiere = $this->em->getRepository(Cloison::class)->findOneBy([], ['id' => 'ASC']);
        self::assertInstanceOf(Cloison::class, $premiere);

        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId.'/partitions/'.$premiere->getId());
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.flash-warning');
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Cloison::class)->findAll());
    }

    public function testUnPanOuUnPlafondUtiliseLaPageDeSurface(): void
    {
        $this->em->clear();
        $plafond = $this->em->getRepository(Surface::class)->findOneBy(['piece' => $this->pieceId, 'type' => TypeSurface::PlafondPan]);
        if (null === $plafond) {
            self::markTestSkipped('Pas de plafond généré par défaut.');
        }
        $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$plafond->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg.apercu');
    }
}
