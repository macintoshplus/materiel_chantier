<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Entity\Cloison;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\TypeSurface;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Écran d'application d'un système du registre à un mur : saisie, calcul et chiffrage non vides, variantes,
 * validation et suppression.
 */
final class SystemeAppliqueControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $projetId;
    private int $pieceId;
    private int $murId;
    /** @var array<string, int> */
    private array $articles = [];

    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function article(string $categorie, string $reference, ?int $epaisseur, array $caracteristiques, ?int $largeur = null, ?int $longueur = null): void
    {
        $m = new Materiau();
        $m->setReference($reference);
        $m->setLibelle($reference);
        $m->setCategorie($categorie);
        $m->setEpaisseur($epaisseur);
        $m->setLargeur($largeur);
        $m->setLongueur($longueur);
        $m->setCaracteristiques($caracteristiques);
        $this->em->persist($m);
        $this->em->flush();
        $this->articles[$reference] = (int) $m->getId();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = $this->creerSchema();
        $portees = [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3000]];
        $pose = ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250];
        $this->article('appui_fixation', 'ATT', null, []);
        $this->article('profil_ossature', 'M70', null, ['famille' => 'montant', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600, 'portees' => $portees]], null, 3000);
        $this->article('profil_ossature', 'R70', null, ['famille' => 'rail', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600]], null, 3000);
        $this->article('suspente', 'SUSP', null, []);
        $this->article('profil_ossature', 'F47', null, ['famille' => 'fourrure', 'serie' => 47, 'pose' => ['entraxe_defaut' => 600]], null, 3000);
        $this->article('isolant', 'ISO70', 70, ['lambda' => 0.035], 600, 1200);
        $this->article('plaque_platre', 'BA13', 13, ['pose' => $pose], 1200, 2500);

        $projet = new Projet();
        $projet->setNom('Maison');
        $this->em->persist($projet);
        $piece = new Piece($projet);
        $piece->setNom('Salon');
        $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
        $piece->setHauteurSousPlafond(2500);
        (new PieceCreationService(new PieceJonctions()))->generer($piece);
        $projet->getPieces()->add($piece);
        $mur = null;
        foreach ($piece->getSurfaces() as $surface) {
            if (TypeSurface::Mur === $surface->getType() && null === $mur) {
                $mur = $surface;
            }
        }
        self::assertInstanceOf(Surface::class, $mur);
        $this->em->flush();
        $this->projetId = (int) $projet->getId();
        $this->pieceId = (int) $piece->getId();
        $this->murId = (int) $mur->getId();
    }

    private function urlNouveau(): string
    {
        return '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/systems/new?definition='.SystemeDoublageOssature::CODE;
    }

    /**
     * Enregistre un doublage complet (un article épinglé par emplacement).
     *
     * @param array<string, mixed> $extra valeurs de formulaire à forcer
     */
    private function appliquer(string $variante, array $extra = []): Crawler
    {
        $this->client->request('GET', $this->urlNouveau());
        self::assertResponseIsSuccessful();
        $valeurs = [
            'systeme_saisie[variante]' => $variante,
            'systeme_saisie[couches][attache][0][materiau]' => (string) $this->articles['ATT'],
            'systeme_saisie[couches][ossature][0][materiau]' => (string) $this->articles['M70'],
            'systeme_saisie[couches][isolant][0][materiau]' => (string) $this->articles['ISO70'],
            'systeme_saisie[couches][parement][0][materiau]' => (string) $this->articles['BA13'],
        ];
        $valeurs = $extra + $valeurs;

        return $this->client->submitForm('Enregistrer', $valeurs);
    }

    /**
     * @return list<SystemeApplique>
     */
    private function systemes(): array
    {
        $this->em->clear();
        $mur = $this->em->find(Surface::class, $this->murId);
        self::assertInstanceOf(Surface::class, $mur);

        return array_values($mur->getSystemes()->toArray());
    }

    public function testLeLienDeLaSurfaceMeneALaListeEtAuxDefinitionsCompatibles(): void
    {
        $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId);
        $this->client->clickLink('Gérer les systèmes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="aucun-systeme"]');
        self::assertSelectorExists('a[data-definition="'.SystemeDoublageOssature::CODE.'"]');
        self::assertSelectorNotExists('a[data-definition="cloison_ossature"]');
    }

    public function testAppliquerUnSystemeAlimenteLeCalculEtLeChiffrage(): void
    {
        $this->appliquer('V1');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorNotExists('.flash-warning');
        self::assertSelectorExists('[data-testid="calcul-ok"]');
        self::assertResponseIsSuccessful();

        $systemes = $this->systemes();
        self::assertCount(1, $systemes);
        self::assertTrue($systemes[0]->isActif(), 'le premier système du support est actif');
        self::assertCount(4, $systemes[0]->getCouches());

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/calculate');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('table.achats tr[data-reference="BA13"]')->count());
        self::assertGreaterThan(0, $crawler->filter('table.achats tr[data-reference="M70"]')->count());

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/chiffrages');
        self::assertStringContainsString('BA13', $crawler->filter('fieldset')->text());
        $this->client->submitForm('Créer le chiffrage', ['libelle' => 'Devis']);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertGreaterThan(0, $crawler->filter('table[data-testid="lignes-chiffrage"] tbody tr')->count());
    }

    public function testDeuxVariantesPuisActivation(): void
    {
        $this->appliquer('V1');
        $this->appliquer('V2');
        self::assertResponseRedirects();

        $systemes = $this->systemes();
        self::assertCount(2, $systemes);
        self::assertSame(['V1', 'V2'], array_map(static fn (SystemeApplique $s): string => $s->getVariante(), $systemes));
        self::assertSame([true, false], array_map(static fn (SystemeApplique $s): bool => $s->isActif(), $systemes));

        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/systems');
        self::assertCount(2, $crawler->filter('table[data-testid="systemes"] tbody tr'));
        $this->client->submitForm('Activer');
        self::assertResponseRedirects();

        self::assertSame([false, true], array_map(static fn (SystemeApplique $s): bool => $s->isActif(), $this->systemes()));
        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/variants');
        self::assertCount(2, $crawler->filter('table[data-testid="variantes"] tbody tr'));
    }

    public function testUneVarianteSansNomEstRefusee(): void
    {
        $this->appliquer('');
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->systemes());
    }

    public function testLaCardinaliteDUnEmplacementEstControlee(): void
    {
        // Trois parements pour un maximum de deux.
        $crawler = $this->client->request('GET', $this->urlNouveau());
        $form = $crawler->selectButton('Enregistrer')->form();
        $valeurs = $form->getPhpValues();
        $parement = $valeurs['systeme_saisie']['couches']['parement'][0];
        $valeurs['systeme_saisie']['couches']['parement'] = [$parement, $parement, $parement];
        $valeurs['systeme_saisie']['variante'] = 'Trop';
        $this->client->request('POST', $this->urlNouveau(), $valeurs);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->systemes());
    }

    public function testLesArticlesProposesSontFiltresParSpecificationEtUnEmplacementPeutResterNonEpingle(): void
    {
        $crawler = $this->client->request('GET', $this->urlNouveau());
        $options = static fn (string $emplacement): array => $crawler->filter('select[name="systeme_saisie[couches]['.$emplacement.'][0][materiau]"] option')->each(static fn (Crawler $o): string => $o->attr('value') ?? '');
        self::assertSame(['', (string) $this->articles['BA13']], $options('parement'));
        self::assertSame(['', (string) $this->articles['M70']], $options('ossature'), 'seuls les montants conviennent à l\'ossature');

        $this->appliquer('V1', ['systeme_saisie[couches][isolant][0][materiau]' => '']);
        self::assertResponseRedirects();
        $systemes = $this->systemes();
        self::assertCount(1, $systemes);
        $isolants = $systemes[0]->getCouches()->filter(static fn ($c): bool => 'isolant' === $c->getRole())->getValues();
        self::assertCount(1, $isolants);
        self::assertNull($isolants[0]->getMateriau());
    }

    public function testSupprimerLeSystemeActifSignaleLesVariantesRestantes(): void
    {
        $this->appliquer('V1');
        $this->appliquer('V2');
        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/systems');
        $this->client->submit($crawler->filter('table[data-testid="systemes"] tbody tr[data-variante="V1"] form[action$="/delete"] button')->form());
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('table[data-testid="systemes"] tbody tr'));
        self::assertStringContainsString('active', $crawler->filter('.flash-warning')->text());
        self::assertCount(1, $this->systemes());
    }

    public function testUneDefinitionIncompatibleOuInconnueDonne404(): void
    {
        $base = '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/systems/new';
        $this->client->request('GET', $base.'?definition=cloison_ossature');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $base.'?definition=inconnue');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUneCloisonRecoitUnSystemeEtAlimenteLeCalcul(): void
    {
        $base = '/rooms/'.$this->pieceId.'/partitions';
        $crawler = $this->client->request('GET', $base.'/new');
        $valeurs = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $valeurs['cloison_saisie'] = array_merge($valeurs['cloison_saisie'], ['debutX' => '0', 'debutY' => '1500', 'finX' => '2000', 'finY' => '1500']);
        $this->client->request('POST', $base.'/new', $valeurs);
        self::assertResponseRedirects();
        $this->em->clear();
        $cloison = $this->em->getRepository(Cloison::class)->findAll()[0];
        $id = (int) $cloison->getId();

        $this->client->request('GET', $base.'/'.$id);
        $this->client->clickLink('Gérer les systèmes');
        self::assertSelectorExists('a[data-definition="cloison_ossature"]');
        self::assertSelectorNotExists('a[data-definition="'.SystemeDoublageOssature::CODE.'"]');
        $this->client->clickLink('Cloison sur ossature métallique');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="systeme_saisie[couches][parement][0][face]"]');

        $this->client->submitForm('Enregistrer', [
            'systeme_saisie[couches][ossature][0][materiau]' => (string) $this->articles['M70'],
            'systeme_saisie[couches][parement][0][materiau]' => (string) $this->articles['BA13'],
            'systeme_saisie[couches][parement][1][materiau]' => (string) $this->articles['BA13'],
        ]);
        self::assertResponseRedirects();

        $this->em->clear();
        $cloison = $this->em->find(Cloison::class, $id);
        self::assertInstanceOf(Cloison::class, $cloison);
        self::assertCount(1, $cloison->getSystemes());
        self::assertTrue($cloison->getSystemes()->getValues()[0]->isActif());

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/calculate');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('table.achats tr[data-reference="BA13"]')->count());
    }

    public function testUnPlafondRecoitUnSystemeEtAlimenteLeCalcul(): void
    {
        $plafond = $this->em->getRepository(Surface::class)->findOneBy(['type' => TypeSurface::PlafondPan]);
        if (!$plafond instanceof Surface) {
            self::markTestSkipped('La pièce générée n\'a pas de plafond.');
        }
        $base = '/rooms/'.$this->pieceId.'/walls/'.$plafond->getId().'/systems';
        $this->client->request('GET', $base);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[data-definition="plafond_suspendu_placo"]');
        $this->client->request('GET', $base.'/new?definition=plafond_suspendu_placo');
        $this->client->submitForm('Enregistrer', [
            'systeme_saisie[couches][suspente][0][materiau]' => (string) $this->articles['SUSP'],
            'systeme_saisie[couches][ossature][0][materiau]' => (string) $this->articles['F47'],
            'systeme_saisie[couches][parement][0][materiau]' => (string) $this->articles['BA13'],
        ]);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/calculate');
        self::assertGreaterThan(0, $crawler->filter('table.achats tr[data-reference="BA13"]')->count());
        self::assertGreaterThan(0, $crawler->filter('table.achats tr[data-reference="F47"]')->count());
    }
}
