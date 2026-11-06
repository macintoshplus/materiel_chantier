<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Pipeline\ResultatSysteme;
use App\Calcul\Systeme\SystemeCloisonOssature;
use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Entity\Cloison;
use App\Entity\CoucheAppliquee;
use App\Entity\CoucheExistante;
use App\Entity\Materiau;
use App\Entity\Ouverture;
use App\Entity\ParoiExistante;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\CoteCloison;
use App\Enum\TypePose;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\Point2D;
use App\Geometrie\TraceCloison;
use App\Service\CalculSysteme;
use App\Service\CloisonEditionService;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Service\ServiceVariantes;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Systèmes cloison et doublage sur ossature : calculs réels (calculateurs, pipeline, base), par face, variantes.
 */
final class CloisonsDoublagesTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /** @var array<string, Materiau> */
    private array $articles = [];

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

        return $this->articles[$reference] = $m;
    }

    private function catalogue(): void
    {
        $portees = [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3000]];
        $this->article('profil_ossature', 'M70', null, ['famille' => 'montant', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600, 'portees' => $portees]]);
        $this->article('profil_ossature', 'R70', null, ['famille' => 'rail', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600]]);
        $this->article('profil_ossature', 'L70', null, ['famille' => 'lisse', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600]]);
        $this->article('appui_fixation', 'ATT', null, []);
        $this->article('isolant', 'ISO70', 70, ['lambda' => 0.035], 600, 1200);
        $pose = ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250];
        $this->article('plaque_platre', 'BA13', 13, ['pose' => $pose], 1200, 2500);
        $this->article('plaque_platre', 'BA18', 18, ['pose' => $pose], 1200, 2500);
    }

    private function couche(SystemeApplique $systeme, int $ordre, string $emplacement, ?string $reference, ?string $position = null, ?CoteCloison $face = null, ?int $orientation = null): CoucheAppliquee
    {
        $c = new CoucheAppliquee($systeme, $ordre, $emplacement, $emplacement);
        $c->setMateriau(null === $reference ? null : $this->articles[$reference]);
        $c->setPosition($position);
        $c->setFace($face);
        $c->setOrientation($orientation);

        return $c;
    }

    private function cloison(): Cloison
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $this->catalogue();
        $projet = new Projet();
        $projet->setNom('P');
        $piece = new Piece($projet);
        $piece->setNom('Salon');
        $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
        $piece->setHauteurSousPlafond(2500);
        (new PieceCreationService(new PieceJonctions()))->generer($piece);
        $this->em->persist($projet);
        $cloison = new Cloison($piece);
        $cloison->setNom('Cloison');
        $cloison->setEpaisseur(100);
        $this->em->persist($cloison);
        $cloison->definirTrace(new TraceCloison(new Point2D(0, 1500), new Point2D(2000, 1500)));
        (new CloisonEditionService($this->em, new PieceJonctions()))->regenerer($cloison);

        return $cloison;
    }

    private function systemeCloison(Cloison $cloison, string $parementA = 'BA13', string $parementB = 'BA13', string $variante = 'V1'): SystemeApplique
    {
        $systeme = new SystemeApplique(SystemeCloisonOssature::CODE, null, $cloison);
        $systeme->setVariante($variante);
        $this->couche($systeme, 0, 'ossature', 'M70');
        $this->couche($systeme, 1, 'isolant', 'ISO70', 'entre_ossature', null, 0);
        $this->couche($systeme, 2, 'parement', $parementA, 'sur_ossature', CoteCloison::A);
        $this->couche($systeme, 3, 'parement', $parementB, 'sur_ossature', CoteCloison::B);

        return $systeme;
    }

    private static function reference(?\App\Catalogue\MaterielLisible $m): ?string
    {
        return $m instanceof Materiau ? $m->getReference() : null;
    }

    private function calcul(): CalculSysteme
    {
        return self::getContainer()->get(CalculSysteme::class);
    }

    /**
     * @return list<string>
     */
    private static function cles(ResultatSysteme $r, ?string $face): array
    {
        return array_map(static fn ($l): string => $l->ligne->cle, $r->lignesDeLaFace($face));
    }

    public function test_cloison_deux_faces_ossature_et_isolant_une_fois_parements_par_face(): void
    {
        $cloison = $this->cloison();
        $systeme = $this->systemeCloison($cloison);
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $communes = self::cles($r, null);
        self::assertContains('montants', $communes);
        self::assertContains('rail_bas', $communes);
        self::assertSame(1, \count(array_filter($communes, static fn (string $c): bool => 'montants' === $c)), 'ossature comptée une fois');
        self::assertSame(1, \count(array_filter($communes, static fn (string $c): bool => str_starts_with($c, 'isolant_'))), 'isolant compté une fois');
        self::assertSame(['plaques_2', 'vis_2'], self::cles($r, 'A'));
        self::assertSame(['plaques_3', 'vis_3'], self::cles($r, 'B'));
        // 70 + 13 + 13 ; R = isolant 70 mm / lambda 0,035 = 2000.
        self::assertSame(96, $r->epaisseurTotale);
        self::assertSame(2000, $r->rSystemeMilli);
        $codes = array_map(static fn ($a): string => $a->code, $r->avertissements);
        self::assertSame(['framing_free_end'], $codes, 'la cloison se termine librement : un seul avertissement, dédupliqué');
    }

    public function test_deux_types_de_plaques_donnent_deux_lignes_de_parement(): void
    {
        $cloison = $this->cloison();
        $systeme = $this->systemeCloison($cloison, 'BA13', 'BA18');
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $plaques = [];
        foreach ($r->lignes as $l) {
            if ('plaque_platre' === $l->ligne->categorie) {
                $plaques[] = self::reference($l->ligne->materiau);
            }
        }
        sort($plaques);
        self::assertSame(['BA13', 'BA18'], $plaques);
        self::assertSame(101, $r->epaisseurTotale);
    }

    public function test_ouverture_de_cloison_sur_les_deux_faces_et_retour_de_tunnel_sur_toute_l_epaisseur(): void
    {
        $cloison = $this->cloison();
        $ouverture = new Ouverture(null, $cloison);
        $ouverture->setNom('Porte');
        $ouverture->setType('porte');
        $ouverture->definirRectangle(500, 0, 900, 2040);
        $ouverture->setTypePose(TypePose::Tunnel);
        $systeme = $this->systemeCloison($cloison);
        $this->em->flush();

        $surfaces = $this->calcul()->surfacesCloison($cloison);
        self::assertCount(1, $surfaces['A']->ouvertures);
        self::assertCount(1, $surfaces['B']->ouvertures);

        $r = $this->calcul()->calculerSysteme($systeme);
        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $retour = $r->ligne('retour_tunnel', 'A');
        self::assertNotNull($retour);
        // Linteau 900 + deux jambages 2040, sans le seuil, sur 96 mm.
        self::assertSame((900 + 2 * 2040) * 96, $retour->ligne->quantite);
        self::assertSame(96, $r->epaisseurTotale);
    }

    public function test_comparer_ne_change_pas_la_variante_active_puis_activer_la_bascule(): void
    {
        $cloison = $this->cloison();
        $v1 = $this->systemeCloison($cloison, 'BA13', 'BA13', 'V1');
        $v2 = $this->systemeCloison($cloison, 'BA18', 'BA18', 'V2');
        $v1->activer();
        $this->em->flush();
        $service = self::getContainer()->get(ServiceVariantes::class);

        $lignes = $service->comparer($cloison->getPiece());

        self::assertCount(2, $lignes);
        self::assertTrue($v1->isActif());
        self::assertFalse($v2->isActif());
        self::assertSame(['V1', 'V2'], array_map(static fn ($l): string => $l->variante, $lignes));
        self::assertSame([true, false], array_map(static fn ($l): bool => $l->actif, $lignes));
        self::assertSame(96, $lignes[0]->epaisseurMm);
        self::assertSame(106, $lignes[1]->epaisseurMm);
        // R système 2000 + Rsi 130 + Rse 40 (mur), U = 1 / 2,17 arrondi.
        self::assertSame(2170, $lignes[0]->rTotaleMilli);
        self::assertSame(461, $lignes[0]->uMilli);
        self::assertNull($lignes[0]->coutCentimes);

        $service->activer($v2);

        self::assertFalse($v1->isActif());
        self::assertTrue($v2->isActif());
        self::assertSame(106, $cloison->getEpaisseur(), "l'épaisseur de la cloison suit la variante activée");
    }

    private function mur(): Surface
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $this->catalogue();
        $projet = new Projet();
        $projet->setNom('P');
        $surface = new Surface(new Piece($projet), TypeSurface::Mur);
        $surface->setNom('Mur A');
        $surface->definirContour(ContourIdentifie::depuisTableau([
            ['id' => 'a', 'u' => 0, 'v' => 0],
            ['id' => 'b', 'u' => 4000, 'v' => 0],
            ['id' => 'c', 'u' => 4000, 'v' => 2500],
            ['id' => 'd', 'u' => 0, 'v' => 2500],
        ]));
        $this->em->persist($projet);

        return $surface;
    }

    private function doublage(Surface $surface, string $profilPeripherique): SystemeApplique
    {
        $systeme = new SystemeApplique(SystemeDoublageOssature::CODE, $surface);
        $systeme->setVariante('V1');
        $systeme->setParametres(['type_profil_peripherique' => $profilPeripherique]);
        $this->couche($systeme, 0, 'attache', 'ATT');
        $this->couche($systeme, 1, 'ossature', 'M70');
        $this->couche($systeme, 2, 'isolant', 'ISO70', 'entre_ossature', null, 0);
        $this->couche($systeme, 3, 'parement', 'BA13', 'sur_ossature');

        return $systeme;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function peripheriques(): array
    {
        return [
            'système cloison : rails haut et bas' => ['rail', 'R70'],
            'système doublage : lisses haute et basse' => ['lisse', 'L70'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peripheriques')]
    public function test_doublage_profils_peripheriques_selon_le_type(string $type, string $reference): void
    {
        $surface = $this->mur();
        $systeme = $this->doublage($surface, $type);
        $this->em->flush();

        $r = $this->calcul()->calculerSysteme($systeme);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        foreach (['rail_bas', 'rail_haut'] as $cle) {
            $ligne = $r->ligne($cle);
            self::assertNotNull($ligne, $cle);
            self::assertSame([4000], $ligne->ligne->pieces);
            self::assertSame($reference, self::reference($ligne->ligne->materiau));
        }
        self::assertNotNull($r->ligne('appuis'));
        self::assertSame(83, $r->epaisseurTotale);
    }

    public function test_paroi_existante_resistance_et_u_de_la_variante(): void
    {
        $surface = $this->mur();
        $brique = $this->article('paroi_existante', 'BRIQUE', null, ['lambda' => 0.5]);
        $paroi = new ParoiExistante($surface);
        $c1 = new CoucheExistante($paroi, 0);
        $c1->definirMateriau($brique, 200);
        $c2 = new CoucheExistante($paroi, 1);
        $c2->definirForfait(150);
        $systeme = $this->doublage($surface, 'rail');
        $systeme->activer();
        $autre = $this->doublage($surface, 'lisse');
        $autre->setVariante('V2');
        $this->em->flush();

        // 200 mm / 0,5 W/m.K = 400 ; + 150 forfaitaire.
        self::assertSame(400, $c1->resistanceMilli());
        self::assertSame(550, $paroi->resistanceMilli());
        $lignes = self::getContainer()->get(ServiceVariantes::class)->comparer($surface);
        self::assertCount(2, $lignes, 'deux variantes sur la surface');
        self::assertTrue($systeme->isActif());
        self::assertFalse($autre->isActif(), 'comparer ne change pas la variante active');
        // Rsi 130 + 550 + R système (isolant 2000) + Rse 40.
        self::assertSame(130 + 550 + 2000 + 40, $lignes[0]->rTotaleMilli);
        self::assertSame(368, $lignes[0]->uMilli);
    }

    public function test_couche_existante_materiau_et_forfait_sont_exclusifs(): void
    {
        $surface = $this->mur();
        $brique = $this->article('paroi_existante', 'BRIQUE', null, ['lambda' => 0.5]);
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $paroi = new ParoiExistante($surface);
        $couche = new CoucheExistante($paroi, 0);

        self::assertGreaterThan(0, \count($validator->validate($couche)), 'ni matériau ni forfait');
        $couche->definirForfait(100);
        self::assertCount(0, $validator->validate($couche));
        $couche->definirMateriau($brique, 100);
        self::assertCount(0, $validator->validate($couche));
        self::assertNull($couche->getRForfaitaireMilli(), 'définir un matériau efface le forfait');
    }
}
