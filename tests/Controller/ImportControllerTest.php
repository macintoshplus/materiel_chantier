<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Piece;
use App\Entity\Projet;
use App\Tests\Import\Fixtures;
use App\Tests\Support\BaseDeDonneesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    private KernelBrowser $client;
    private int $projetId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('Maison');
        $em->persist($projet);
        $em->flush();
        $this->projetId = (int) $projet->getId();
    }

    private function envoyer(string $source, string $chemin, string $nom, ?string $mime = null): void
    {
        $copie = tempnam(sys_get_temp_dir(), 'imp');
        self::assertIsString($copie);
        copy($chemin, $copie);
        $this->client->request('POST', '/projets/'.$this->projetId.'/pieces/import/'.$source, [], [
            'import_fichier' => ['fichier' => new UploadedFile($copie, $nom, $mime, null, true)],
        ]);
    }

    private function jeton(): string
    {
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertSame(1, preg_match('#/import/([0-9a-f]{32})/parametres#', $location, $m), $location);

        return $m[1];
    }

    public function testLesEntreesSontProposeesSurLeProjet(): void
    {
        $this->client->request('GET', '/projets/'.$this->projetId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href$="/pieces/import/dxf"]');
        self::assertSelectorExists('a[href$="/pieces/import/csv"]');
        self::assertSelectorExists('a[href$="/pieces/cotes"]');
        foreach (['dxf', 'csv'] as $s) {
            $this->client->request('GET', '/projets/'.$this->projetId.'/pieces/import/'.$s);
            self::assertResponseIsSuccessful();
        }
    }

    public function testImportDxfDeBoutEnBout(): void
    {
        $this->envoyer('dxf', Fixtures::chemin('dxf/rectangle.dxf'), 'rectangle.dxf');
        self::assertResponseRedirects();
        $jeton = $this->jeton();
        $base = '/projets/'.$this->projetId.'/pieces/import/'.$jeton;

        $this->client->request('GET', $base.'/parametres');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg');

        // changement d'unité sans nouvel envoi
        $this->client->submitForm('Appliquer', ['import_parametres[unite]' => 'cm']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Appliquer', ['import_parametres[unite]' => 'mm']);
        $this->client->followRedirect();
        $this->client->submitForm('Continuer');
        self::assertResponseRedirects($base.'/contour');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Continuer', ['import_contour[epaisseur]' => '100', 'import_contour[hauteur]' => '2500', 'import_contour[nom]' => 'Salon']);
        self::assertResponseRedirects($base.'/apercu');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(0, self::getContainer()->get('doctrine')->getManager()->getRepository(Piece::class)->findAll());

        $this->client->submitForm('Créer la pièce');
        self::assertResponseRedirects();
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $pieces = $em->getRepository(Piece::class)->findAll();
        self::assertCount(1, $pieces);
        self::assertSame('Salon', $pieces[0]->getNom());
        self::assertSame('/rooms/'.$pieces[0]->getId(), $this->client->getResponse()->headers->get('Location'));
        self::assertFileDoesNotExist(self::getContainer()->getParameter('kernel.project_dir').'/var/import/'.$jeton.'.dat');
    }

    public function testImportCsvDeBoutEnBout(): void
    {
        $this->envoyer('csv', Fixtures::chemin('csv/rectangle_xy.csv'), 'r.csv');
        self::assertResponseRedirects();
        $base = '/projets/'.$this->projetId.'/pieces/import/'.$this->jeton();
        $this->client->request('GET', $base.'/parametres');
        $this->client->submitForm('Continuer');
        $this->client->followRedirect();
        $this->client->submitForm('Continuer');
        self::assertResponseRedirects($base.'/apercu');
        $this->client->followRedirect();
        $this->client->submitForm('Créer la pièce');
        self::assertResponseRedirects();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertCount(1, $em->getRepository(Piece::class)->findAll());
    }

    public function testApercuNonAtteignableSansContour(): void
    {
        $this->envoyer('dxf', Fixtures::chemin('dxf/rectangle.dxf'), 'rectangle.dxf');
        $base = '/projets/'.$this->projetId.'/pieces/import/'.$this->jeton();
        $this->client->request('GET', $base.'/apercu');
        self::assertResponseRedirects($base.'/contour');
        $this->client->request('GET', $base.'/contour');
        self::assertResponseRedirects($base.'/parametres');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function fichiersInvalides(): iterable
    {
        yield 'binaire' => ['dxf', 'dxf/binaire.dxf', 'binaire.dxf'];
        yield 'corrompu' => ['dxf', 'dxf/corrompu.dxf', 'corrompu.dxf'];
        yield 'mauvaise extension' => ['dxf', 'csv/rectangle_xy.csv', 'plan.txt'];
        yield 'csv vide' => ['csv', 'csv/vide.csv', 'vide.csv'];
    }

    #[DataProvider('fichiersInvalides')]
    public function testFichierInvalideAfficheUneErreurExplicite(string $source, string $fixture, string $nom): void
    {
        $this->envoyer($source, Fixtures::chemin($fixture), $nom);
        if ('csv/vide.csv' === $fixture) {
            // un CSV vide est refusé à l'étape des paramètres
            $this->client->followRedirect();
            self::assertSelectorExists('.invalid-feedback, .alert-danger, .form-error-message');

            return;
        }
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback, .alert-danger, .form-error-message');
    }

    public function testFichierTropVolumineux(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'big');
        self::assertIsString($chemin);
        file_put_contents($chemin, str_repeat("0\nTEXT\n", 1_600_000));
        $this->envoyer('dxf', $chemin, 'gros.dxf');
        unlink($chemin);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'volumineux');
    }

    public function testJetonInconnuOuExpire(): void
    {
        $this->client->request('GET', '/projets/'.$this->projetId.'/pieces/import/'.str_repeat('a', 32).'/parametres');
        self::assertResponseRedirects();
    }

    public function testAnnulation(): void
    {
        $this->envoyer('dxf', Fixtures::chemin('dxf/rectangle.dxf'), 'rectangle.dxf');
        $jeton = $this->jeton();
        $this->client->request('GET', '/projets/'.$this->projetId.'/pieces/import/'.$jeton.'/parametres');
        $this->client->submitForm('Annuler l\'import');
        self::assertResponseRedirects('/projets/'.$this->projetId);
        self::assertFileDoesNotExist(self::getContainer()->getParameter('kernel.project_dir').'/var/import/'.$jeton.'.dat');
    }

    public function testCotesEtAnglesEcartDeFermetureEtCreation(): void
    {
        $this->client->request('GET', '/projets/'.$this->projetId.'/pieces/cotes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button:contains("Enregistrer")');

        $this->client->submitForm('Enregistrer', ['cotes_angles[lignes][2][cote]' => '3990']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('svg');
        self::assertSelectorTextContains('body', 'écart');

        $this->client->submitForm('Créer la pièce', ['cotes_angles[lignes][2][cote]' => '4000', 'cotes_angles[nom]' => 'Bureau']);
        self::assertResponseRedirects();
        $em = self::getContainer()->get('doctrine')->getManager();
        $pieces = $em->getRepository(Piece::class)->findAll();
        self::assertCount(1, $pieces);
        self::assertSame('Bureau', $pieces[0]->getNom());
    }
}
