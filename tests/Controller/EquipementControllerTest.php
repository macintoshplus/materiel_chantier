<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Equipement;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Entity\Surface;
use App\Enum\TypeCircuit;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Écrans des équipements (page d'une surface) et des circuits. Pièce 4000 x 3000, plafond 2500 ; mur 4 : u = y, v = z.
 */
final class EquipementControllerTest extends WebTestCase
{
    use BaseDeDonneesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $projetId;
    private int $pieceId;
    private int $murId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = $this->creerSchema();
        foreach ([['electrique_gaine', 'ICTA16', ['diametre' => 16, 'taux_remplissage_max' => 40, 'conducteurs_max' => [['section' => '1.5', 'nombre' => 5], ['section' => '2.5', 'nombre' => 3]]], 50_000],
            ['electrique_cable', 'CABLE3G15', ['section' => 1.5, 'nb_conducteurs' => 3], 100_000]] as [$categorie, $reference, $caracteristiques, $longueur]) {
            $m = new Materiau();
            $m->setReference($reference);
            $m->setLibelle($reference);
            $m->setCategorie($categorie);
            $m->setLongueur($longueur);
            $m->setUnitesParLot(1);
            $m->setCaracteristiques($caracteristiques);
            $this->em->persist($m);
        }
        $projet = new Projet();
        $projet->setNom('Maison');
        $this->em->persist($projet);
        $piece = new Piece($projet);
        $piece->setNom('Salon');
        $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
        $piece->setHauteurSousPlafond(2500);
        (new PieceCreationService(new PieceJonctions()))->generer($piece);
        $this->em->flush();
        $this->projetId = (int) $projet->getId();
        $this->pieceId = (int) $piece->getId();
        $this->murId = (int) $this->mur('Wall 4')->getId();
    }

    private function mur(string $nom): Surface
    {
        $surface = $this->em->getRepository(Surface::class)->findOneBy(['piece' => $this->pieceId, 'nom' => $nom]);
        self::assertInstanceOf(Surface::class, $surface);

        return $surface;
    }

    private function ajouter(string $type, int $u, int $v, ?Reseau $reseau = null): Equipement
    {
        $piece = $this->em->find(Piece::class, $this->pieceId);
        self::assertInstanceOf(Piece::class, $piece);
        $e = new Equipement($piece, $this->mur('Wall 4'));
        $e->setType($type);
        $e->placer($u, $v);
        $e->setReseau($reseau);
        $this->em->persist($e);
        $this->em->flush();

        return $e;
    }

    private function urlAjout(string $famille = 'electrique'): string
    {
        return '/rooms/'.$this->pieceId.'/walls/'.$this->murId.'/equipments/'.$famille.'/new';
    }

    public function testLaPageDeLaSurfaceListeLesEquipementsEtAfficheUnMarqueurParEquipement(): void
    {
        $this->ajouter('tableau', 1000, 1800);
        $this->ajouter('prise', 2000, 300);

        $crawler = $this->client->request('GET', '/rooms/'.$this->pieceId.'/walls/'.$this->murId);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-testid="liste-equipements"] li'));
        self::assertCount(2, $crawler->filter('[data-testid="marqueur-equipement"]'));
        self::assertSelectorExists('[data-testid="ajout-equipement-electrique"]');
        self::assertSelectorNotExists('[data-testid="ajout-equipement-plomberie"]');
        self::assertSelectorExists('[data-testid="lien-reseaux"]');
    }

    public function testAjouterUnEquipement(): void
    {
        $this->client->request('GET', $this->urlAjout().'?type=prise');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', [
            'equipement[type]' => 'prise',
            'equipement[nom]' => 'Prise du bureau',
            'equipement[u]' => '1500',
            'equipement[v]' => '300',
            'equipement[puissanceW]' => '2300',
        ]);
        self::assertResponseRedirects('/rooms/'.$this->pieceId.'/walls/'.$this->murId);

        $this->em->clear();
        $e = $this->em->getRepository(Equipement::class)->findOneBy(['nom' => 'Prise du bureau']);
        self::assertInstanceOf(Equipement::class, $e);
        self::assertSame(1500, $e->getU());
        self::assertSame(300, $e->getV());
        self::assertSame('prise', $e->getType());
        self::assertSame(2300, $e->getParametres()['puissance_w'] ?? null);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Équipement enregistré.');
    }

    public function testModifierUnEquipement(): void
    {
        $e = $this->ajouter('prise', 1500, 300);
        $id = (int) $e->getId();

        $this->client->request('GET', '/equipments/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', ['equipement[u]' => '1700', 'equipement[v]' => '400']);
        self::assertResponseRedirects();

        $this->em->clear();
        $modifie = $this->em->find(Equipement::class, $id);
        self::assertSame([1700, 400], [$modifie?->getU(), $modifie?->getV()]);
    }

    public function testSupprimerUnEquipementLeRetireDuCircuit(): void
    {
        $reseau = new Reseau($this->em->find(Projet::class, $this->projetId) ?? self::fail(), TypeCircuit::Prises);
        $reseau->setNom('Sockets');
        $this->em->persist($reseau);
        $tableau = $this->ajouter('tableau', 1000, 1800);
        $prise = $this->ajouter('prise', 2000, 300, $reseau);
        $reseau->definirDepart((int) $tableau->getId());
        $reseau->relier((int) $prise->getId(), (int) $tableau->getId());
        $this->em->flush();
        $id = (int) $prise->getId();

        $this->client->request('GET', '/equipments/'.$id.'/edit');
        $this->client->submitForm('Supprimer cet équipement');
        self::assertResponseRedirects();

        $this->em->clear();
        self::assertNull($this->em->find(Equipement::class, $id));
        $reseau = $this->em->getRepository(Reseau::class)->findOneBy(['nom' => 'Sockets']);
        self::assertInstanceOf(Reseau::class, $reseau);
        self::assertSame([], $reseau->getLiaisons());
    }

    public function testFormulaireInvalideRepond422(): void
    {
        $this->client->request('GET', $this->urlAjout());
        $this->client->submitForm('Enregistrer', ['equipement[type]' => '', 'equipement[u]' => '10', 'equipement[v]' => '10']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Equipement::class)->count([]));
    }

    public function testRessourcesInconnuesOuIncoherentesRepondent404(): void
    {
        $this->client->request('GET', '/equipments/9999/edit');
        self::assertResponseStatusCodeSame(404);

        // Famille sans type d'élément (étape 08) : pas d'écran.
        $this->client->request('GET', $this->urlAjout('plomberie'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/rooms/9999/walls/'.$this->murId.'/equipments/electrique/new');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/reseaux/9999/edit');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/projets/'.$this->projetId.'/reseaux/plomberie');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/projets/9999/reseaux/electrique');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreerUnCircuitAfficheLongueursQuantitesEtControles(): void
    {
        $tableau = $this->ajouter('tableau', 1000, 1800);
        $inter = $this->ajouter('interrupteur', 2500, 1100);
        $tableauId = (int) $tableau->getId();
        $interId = (int) $inter->getId();

        $crawler = $this->client->request('GET', '/projets/'.$this->projetId.'/reseaux/electrique/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $valeurs = $form->getPhpValues();
        $valeurs['reseau']['nom'] = 'Lighting';
        $valeurs['reseau']['type'] = 'eclairage';
        $valeurs['reseau']['section'] = '1.5';
        $valeurs['reseau']['protection'] = '10';
        $valeurs['reseau']['depart'] = (string) $tableauId;
        $valeurs['reseau']['equipements'] = [(string) $interId];
        $valeurs['reseau']['liaisons'] = [['equipement' => (string) $interId, 'parent' => (string) $tableauId, 'conducteurs' => '']];
        $this->client->request('POST', '/projets/'.$this->projetId.'/reseaux/electrique/new', $valeurs);
        self::assertResponseRedirects('/projets/'.$this->projetId.'/reseaux/electrique');

        $this->em->clear();
        $reseau = $this->em->getRepository(Reseau::class)->findOneBy(['nom' => 'Lighting']);
        self::assertInstanceOf(Reseau::class, $reseau);
        self::assertSame($tableauId, $reseau->getDepart());
        self::assertCount(1, $reseau->getLiaisons());

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[data-testid="reseau"] h2', 'Lighting');
        self::assertSelectorTextContains('[data-testid="gaine"]', '3.60 m');
        self::assertSelectorExists('[data-testid="cable"]');
        self::assertSelectorExists('[data-testid="quantites"] li');
        self::assertSelectorExists('[data-testid="controles-generaux"]');
    }

    public function testModifierEtSupprimerUnCircuit(): void
    {
        $reseau = new Reseau($this->em->find(Projet::class, $this->projetId) ?? self::fail(), TypeCircuit::Prises);
        $reseau->setNom('Sockets');
        $this->em->persist($reseau);
        $this->em->flush();
        $id = (int) $reseau->getId();

        $this->client->request('GET', '/reseaux/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', ['reseau[nom]' => 'Kitchen', 'reseau[section]' => '2.5', 'reseau[protection]' => '20']);
        self::assertResponseRedirects();
        $this->em->clear();
        $modifie = $this->em->find(Reseau::class, $id);
        self::assertSame('Kitchen', $modifie?->getNom());

        $this->client->request('GET', '/projets/'.$this->projetId.'/reseaux/electrique');
        $this->client->submitForm('Supprimer');
        self::assertResponseRedirects('/projets/'.$this->projetId.'/reseaux/electrique');
        $this->em->clear();
        self::assertNull($this->em->find(Reseau::class, $id));
    }

    public function testCircuitInvalideRepond422(): void
    {
        $this->client->request('GET', '/projets/'.$this->projetId.'/reseaux/electrique/new');
        $this->client->submitForm('Enregistrer', ['reseau[nom]' => '', 'reseau[protection]' => '0']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Reseau::class)->count([]));
    }

    public function testLaPageDuProjetLieLesCircuits(): void
    {
        $this->client->request('GET', '/projets/'.$this->projetId);
        self::assertSelectorExists('a[data-testid="lien-reseaux"]');
    }
}
