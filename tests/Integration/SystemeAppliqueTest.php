<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Systeme\RegistreSystemes;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Entity\CoucheAppliquee;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Service\CalculSysteme;
use App\Tests\Support\BaseDeDonneesTrait;
use App\Tests\Calcul\Systemes\SystemeIsolantsCroises;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Un système défini seulement dans `tests/` (classe + tag par autoconfiguration) est enregistré, persisté et calculé
 * sans toucher au moteur ni aux entités.
 */
final class SystemeAppliqueTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

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

    private function surface(): Surface
    {
        $projet = new Projet();
        $projet->setNom('P');
        $surface = new Surface(new Piece($projet), TypeSurface::Mur);
        $surface->setNom('Mur A');
        $surface->definirContour(ContourIdentifie::depuisTableau([
            ['id' => 'a', 'u' => 0, 'v' => 0],
            ['id' => 'b', 'u' => 3000, 'v' => 0],
            ['id' => 'c', 'u' => 3000, 'v' => 2500],
            ['id' => 'd', 'u' => 0, 'v' => 2500],
        ]));
        $this->em->persist($projet);

        return $surface;
    }

    private function systeme(Surface $surface): SystemeApplique
    {
        $attache = $this->article('appui_fixation', 'ATT', null, []);
        $isolant = $this->article('isolant', 'ISO60', 60, ['lambda' => 0.035], 600, 1200);
        $plaque = $this->article('plaque_platre', 'BA13', 13, ['pose' => ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250]], 1200, 2500);
        $this->article('profil_ossature', 'M100', null, ['serie' => 100, 'pose' => ['entraxe_defaut' => 600]]);
        $this->article('profil_ossature', 'M150', null, ['serie' => 150, 'pose' => ['entraxe_defaut' => 600]]);

        $systeme = new SystemeApplique(SystemeIsolantsCroises::CODE, $surface);
        $systeme->setVariante('V1');
        $systeme->activer();
        $couche = static function (int $ordre, string $emplacement, ?Materiau $materiau, ?int $orientation = null, ?string $position = null) use ($systeme): CoucheAppliquee {
            $c = new CoucheAppliquee($systeme, $ordre, $emplacement, $emplacement);
            $c->setMateriau($materiau);
            $c->setOrientation($orientation);
            $c->setPosition($position);

            return $c;
        };
        $couche(0, 'attache', $attache);
        $couche(1, 'isolant', $isolant, 0, 'entre_ossature');
        $couche(2, 'isolant', $isolant, 90, 'continue');
        $ossature = $couche(3, 'ossature', null);
        $ossature->setSpecification(self::getContainer()->get(SpecificationSerialiseur::class)->vers(
            MaterielSpecification::creer('ossature', new ProfilOssature(), [new Critere('serie', Operateur::SuperieurOuEgal, '{contexte.profondeur_cavite}')]),
        ));
        $couche(4, 'parement', $plaque, 0, 'sur_ossature');
        $couche(5, 'parement', $plaque, 0, 'sur_ossature');

        return $systeme;
    }

    public function test_la_definition_et_les_calculateurs_de_test_sont_enregistres_par_tag(): void
    {
        self::bootKernel();

        $systemes = self::getContainer()->get(RegistreSystemes::class);
        $calculateurs = self::getContainer()->get(RegistreCalculateurs::class);
        self::assertTrue($systemes->possede(SystemeIsolantsCroises::CODE));
        foreach (['test.attache', 'test.isolant', 'test.ossature', 'test.parement'] as $code) {
            self::assertTrue($calculateurs->possede($code), $code);
        }
    }

    public function test_un_nouveau_systeme_est_persiste_relu_et_calcule_sans_modifier_le_moteur(): void
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $surface = $this->surface();
        $systeme = $this->systeme($surface);
        $this->em->persist($surface);
        $this->em->flush();
        $id = $systeme->getId();
        $this->em->clear();

        $relu = $this->em->find(SystemeApplique::class, $id);
        self::assertInstanceOf(SystemeApplique::class, $relu);
        self::assertCount(6, $relu->getCouches());
        self::assertSame([0, 1, 2, 3, 4, 5], array_map(static fn (CoucheAppliquee $c): int => $c->getOrdre(), $relu->getCouches()->toArray()));

        $service = self::getContainer()->get(CalculSysteme::class);
        $resultats = $service->calculer($relu);

        $r = $resultats['Mur A'];
        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        // L'ossature non épinglée est résolue par spécification : cavité de 120 mm, donc le profil de série 150.
        self::assertSame('M150', $r->materiaux[3] instanceof Materiau ? $r->materiaux[3]->getReference() : null);
        self::assertSame(3428, $r->contexte->rCumuleMilli);
        self::assertSame(146, $r->contexte->epaisseurCumulee);
        self::assertSame([], $r->avertissements);
    }

    public function test_un_systeme_appartient_a_un_seul_support_et_un_seul_est_actif(): void
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $surface = $this->surface();

        $a = new SystemeApplique(SystemeIsolantsCroises::CODE, $surface);
        $b = new SystemeApplique(SystemeIsolantsCroises::CODE, $surface);
        self::assertCount(0, $validator->validate($a));

        $a->activer();
        $b->activer();
        self::assertFalse($a->isActif());
        self::assertTrue($b->isActif());
        self::assertCount(0, $validator->validate($b));

        $sansSupport = new SystemeApplique(SystemeIsolantsCroises::CODE);
        self::assertCount(1, $validator->validate($sansSupport));
    }
}
