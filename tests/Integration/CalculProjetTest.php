<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Agregation\LigneAchat;
use App\Calcul\Agregation\SourceMarge;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Substitution\NatureOffre;
use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Entity\CoucheAppliquee;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\TypeSurface;
use App\Service\CalculProjet;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Service\PorteeAgregation;
use App\Service\PrixService;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Agrégation d'un projet de deux pièces avec la base : besoins de toutes les surfaces, marge, lots, offres d'un fournisseur.
 */
final class CalculProjetTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /** @var array<string, Materiau> */
    private array $articles = [];

    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function article(string $categorie, string $reference, ?int $epaisseur, array $caracteristiques, ?int $largeur = null, ?int $longueur = null, int $parLot = 1): Materiau
    {
        $m = new Materiau();
        $m->setReference($reference);
        $m->setLibelle($reference);
        $m->setCategorie($categorie);
        $m->setEpaisseur($epaisseur);
        $m->setLargeur($largeur);
        $m->setLongueur($longueur);
        $m->setUnitesParLot($parLot);
        $m->setCaracteristiques($caracteristiques);
        $this->em->persist($m);

        return $this->articles[$reference] = $m;
    }

    private function projet(int $plaquesParLot = 1): Projet
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $portees = [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3000]];
        $this->article('profil_ossature', 'M70', null, ['famille' => 'montant', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600, 'portees' => $portees]], null, 3000);
        $this->article('profil_ossature', 'R70', null, ['famille' => 'rail', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600]], null, 3000);
        $this->article('appui_fixation', 'ATT', null, []);
        $this->article('isolant', 'ISO70', 70, ['lambda' => 0.035], 600, 1200);
        $pose = ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250];
        $this->article('plaque_platre', 'BA13', 13, ['pose' => $pose], 1200, 2500, $plaquesParLot);
        $this->article('plaque_platre', 'BA18', 18, ['pose' => $pose], 1200, 2500, $plaquesParLot);

        $projet = new Projet();
        $projet->setNom('Maison');
        $this->em->persist($projet);
        foreach ([['Salon', 4000], ['Chambre', 3000]] as [$nom, $largeur]) {
            $piece = new Piece($projet);
            $piece->setNom($nom);
            $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => $largeur, 'y' => 0], ['x' => $largeur, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
            $piece->setHauteurSousPlafond(2500);
            (new PieceCreationService(new PieceJonctions()))->generer($piece);
            $projet->getPieces()->add($piece);
            $this->doublage($piece);
        }
        $this->em->flush();

        return $projet;
    }

    private function doublage(Piece $piece): void
    {
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
        $specification = static fn (): MaterielSpecification => MaterielSpecification::creer('parement', new PlaquePlatre(), [new Critere('epaisseur', Operateur::SuperieurOuEgal, 13)]);
        foreach ([[0, 'attache', 'ATT', null], [1, 'ossature', 'M70', null], [2, 'isolant', 'ISO70', 'entre_ossature'], [3, 'parement', 'BA13', 'sur_ossature']] as [$ordre, $role, $reference, $position]) {
            $couche = new CoucheAppliquee($systeme, $ordre, $role, $role);
            $couche->setMateriau($this->articles[$reference]);
            $couche->setPosition($position);
            if (2 === $ordre) {
                $couche->setOrientation(0);
            }
            if ('parement' === $role) {
                $couche->setSpecification(self::getContainer()->get(SpecificationSerialiseur::class)->vers($specification()));
            }
        }
        $systeme->activer();
        $this->em->persist($systeme);
    }

    private function achat(\App\Service\GroupeAchats $groupe, string $reference): LigneAchat
    {
        foreach ($groupe->resultat->achats as $achat) {
            if ($achat->article instanceof Materiau && $reference === $achat->article->getReference()) {
                return $achat;
            }
        }
        self::fail('Pas de ligne d\'achat pour '.$reference);
    }

    private function service(): CalculProjet
    {
        return self::getContainer()->get(CalculProjet::class);
    }

    public function test_deux_pieces_donnent_des_besoins_agreges_en_lots_entiers_avec_chute(): void
    {
        $projet = $this->projet(5);

        $global = $this->service()->calculer($projet);
        $parPiece = $this->service()->calculer($projet, PorteeAgregation::Piece);

        self::assertSame([], $global->erreurs);
        self::assertCount(1, $global->groupes);
        self::assertCount(2, $parPiece->groupes);
        $total = $this->achat($global->groupes[0], 'BA13');
        $somme = $this->achat($parPiece->groupes[0], 'BA13')->besoin + $this->achat($parPiece->groupes[1], 'BA13')->besoin;
        self::assertSame($somme, $total->besoin, 'le besoin agrégé est la somme des besoins des pièces');
        self::assertSame(['Salon', 'Chambre'], array_map(static fn ($g): string => $g->nom, $parPiece->groupes));
        self::assertSame(SourceMarge::Categorie, $total->sourceMarge);
        self::assertSame(1000, $total->margeCentiemes);
        self::assertSame(5, $total->unitesParLot);
        self::assertSame($total->lots * 5, $total->quantiteAchetee);
        self::assertGreaterThanOrEqual($total->quantiteAvecMargeMilli, $total->quantiteAchetee * 1000);
        self::assertLessThan($total->quantiteAvecMargeMilli + 5000, $total->quantiteAchetee * 1000);
        self::assertSame($total->quantiteAchetee - $total->unitesBesoin, $total->chute);
        self::assertCount(2, $total->origines);
        // La marge du projet prime sur celle de la catégorie.
        $projet->setParametres(['marges' => ['materiaux' => ['BA13' => 0]]]);
        $sans = $this->achat($this->service()->calculer($projet)->groupes[0], 'BA13');
        self::assertSame(SourceMarge::Projet, $sans->sourceMarge);
        self::assertSame(0, $sans->margeCentiemes);
        self::assertSame($total->unitesBesoin, $sans->unitesBesoin);
    }

    #[DataProvider('portees')]
    public function test_nombre_de_groupes_selon_la_portee(PorteeAgregation $portee, int $attendu): void
    {
        $projet = $this->projet();

        self::assertCount($attendu, $this->service()->calculer($projet, $portee)->groupes);
    }

    /**
     * @return iterable<string, array{PorteeAgregation, int}>
     */
    public static function portees(): iterable
    {
        yield 'projet' => [PorteeAgregation::Projet, 1];
        yield 'pièce' => [PorteeAgregation::Piece, 2];
        yield 'surface' => [PorteeAgregation::Surface, 2];
    }

    public function test_fournisseur_equivalent_puis_prix_manquant(): void
    {
        $projet = $this->projet();
        $fournisseur = new Fournisseur();
        $fournisseur->setNom('Négoce');
        $this->em->persist($fournisseur);
        $this->em->flush();
        // Le fournisseur ne vend que la BA18 (équivalent de la BA13 : même spécification) et le montant M70.
        $prix = self::getContainer()->get(PrixService::class);
        $debut = new \DateTimeImmutable('2020-01-01');
        $prix->definirPrix($this->articles['BA18'], $fournisseur, '12.00', $debut);
        $prix->definirPrix($this->articles['M70'], $fournisseur, '5.00', $debut);

        $resultat = $this->service()->calculer($projet, PorteeAgregation::Projet, $fournisseur);

        $groupe = $resultat->groupes[0];
        $codes = array_map(static fn ($a): string => $a->code, $groupe->resultat->avertissements);
        self::assertContains('offer_equivalent_used', $codes);
        self::assertContains('offer_missing', $codes);
        $remplace = $this->achat($groupe, 'BA18');
        self::assertSame($this->articles['BA13'], $remplace->articleInitial);
        self::assertSame(NatureOffre::Equivalente, $remplace->nature);
        self::assertFalse($remplace->prixManquant);
        self::assertFalse($this->achat($groupe, 'M70')->prixManquant);
        self::assertTrue($this->achat($groupe, 'ISO70')->prixManquant);
    }
}
