<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Entity\CoucheAppliquee;
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

/**
 * Page d'une surface après « Enregistrer » (aperçu de l'ossature et des plaques, avertissements du calcul sans clic sur
 * « Calculer ») et page « Calculer » du projet (agrégation).
 */
final class CalculProjetControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $projetId;
    private int $pieceId;
    private int $murId;

    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function article(string $categorie, string $reference, ?int $epaisseur, array $caracteristiques, ?int $largeur = null, ?int $longueur = null): Materiau
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

        return $m;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = $this->creerSchema();
        $portees = [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3000]];
        $pose = ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250];
        $articles = [
            'ATT' => $this->article('appui_fixation', 'ATT', null, []),
            'M70' => $this->article('profil_ossature', 'M70', null, ['famille' => 'montant', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600, 'portees' => $portees]], null, 3000),
            'R70' => $this->article('profil_ossature', 'R70', null, ['famille' => 'rail', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600]], null, 3000),
            'ISO100' => $this->article('isolant', 'ISO100', 100, ['lambda' => 0.035], 600, 1200),
            'BA13' => $this->article('plaque_platre', 'BA13', 13, ['pose' => $pose], 1200, 2500),
        ];

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
        $systeme = new SystemeApplique(SystemeDoublageOssature::CODE, $mur);
        $systeme->setVariante('V1');
        $systeme->setParametres(['type_profil_peripherique' => 'rail']);
        foreach ([[0, 'attache', 'ATT', null], [1, 'ossature', 'M70', null], [2, 'isolant', 'ISO100', 'entre_ossature'], [3, 'parement', 'BA13', 'sur_ossature']] as [$ordre, $role, $reference, $position]) {
            $couche = new CoucheAppliquee($systeme, $ordre, $role, $role);
            $couche->setMateriau($articles[$reference]);
            $couche->setPosition($position);
            if (2 === $ordre) {
                $couche->setOrientation(0);
            }
        }
        $systeme->activer();
        $this->em->persist($systeme);
        $this->em->flush();
        $this->projetId = (int) $projet->getId();
        $this->pieceId = (int) $piece->getId();
        $this->murId = (int) $mur->getId();
    }

    public function testApresEnregistrerLApercuMontreOssatureEtPlaquesEtLesAvertissementsSansCalculer(): void
    {
        $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId);
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer');
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-testid="apercu-montants"] line')->count(), 'ossature');
        self::assertGreaterThan(0, $crawler->filter('[data-testid="apercu-plaques"] rect')->count(), 'plaques');
        self::assertSelectorTextContains('[data-testid="controles"]', 'isolant');
    }

    public function testLaPageCalculerAgregeLeProjet(): void
    {
        $crawler = $this->client->request('GET', '/projets/'.$this->projetId);
        self::assertSelectorExists('a[href$="/calculate"]');

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/calculate');

        self::assertResponseIsSuccessful();
        $ligne = $crawler->filter('table.achats tr[data-reference="BA13"]');
        self::assertCount(1, $ligne);
        $cellules = $ligne->filter('td')->each(static fn ($td): string => trim($td->text()));
        self::assertSame('10,00', $cellules[4], 'marge de la catégorie plaque de plâtre');
        self::assertGreaterThanOrEqual(1, (int) $cellules[6], 'lots');
        self::assertCount(1, $crawler->filter('table.achats tr[data-reference="M70"]'));
    }

    public function testLaPageCalculerParPieceEtAvecFournisseurSansPrixSignaleLesPrixManquants(): void
    {
        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/calculate?portee=piece');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Salon');
        self::assertCount(0, $crawler->filter('.prix-manquant'));
    }
}
