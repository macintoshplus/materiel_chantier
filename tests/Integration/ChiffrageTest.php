<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Chiffrage\ComparaisonChiffrages;
use App\Chiffrage\Export\ListeCourses;
use App\Chiffrage\Export\ListeCoursesPdf;
use App\Chiffrage\Export\PorteeExport;
use App\Chiffrage\Export\Regroupement;
use App\Chiffrage\Export\TypeExport;
use App\Entity\Chiffrage;
use App\Entity\CoucheAppliquee;
use App\Entity\Fournisseur;
use App\Entity\LigneChiffrage;
use App\Entity\Materiau;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\SystemeApplique;
use App\Enum\TypeLigneChiffrage;
use App\Enum\TypeStrategie;
use App\Enum\TypeSurface;
use App\Service\ChiffrageService;
use App\Service\PieceCreationService;
use App\Service\PieceJonctions;
use App\Service\PrixService;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Chiffrage d'un projet de deux pièces avec la base : instantané, stratégies, livraison, prix manquant, répartition
 * par pièce, comparaison et rendu des listes de courses.
 */
final class ChiffrageTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /** @var array<string, Materiau> */
    private array $articles = [];

    private \DateTimeImmutable $debut;

    /**
     * @param array<string, mixed> $caracteristiques
     */
    private function article(string $categorie, string $reference, ?int $epaisseur, array $caracteristiques, ?int $largeur = null, ?int $longueur = null, int $parLot = 1): void
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
        $this->articles[$reference] = $m;
    }

    private function fournisseur(string $nom, ?string $livraison = null, ?string $ligne1 = null, ?string $ville = null): Fournisseur
    {
        $f = new Fournisseur();
        $f->setNom($nom);
        $f->setFraisLivraisonTtc($livraison);
        $f->setAdresseLigne1($ligne1);
        $f->setVille($ville);
        $this->em->persist($f);

        return $f;
    }

    private function projet(): Projet
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $this->debut = new \DateTimeImmutable('2020-01-01');
        $portees = [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3000]];
        $this->article('profil_ossature', 'M70', null, ['famille' => 'montant', 'serie' => 70, 'pose' => ['entraxe_defaut' => 600, 'portees' => $portees]], null, 3000);
        $this->article('appui_fixation', 'ATT', null, []);
        $this->article('isolant', 'ISO70', 70, ['lambda' => 0.035], 600, 1200);
        $pose = ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 600, 'pas_vis_derniere_peau' => 300, 'pas_vis_peau_unique' => 250];
        $this->article('plaque_platre', 'BA13', 13, ['pose' => $pose], 1200, 2500, 5);

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
        self::assertNotNull($mur);
        $systeme = new SystemeApplique(SystemeDoublageOssature::CODE, $mur);
        $systeme->setVariante('V1');
        $systeme->setParametres(['type_profil_peripherique' => 'rail']);
        $specification = MaterielSpecification::creer('parement', new PlaquePlatre(), [new Critere('epaisseur', Operateur::SuperieurOuEgal, 13)]);
        foreach ([[0, 'attache', 'ATT', null], [1, 'ossature', 'M70', null], [2, 'isolant', 'ISO70', 'entre_ossature'], [3, 'parement', 'BA13', 'sur_ossature']] as [$ordre, $role, $reference, $position]) {
            $couche = new CoucheAppliquee($systeme, $ordre, $role, $role);
            $couche->setMateriau($this->articles[$reference]);
            $couche->setPosition($position);
            if (2 === $ordre) {
                $couche->setOrientation(0);
            }
            if ('parement' === $role) {
                $couche->setSpecification(self::getContainer()->get(SpecificationSerialiseur::class)->vers($specification));
            }
        }
        $systeme->activer();
        $this->em->persist($systeme);
    }

    private function prix(Fournisseur $f, string $reference, string $prix): void
    {
        self::getContainer()->get(PrixService::class)->definirPrix($this->articles[$reference], $f, $prix, $this->debut);
    }

    private function service(): ChiffrageService
    {
        return self::getContainer()->get(ChiffrageService::class);
    }

    private function ligneArticle(Chiffrage $chiffrage, string $reference): LigneChiffrage
    {
        foreach ($chiffrage->lignesDeType(TypeLigneChiffrage::Article) as $ligne) {
            if ($reference === $ligne->getMateriauReference()) {
                return $ligne;
            }
        }
        self::fail('Pas de ligne pour '.$reference);
    }

    /**
     * @return array{Projet, Fournisseur, Fournisseur}
     */
    private function projetAvecDeuxFournisseurs(): array
    {
        $projet = $this->projet();
        $a = $this->fournisseur('Alpha', '30.00', '1 rue des Lots', 'Lyon');
        $b = $this->fournisseur('Bravo', null, '2 avenue du Pro', 'Paris');
        $this->em->flush();
        foreach (['BA13' => ['12.00', '14.00'], 'M70' => ['5.00', '4.00'], 'ISO70' => ['20.00', '25.00'], 'ATT' => ['1.00', '1.50']] as $reference => [$pa, $pb]) {
            $this->prix($a, $reference, $pa);
            $this->prix($b, $reference, $pb);
        }

        return [$projet, $a, $b];
    }

    public function test_un_chiffrage_ne_change_pas_quand_un_prix_change_ensuite(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();
        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $a->getId()]);
        $total = $chiffrage->getTotalTtcCentimes();
        $prixLot = $this->ligneArticle($chiffrage, 'BA13')->getPrixLotCentimes();
        self::assertSame(1200, $prixLot);
        self::assertGreaterThan(0, $total);

        $this->prix($a, 'BA13', '99.00');
        $this->em->flush();
        $this->em->clear();
        $relu = $this->em->find(Chiffrage::class, $chiffrage->getId());

        self::assertInstanceOf(Chiffrage::class, $relu);
        self::assertSame($total, $relu->getTotalTtcCentimes());
        self::assertSame(1200, $this->ligneArticle($relu, 'BA13')->getPrixLotCentimes());
        self::assertSame('2024-06-01', $relu->getDateCreation()->format('Y-m-d'));
    }

    public function test_fournisseur_unique_ajoute_une_ligne_de_livraison_et_le_total_est_la_somme_des_lignes(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();

        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $a->getId()]);

        $livraisons = $chiffrage->lignesDeType(TypeLigneChiffrage::Livraison);
        self::assertCount(1, $livraisons);
        self::assertSame(3000, $livraisons[0]->getTotalCentimes());
        self::assertSame('Alpha', $livraisons[0]->getFournisseurNom());
        $somme = array_sum(array_map(static fn (LigneChiffrage $l): int => $l->getTotalCentimes(), [...$chiffrage->lignesDeType(TypeLigneChiffrage::Article), ...$livraisons]));
        self::assertSame($somme, $chiffrage->getTotalTtcCentimes());
        self::assertFalse($chiffrage->aDesPrixManquants());
    }

    public function test_meilleur_prix_n_est_jamais_plus_cher_qu_un_fournisseur_unique(): void
    {
        [$projet, $a, $b] = $this->projetAvecDeuxFournisseurs();
        $date = new \DateTimeImmutable('2024-06-01');

        $meilleur = $this->service()->creer($projet, TypeStrategie::MeilleurPrix, $date)->getTotalTtcCentimes();
        $seulA = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, $date, ['fournisseur' => $a->getId()])->getTotalTtcCentimes();
        $seulB = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, $date, ['fournisseur' => $b->getId()])->getTotalTtcCentimes();

        self::assertTrue($meilleur <= min($seulA, $seulB), sprintf('meilleur prix %d, Alpha %d, Bravo %d', $meilleur, $seulA, $seulB));
    }

    public function test_prix_manquant_est_visible_et_le_total_est_partiel(): void
    {
        $projet = $this->projet();
        $f = $this->fournisseur('Alpha');
        $this->em->flush();
        $this->prix($f, 'BA13', '12.00');

        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $f->getId()]);

        self::assertTrue($chiffrage->aDesPrixManquants());
        self::assertTrue($this->ligneArticle($chiffrage, 'ISO70')->isPrixManquant());
        self::assertSame(0, $this->ligneArticle($chiffrage, 'ISO70')->getTotalCentimes());
        self::assertFalse($this->ligneArticle($chiffrage, 'BA13')->isPrixManquant());
        self::assertSame($this->ligneArticle($chiffrage, 'BA13')->getTotalCentimes(), $chiffrage->getTotalTtcCentimes());
        $html = $this->pdf()->html($chiffrage, new PorteeExport(TypeExport::Projet), true);
        self::assertStringContainsString('Prix manquant', $html);
        self::assertStringContainsString('Total partiel', $html);
    }

    public function test_la_repartition_par_piece_somme_les_lignes_agregees(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();
        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $a->getId()]);

        $parts = $chiffrage->lignesDeType(TypeLigneChiffrage::Repartition);
        $articles = $chiffrage->lignesDeType(TypeLigneChiffrage::Article);

        self::assertSame(
            array_sum(array_map(static fn (LigneChiffrage $l): int => $l->getTotalCentimes(), $articles)),
            array_sum(array_map(static fn (LigneChiffrage $l): int => $l->getTotalCentimes(), $parts)),
        );
        self::assertEqualsCanonicalizing(['Chambre', 'Salon'], array_values(array_unique(array_map(static fn (LigneChiffrage $l): ?string => $l->getPieceNom(), $parts))));
        // Le total du chiffrage ne compte pas deux fois les parts.
        self::assertSame(
            array_sum(array_map(static fn (LigneChiffrage $l): int => $l->getTotalCentimes(), [...$articles, ...$chiffrage->lignesDeType(TypeLigneChiffrage::Livraison)])),
            $chiffrage->getTotalTtcCentimes(),
        );
    }

    public function test_comparaison_de_deux_chiffrages_apres_un_changement_de_prix(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();
        $date = new \DateTimeImmutable('2024-06-01');
        $avant = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, $date, ['fournisseur' => $a->getId()], 'Avant');
        $this->prix($a, 'BA13', '13.00');
        $apres = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, $date, ['fournisseur' => $a->getId()], 'Après');

        $comparaison = (new ComparaisonChiffrages())->comparer($avant, $apres);

        self::assertGreaterThan(0, $comparaison['ecart']);
        self::assertSame($apres->getTotalTtcCentimes() - $avant->getTotalTtcCentimes(), $comparaison['ecart']);
        $ecarts = array_filter($comparaison['lignes'], static fn ($l): bool => 0 !== $l->ecart());
        self::assertSame(['BA13'], array_values(array_map(static fn ($l): string => $l->cle, $ecarts)));
    }

    private function pdf(): ListeCoursesPdf
    {
        return self::getContainer()->get(ListeCoursesPdf::class);
    }

    public function test_liste_par_piece_avec_et_sans_prix_contient_les_memes_articles(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();
        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $a->getId()]);
        $portee = new PorteeExport(TypeExport::Piece);

        $avec = $this->pdf()->html($chiffrage, $portee, true);
        $sans = $this->pdf()->html($chiffrage, $portee, false);

        foreach (['BA13', 'M70', 'ISO70', 'Salon', 'Chambre'] as $texte) {
            self::assertStringContainsString($texte, $avec);
            self::assertStringContainsString($texte, $sans);
        }
        self::assertStringContainsString('Prix du lot', $avec);
        self::assertStringContainsString('Prix TTC à la date du 01/06/2024', $avec);
        self::assertStringNotContainsString('Prix du lot', $sans);
        self::assertStringNotContainsString('Prix TTC à la date', $sans);
        self::assertStringNotContainsString('&euro;', $sans);
    }

    public function test_liste_par_fournisseur_a_une_section_et_une_adresse_par_fournisseur(): void
    {
        [$projet, $a, $b] = $this->projetAvecDeuxFournisseurs();
        $chiffrage = $this->service()->creer($projet, TypeStrategie::Manuelle, new \DateTimeImmutable('2024-06-01'), ['choix' => [
            'BA13' => $a->getId(), 'M70' => $b->getId(), 'ISO70' => $b->getId(), 'ATT' => $b->getId(),
        ]]);
        $portee = new PorteeExport(TypeExport::Fournisseur, regroupement: Regroupement::Categorie);

        $sections = (new ListeCourses())->sections($chiffrage, $portee);
        $html = $this->pdf()->html($chiffrage, $portee, true);

        self::assertSame(['Alpha', 'Bravo'], array_map(static fn ($s): ?string => $s->titre, $sections));
        self::assertSame(2, substr_count($html, '<section'));
        self::assertStringContainsString('1 rue des Lots', $html);
        self::assertStringContainsString('Lyon', $html);
        self::assertStringContainsString('2 avenue du Pro', $html);
        self::assertSame(['BA13'], array_map(static fn ($i): string => $i->reference, $sections[0]->groupes[0]->items));
    }

    public function test_le_pdf_est_genere(): void
    {
        [$projet, $a] = $this->projetAvecDeuxFournisseurs();
        $chiffrage = $this->service()->creer($projet, TypeStrategie::FournisseurUnique, new \DateTimeImmutable('2024-06-01'), ['fournisseur' => $a->getId()]);

        $pdf = $this->pdf()->generer($chiffrage, new PorteeExport(TypeExport::Projet), true);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, \strlen($pdf));
    }
}
