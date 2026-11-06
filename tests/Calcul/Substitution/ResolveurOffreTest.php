<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Substitution;

use App\Calcul\Substitution\DemandeOffre;
use App\Calcul\Substitution\NatureOffre;
use App\Calcul\Substitution\Offre;
use App\Calcul\Substitution\ResolveurOffre;
use App\Calcul\Substitution\SourceOffres;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Critere;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Tests\Calcul\Support\ArticleDouble;
use App\Tests\Calcul\Support\SourceCandidatsDouble;
use PHPUnit\Framework\TestCase;

final class ResolveurOffreTest extends TestCase
{
    private function rail(string $ref, int $longueur): ArticleDouble
    {
        return new ArticleDouble('profil_ossature', ['reference' => $ref, 'longueur' => $longueur, 'serie' => 48, 'famille' => 'rail']);
    }

    /**
     * @param array<int, list<MaterielLisible>> $catalogue articles vendus par fournisseur
     */
    private function source(array $catalogue): SourceOffres
    {
        return new class($catalogue) implements SourceOffres {
            /** @param array<int, list<MaterielLisible>> $catalogue */
            public function __construct(private readonly array $catalogue)
            {
            }

            public function offre(MaterielLisible $article, int $fournisseur): ?Offre
            {
                return \in_array($article, $this->catalogue[$fournisseur] ?? [], true) ? new Offre($article, $fournisseur) : null;
            }
        };
    }

    private function specification(): MaterielSpecification
    {
        return MaterielSpecification::creer('ossature', new ProfilOssature(), [new Critere('serie', Operateur::Egal, 48)]);
    }

    public function test_correspondance_exacte_sans_chercher_d_equivalent(): void
    {
        $r3 = $this->rail('R3', 3000);
        $r26 = $this->rail('R26', 2600);
        $candidats = new SourceCandidatsDouble([$r26, $r3]);
        $resolveur = ResolveurOffre::standard($this->source([1 => [$r3, $r26]]), $candidats);

        $resultat = $resolveur->resoudre(new DemandeOffre($r3, $this->specification(), 1));

        self::assertSame($r3, $resultat->offre?->article);
        self::assertSame(NatureOffre::Exacte, $resultat->offre->nature);
        self::assertSame([], $resultat->avertissements);
        self::assertSame([], $candidats->demandes);
    }

    public function test_equivalent_seulement_sans_correspondance_exacte(): void
    {
        $r3 = $this->rail('R3', 3000);
        $r26 = $this->rail('R26', 2600);
        $resolveur = ResolveurOffre::standard($this->source([1 => [$r26]]), new SourceCandidatsDouble([$r3, $r26]));

        $resultat = $resolveur->resoudre(new DemandeOffre($r3, $this->specification(), 1));

        self::assertSame($r26, $resultat->offre?->article);
        self::assertSame(NatureOffre::Equivalente, $resultat->offre->nature);
        self::assertSame('offer_equivalent_used', $resultat->avertissements[0]->code);
    }

    public function test_equivalent_refuse_ou_sans_specification_donne_prix_manquant(): void
    {
        $r3 = $this->rail('R3', 3000);
        $r26 = $this->rail('R26', 2600);
        $resolveur = ResolveurOffre::standard($this->source([1 => [$r26]]), new SourceCandidatsDouble([$r3, $r26]));

        $interdit = $resolveur->resoudre(new DemandeOffre($r3, $this->specification(), 1, false));
        $sansSpec = $resolveur->resoudre(new DemandeOffre($r3, null, 1));

        foreach ([$interdit, $sansSpec] as $resultat) {
            self::assertTrue($resultat->estManquant());
            self::assertSame('offer_missing', $resultat->avertissements[0]->code);
        }
    }

    public function test_autre_fournisseur_en_dernier_recours_avant_prix_manquant(): void
    {
        $r3 = $this->rail('R3', 3000);
        $resolveur = ResolveurOffre::standard($this->source([2 => [$r3]]), new SourceCandidatsDouble([$r3]));

        $autre = $resolveur->resoudre(new DemandeOffre($r3, null, 1, true, [2]));
        $aucun = $resolveur->resoudre(new DemandeOffre($r3, null, 1, true, [3]));

        self::assertSame(NatureOffre::ExacteAutreFournisseur, $autre->offre?->nature);
        self::assertSame(2, $autre->offre->fournisseur);
        self::assertSame('offer_other_supplier_used', $autre->avertissements[0]->code);
        self::assertTrue($aucun->estManquant());
    }
}
