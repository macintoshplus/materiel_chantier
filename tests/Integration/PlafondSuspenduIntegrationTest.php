<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Systeme\SystemePlafondSuspendu;
use App\Entity\CoucheAppliquee;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Service\CalculSysteme;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Plafond suspendu sur une pièce réelle à double pente (base de données, jonctions, surfaces générées).
 */
final class PlafondSuspenduIntegrationTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /** @var array<string, Materiau> */
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
        $this->articles[$reference] = $m;
    }

    private function couche(SystemeApplique $systeme, int $ordre, string $emplacement, string $reference, ?string $position = null): void
    {
        $c = new CoucheAppliquee($systeme, $ordre, 'suspente' === $emplacement ? 'attache' : $emplacement, $emplacement);
        $c->setMateriau($this->articles[$reference]);
        $c->setPosition($position);
    }

    /**
     * @return array{Piece, list<Surface>}
     */
    private function piece(?int $faitage): array
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $this->article('profil_ossature', 'LISSE', null, ['famille' => 'lisse', 'serie' => 27], null, 3000);
        $this->article('profil_ossature', 'FOURRURE', null, ['famille' => 'fourrure', 'serie' => 60], null, 3000);
        $this->article('suspente', 'SUS', null, ['pas' => 1200]);
        $this->article('plaque_platre', 'BA13', 13, ['pose' => ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250]], 1200, 2500);
        $this->article('visserie', 'TTPC25', null, ['usage' => 'plaque_sur_metal', 'pose' => ['epaisseur_fixable_min' => 0, 'epaisseur_fixable_max' => 15], 'unites_par_lot' => 1000], null, 25);
        $projet = new Projet();
        $projet->setNom('P');
        $piece = new Piece($projet);
        $piece->setNom('Combles');
        $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
        $piece->setHauteurSousPlafond(2500);
        (new PieceCreationService(new PieceJonctions()))->generer($piece, $faitage);
        $this->em->persist($projet);

        return [$piece, $piece->getPans()];
    }

    /**
     * @param array<string, scalar> $parametres
     */
    private function systeme(Surface $pan, array $parametres = []): SystemeApplique
    {
        $systeme = new SystemeApplique(SystemePlafondSuspendu::CODE, $pan);
        $systeme->setVariante('V1');
        $systeme->setParametres($parametres);
        $this->couche($systeme, 0, 'suspente', 'SUS');
        $this->couche($systeme, 1, 'ossature', 'FOURRURE');
        $this->couche($systeme, 2, 'parement', 'BA13', 'sur_ossature');

        return $systeme;
    }

    private function calcul(): CalculSysteme
    {
        return self::getContainer()->get(CalculSysteme::class);
    }

    public function test_double_pente_longueurs_reelles_et_faitage_sans_lisse(): void
    {
        [, $pans] = $this->piece(3500);
        self::assertCount(2, $pans);
        $systeme = $this->systeme($pans[0]);
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $lisses = $r->ligne('lisses_peripheriques');
        self::assertNotNull($lisses);
        // Sablière 4000 + deux rives de pente de 1803 mm réels (arrondi de 1803,8) (1500 horizontal, 1000 de dénivelé) ; faîtage ouvert.
        self::assertSame(4000 + 2 * 1803, array_sum($lisses->ligne->pieces), json_encode($lisses->ligne->pieces, \JSON_THROW_ON_ERROR));
        $fourrures = $r->ligne('fourrures');
        self::assertNotNull($fourrures);
        self::assertContains(1803, $fourrures->ligne->pieces, 'fourrure de pente en longueur réelle');
    }

    public function test_plafond_plat_garde_toute_la_peripherie(): void
    {
        [, $pans] = $this->piece(null);
        $systeme = $this->systeme($pans[0]);
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        self::assertSame(14000, array_sum($r->ligne('lisses_peripheriques')?->ligne->pieces ?? []));
    }

    public function test_aretes_ouvertes_explicites_prioritaires_sur_la_derivation(): void
    {
        [, $pans] = $this->piece(3500);
        $systeme = $this->systeme($pans[0], ['aretes_ouvertes' => '']);
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertSame(4000 * 2 + 2 * 1803, array_sum($r->ligne('lisses_peripheriques')?->ligne->pieces ?? []), 'aucune arête ouverte : le faîtage garde sa lisse');
    }
}
