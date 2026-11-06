<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Agregation\LigneAchat;
use App\Entity\CoucheAppliquee;
use App\Entity\Equipement;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\TypeCircuit;
use App\Enum\TypeStrategie;
use App\Enum\TypeSurface;
use App\Service\CalculProjet;
use App\Service\ChiffrageService;
use App\Service\ControleElectricite;
use App\Service\ElectriciteProjet;
use App\Service\GroupeAchats;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Service\PorteeAgregation;
use App\Service\PrixService;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Électricité avec la base : persistance des équipements et des réseaux, quantités annexes dans la nomenclature du
 * projet (même agrégation que les systèmes de surface), va-et-vient, chiffrage.
 *
 * Pièce 4000 x 3000, plafond à 2500. Mur 4 (x = 0) : u = y, v = z. Plafond : (u, v) -> (u, -v).
 * Tableau (mur 4, u 1000, v 1800) ; interrupteur (mur 4, u 2500, v 1100) ; point lumineux (plafond, u 2000, v -1500).
 * Tableau -> interrupteur : 3600 mm ; interrupteur -> point lumineux : 4400 mm (voir CheminementCalculatorTest).
 */
final class ElectriciteTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /** @var array<string, Materiau> */
    private array $articles = [];

    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function article(string $categorie, string $reference, array $caracteristiques, ?int $longueur = null): void
    {
        $m = new Materiau();
        $m->setReference($reference);
        $m->setLibelle($reference);
        $m->setCategorie($categorie);
        $m->setLongueur($longueur);
        $m->setUnitesParLot(1);
        $m->setCaracteristiques($caracteristiques);
        $this->em->persist($m);
        $this->articles[$reference] = $m;
    }

    private function catalogue(bool $complet = true): void
    {
        $this->article('electrique_gaine', 'ICTA16', ['diametre' => 16, 'taux_remplissage_max' => 40, 'conducteurs_max' => [['section' => '1.5', 'nombre' => 5], ['section' => '2.5', 'nombre' => 3]]], 50_000);
        $this->article('electrique_gaine', 'ICTA20', ['diametre' => 20, 'taux_remplissage_max' => 40, 'conducteurs_max' => [['section' => '1.5', 'nombre' => 8], ['section' => '2.5', 'nombre' => 5]]], 50_000);
        $this->article('electrique_cable', 'CABLE3G15', ['section' => 1.5, 'nb_conducteurs' => 3], 100_000);
        $this->article('electrique_cable', 'CABLE4G15', ['section' => 1.5, 'nb_conducteurs' => 4], 100_000);
        $this->article('electrique_boite', 'BOITE-ENC', ['type_boite' => 'encastrement', 'profondeur' => 40, 'diametre' => 67]);
        $this->article('electrique_boite', 'BOITE-CREUSE', ['type_boite' => 'cloison_creuse', 'profondeur' => 40, 'diametre' => 67]);
        $this->article('electrique_appareillage', 'INTER', ['fonction' => 'interrupteur']);
        $this->article('electrique_appareillage', 'VAV', ['fonction' => 'va_et_vient']);
        $this->article('electrique_appareillage', 'PLAQUE', ['fonction' => 'plaque']);
        $this->article('electrique_connecteur', 'COLLIER', ['type' => 'fixation']);
        $this->article('electrique_connecteur', 'WAGO', ['type' => 'wago', 'nb_entrees' => 5]);
        if (!$complet) {
            return;
        }
    }

    private function surface(Piece $piece, string $nom): Surface
    {
        foreach ($piece->getSurfaces() as $surface) {
            if ($nom === $surface->getNom()) {
                return $surface;
            }
        }
        self::fail('Pas de surface '.$nom);
    }

    private function equipement(Piece $piece, string $surface, string $type, int $u, int $v, ?Reseau $reseau = null, ?int $puissance = null): Equipement
    {
        $e = new Equipement($piece, $this->surface($piece, $surface));
        $e->setType($type);
        $e->placer($u, $v);
        $e->setReseau($reseau);
        if (null !== $puissance) {
            $e->setParametres(['puissance_w' => $puissance]);
        }
        $this->em->persist($e);

        return $e;
    }

    /**
     * @return array{Projet, Piece, Reseau, array<string, Equipement>}
     */
    private function projet(bool $vaEtVient = false, bool $catalogueComplet = true): array
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        if ($catalogueComplet) {
            $this->catalogue();
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

        $reseau = new Reseau($projet, TypeCircuit::Eclairage);
        $reseau->setNom('Lighting');
        $this->em->persist($reseau);
        $tableau = $this->equipement($piece, 'Wall 4', 'tableau', 1000, 1800);
        $this->em->flush();
        $equipements = ['tableau' => $tableau];
        if ($vaEtVient) {
            $equipements['lumiere'] = $this->equipement($piece, 'Ceiling', 'point_lumineux', 2000, -1500, $reseau, 60);
            $equipements['v1'] = $this->equipement($piece, 'Wall 4', 'va_et_vient', 2500, 1100, $reseau);
            $equipements['v2'] = $this->equipement($piece, 'Wall 3', 'va_et_vient', 3000, 1100, $reseau);
            $this->em->flush();
            $reseau->definirDepart((int) $tableau->getId());
            $reseau->relier((int) $equipements['lumiere']->getId(), (int) $tableau->getId());
            $reseau->relier((int) $equipements['v1']->getId(), (int) $equipements['lumiere']->getId());
            $reseau->relier((int) $equipements['v2']->getId(), (int) $equipements['v1']->getId());
        } else {
            $equipements['inter'] = $this->equipement($piece, 'Wall 4', 'interrupteur', 2500, 1100, $reseau);
            $equipements['lumiere'] = $this->equipement($piece, 'Ceiling', 'point_lumineux', 2000, -1500, $reseau, 60);
            $this->em->flush();
            $reseau->definirDepart((int) $tableau->getId());
            $reseau->relier((int) $equipements['inter']->getId(), (int) $tableau->getId());
            $reseau->relier((int) $equipements['lumiere']->getId(), (int) $equipements['inter']->getId());
        }
        $this->em->flush();

        return [$projet, $piece, $reseau, $equipements];
    }

    private function achat(GroupeAchats $groupe, string $reference): LigneAchat
    {
        foreach ($groupe->resultat->achats as $achat) {
            if ($achat->article instanceof Materiau && $reference === $achat->article->getReference()) {
                return $achat;
            }
        }
        self::fail('Pas de ligne d\'achat pour '.$reference);
    }

    private function aucunAchat(GroupeAchats $groupe, string $reference): void
    {
        foreach ($groupe->resultat->achats as $achat) {
            self::assertFalse($achat->article instanceof Materiau && $reference === $achat->article->getReference(), $reference);
        }
    }

    public function testGaineEtCableDeLaPieceRectangulaire(): void
    {
        [$projet] = $this->projet();

        $analyse = self::getContainer()->get(ElectriciteProjet::class)->analyser($projet);

        self::assertSame([], $analyse->erreurs);
        self::assertCount(1, $analyse->cheminements);
        $cheminement = $analyse->cheminements[0];
        self::assertSame([3600, 4400], array_map(static fn ($t) => $t->longueurMm, $cheminement->troncons));
        self::assertSame([16 => 8000], $cheminement->gaines);
        self::assertSame([['section' => 150, 'conducteurs' => 3, 'longueur' => 9600]], $cheminement->cables);
    }

    public function testLesQuantitesAnnexesSontDansLaNomenclatureDuProjet(): void
    {
        [$projet] = $this->projet();

        $resultat = self::getContainer()->get(CalculProjet::class)->calculer($projet);

        self::assertSame([], $resultat->erreurs);
        $groupe = $resultat->groupes[0];
        // Gaine et câble : longueurs cumulées (mm), commandées en couronnes entières.
        self::assertSame(8000, $this->achat($groupe, 'ICTA16')->besoin);
        // 1 barre + 5 % de marge de catalogue = 1,05 : arrondi à 2 lots (comportement du moteur, inchangé).
        self::assertSame(2, $this->achat($groupe, 'ICTA16')->lots);
        self::assertSame(9600, $this->achat($groupe, 'CABLE3G15')->besoin);
        $this->aucunAchat($groupe, 'ICTA20');
        $this->aucunAchat($groupe, 'CABLE4G15');
        // Une boîte pour l'interrupteur, une pour le point lumineux ; rien pour le tableau.
        self::assertSame(2, $this->achat($groupe, 'BOITE-ENC')->besoin);
        $this->aucunAchat($groupe, 'BOITE-CREUSE');
        self::assertSame(1, $this->achat($groupe, 'INTER')->besoin);
        self::assertSame(1, $this->achat($groupe, 'PLAQUE')->besoin);
        // Fixations : un collier tous les 300 mm de gaine, 8000 / 300 arrondi au-dessus.
        self::assertSame(27, $this->achat($groupe, 'COLLIER')->besoin);
        $this->aucunAchat($groupe, 'WAGO');
    }

    public function testVaEtVientTroisConducteursPlusTerreEntreLesCommandes(): void
    {
        [$projet] = $this->projet(true);

        $analyse = self::getContainer()->get(ElectriciteProjet::class)->analyser($projet);

        self::assertSame([], $analyse->erreurs);
        $troncons = $analyse->cheminements[0]->troncons;
        self::assertCount(3, $troncons);
        $entreCommandes = $troncons[2];
        self::assertSame(3, $entreCommandes->conducteursActifs);
        self::assertTrue($entreCommandes->terre);
        self::assertSame(4, $entreCommandes->conducteurs());
        // Câbles 3G et 4G distincts dans la nomenclature.
        $resultat = self::getContainer()->get(CalculProjet::class)->calculer($projet);
        $groupe = $resultat->groupes[0];
        self::assertSame($entreCommandes->longueurCableMm, $this->achat($groupe, 'CABLE4G15')->besoin);
        self::assertGreaterThan(0, $this->achat($groupe, 'CABLE3G15')->besoin);
        self::assertSame(2, $this->achat($groupe, 'VAV')->besoin);
    }

    public function testArticleManquantEstSignaleSansBloquer(): void
    {
        [$projet] = $this->projet(false, false);
        $this->article('electrique_gaine', 'ICTA16', ['diametre' => 16, 'conducteurs_max' => [['section' => '1.5', 'nombre' => 5]]], 50_000);
        $this->em->flush();

        $resultat = self::getContainer()->get(CalculProjet::class)->calculer($projet);

        self::assertSame([], $resultat->erreurs);
        $codes = array_map(static fn ($a) => $a->code, $resultat->avertissements);
        self::assertContains('electrical_no_candidate', $codes);
        // La gaine, elle, est bien comptée.
        self::assertSame(8000, $this->achat($resultat->groupes[0], 'ICTA16')->besoin);
    }

    public function testSansGaineAdmissibleLErreurEstBloquante(): void
    {
        [$projet] = $this->projet(false, false);
        $this->article('electrique_gaine', 'ICTA16', ['diametre' => 16, 'conducteurs_max' => [['section' => '1.5', 'nombre' => 2]]], 50_000);
        $this->em->flush();

        $resultat = self::getContainer()->get(CalculProjet::class)->calculer($projet);

        self::assertContains('electrical_no_conduit', array_map(static fn ($a) => $a->code, $resultat->erreurs));
    }

    public function testRepartitionParPiece(): void
    {
        [$projet] = $this->projet();

        $resultat = self::getContainer()->get(CalculProjet::class)->calculer($projet, PorteeAgregation::Piece);

        self::assertCount(1, $resultat->groupes);
        self::assertSame('Salon', $resultat->groupes[0]->nom);
        self::assertSame(8000, $this->achat($resultat->groupes[0], 'ICTA16')->besoin);

        $parSurface = self::getContainer()->get(CalculProjet::class)->calculer($projet, PorteeAgregation::Surface);
        self::assertSame('Salon / Electrical installation', $parSurface->groupes[0]->nom);
    }

    public function testPersistanceDesEquipementsEtDesReseaux(): void
    {
        [$projet, , $reseau, $equipements] = $this->projet();
        $reseauId = $reseau->getId();
        $interId = $equipements['inter']->getId();
        $this->em->clear();

        $relu = $this->em->find(Reseau::class, $reseauId);
        self::assertInstanceOf(Reseau::class, $relu);
        self::assertSame('1.50', $relu->getSection());
        self::assertSame(16, $relu->getProtection());
        self::assertSame(TypeCircuit::Eclairage, $relu->getType());
        self::assertSame(2, \count($relu->getLiaisons()));
        self::assertNotNull($relu->getDepart());
        $inter = $this->em->find(Equipement::class, $interId);
        self::assertInstanceOf(Equipement::class, $inter);
        self::assertSame('interrupteur', $inter->getType());
        self::assertSame([2500, 1100], [$inter->getU(), $inter->getV()]);
        self::assertSame($reseauId, $inter->getReseau()?->getId());
        self::assertSame('Wall 4', $inter->getSurface()->getNom());
        self::assertCount(3, $inter->getPiece()->getEquipements());
        self::assertSame(60, $this->em->getRepository(Equipement::class)->findOneBy(['type' => 'point_lumineux'])?->getPuissanceW());

        // Supprimer le réseau détache les équipements sans les supprimer.
        $this->em->remove($relu);
        $this->em->flush();
        $this->em->clear();
        self::assertNull($this->em->find(Equipement::class, $interId)?->getReseau());

        // Supprimer la pièce supprime ses équipements.
        $piece = $this->em->getRepository(Piece::class)->findOneBy([]);
        self::assertNotNull($piece);
        $this->em->remove($piece);
        $this->em->flush();
        self::assertSame(0, $this->em->getRepository(Equipement::class)->count([]));
        self::assertNotNull($projet->getId());
    }

    public function testLeChiffrageReprendLesLignesElectriques(): void
    {
        [$projet] = $this->projet();
        $f = new Fournisseur();
        $f->setNom('Alpha');
        $this->em->persist($f);
        $this->em->flush();
        self::getContainer()->get(PrixService::class)->definirPrix($this->articles['ICTA16'], $f, '30.00', new \DateTimeImmutable('2020-01-01'));
        $this->em->flush();

        $chiffrage = self::getContainer()->get(ChiffrageService::class)->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $f->getId()]);

        $references = [];
        $repartitions = [];
        foreach ($chiffrage->getLignes() as $ligne) {
            $references[] = $ligne->getMateriauReference();
            if ('ICTA16' === $ligne->getMateriauReference() && null !== $ligne->getSurfaceNom()) {
                $repartitions[] = [$ligne->getPieceNom(), $ligne->getSurfaceNom(), $ligne->getQuantiteBesoin()];
            }
        }
        self::assertContains('ICTA16', $references);
        self::assertContains('CABLE3G15', $references);
        self::assertSame([['Salon', 'Electrical installation', 1]], $repartitions);
        // 2 barres de gaine à 30 EUR (1 barre + marge de 5 % arrondie au lot).
        self::assertSame(6000, array_sum(array_map(static fn ($l) => 'ICTA16' === $l->getMateriauReference() && null === $l->getSurfaceNom() ? $l->getTotalCentimes() : 0, $chiffrage->getLignes()->toArray())));
    }

    public function testControlesNonBloquants(): void
    {
        [$projet, $piece, $reseau] = $this->projet();
        $controle = self::getContainer()->get(ControleElectricite::class);

        $codes = array_map(static fn ($a) => $a->code, $controle->controler($projet));
        self::assertContains('electrical_min_sockets', $codes);
        self::assertNotContains('electrical_min_lights', $codes);
        self::assertNotContains('electrical_circuit_overload', $codes);

        // 16 A x 230 V = 3680 W : un second luminaire de 4000 W dépasse la protection.
        $this->equipement($piece, 'Ceiling', 'point_lumineux', 1000, -1000, $reseau, 4000);
        $this->em->flush();
        $this->em->refresh($piece);
        $codes = array_map(static fn ($a) => $a->code, $controle->controler($projet));
        self::assertContains('electrical_circuit_overload', $codes);
        self::assertSame(4060, $controle->charge($projet, $reseau));
    }

    /**
     * @return list<string>
     */
    private function codesPosition(Equipement $equipement): array
    {
        return array_map(static fn ($a) => $a->code, self::getContainer()->get(ControleElectricite::class)->position($equipement));
    }

    public function testBoitePoseeSurUnMontantDOssature(): void
    {
        [, $piece] = $this->projet();
        $mur = $this->surface($piece, 'Wall 4');
        $systeme = new SystemeApplique('doublage_ossature', $mur);
        $systeme->activer();
        new CoucheAppliquee($systeme, 0, 'ossature', 'cavity');
        $this->em->persist($systeme);
        $this->em->flush();

        $surMontant = $this->equipement($piece, 'Wall 4', 'prise', 1200, 300);
        $entreMontants = $this->equipement($piece, 'Wall 4', 'prise', 900, 300);
        $this->em->flush();

        self::assertSame(600, self::getContainer()->get(ControleElectricite::class)->entraxeOssature($mur));
        self::assertContains('electrical_on_stud', $this->codesPosition($surMontant));
        self::assertNotContains('electrical_on_stud', $this->codesPosition($entreMontants));
    }

    public function testSansOssatureAucunMontantNEstSignale(): void
    {
        [, $piece] = $this->projet();
        $prise = $this->equipement($piece, 'Wall 4', 'prise', 1200, 300);
        $this->em->flush();

        self::assertNotContains('electrical_on_stud', $this->codesPosition($prise));
    }

    public function testDistanceMiniAuxOuvertures(): void
    {
        [, $piece] = $this->projet();
        $mur = $this->surface($piece, 'Wall 4');
        $fenetre = new Ouverture($mur);
        $fenetre->setType('window');
        $fenetre->definirRectangle(2000, 1000, 900, 600);
        $this->em->persist($fenetre);
        $this->em->flush();

        $trop = $this->equipement($piece, 'Wall 4', 'prise', 2300, 1650);
        $assez = $this->equipement($piece, 'Wall 4', 'prise', 2300, 1750);
        $dedans = $this->equipement($piece, 'Wall 4', 'prise', 2300, 1300);
        $this->em->flush();

        self::assertContains('electrical_near_opening', $this->codesPosition($trop));
        self::assertNotContains('electrical_near_opening', $this->codesPosition($assez));
        self::assertContains('electrical_on_opening', $this->codesPosition($dedans));
    }
}
