<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Support;

use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\ResolveurMateriau;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ValidateurSysteme;
use App\Calcul\Surface\SurfaceCalculable;
use App\Catalogue\MaterielLisible;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\Plan;
use App\Geometrie\Point3D;
use App\Tests\Calcul\Systemes\TestCalculateurAttache;
use App\Tests\Calcul\Systemes\TestCalculateurIsolant;
use App\Tests\Calcul\Systemes\TestCalculateurOssature;
use App\Tests\Calcul\Systemes\TestCalculateurParement;

/**
 * Pipeline câblé à la main (sans noyau Symfony) avec les calculateurs de test, et couches du système de test.
 */
trait ScenarioCouches
{
    private function surface(int $largeur = 3000, int $hauteur = 2500): SurfaceCalculable
    {
        return new SurfaceCalculable(
            TypeSurface::Mur,
            new Plan(new Point3D(0, 0, 0), new Point3D(1000, 0, 0), new Point3D(0, 0, 1000)),
            ContourIdentifie::depuisTableau([
                ['id' => 'a', 'u' => 0, 'v' => 0],
                ['id' => 'b', 'u' => $largeur, 'v' => 0],
                ['id' => 'c', 'u' => $largeur, 'v' => $hauteur],
                ['id' => 'd', 'u' => 0, 'v' => $hauteur],
            ]),
        );
    }

    private function pipeline(SourceCandidatsDouble $source): PipelineCouches
    {
        $registre = new RegistreCalculateurs([
            new TestCalculateurAttache(),
            new TestCalculateurIsolant(),
            new TestCalculateurOssature(),
            new TestCalculateurParement(new CalepinagePlaques()),
        ]);

        return new PipelineCouches($registre, new ResolveurMateriau($source), new ValidateurSysteme($registre));
    }

    /**
     * 2 isolants (le second croisé) + ossature + 2 peaux de plaque, tous épinglés.
     *
     * @return list<CoucheDecrite>
     */
    private function couchesCroisees(?MaterielLisible $profil = null, ?int $decalageSecondePeau = null): array
    {
        return [
            new CoucheDecrite(0, 'attache', 'attache', materiau: ArticleDouble::attache()),
            new CoucheDecrite(1, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 60), position: PositionCouche::EntreOssature),
            new CoucheDecrite(2, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 60), orientation: 90, position: PositionCouche::Continue),
            new CoucheDecrite(3, 'ossature', 'ossature', materiau: $profil ?? ArticleDouble::profil(150)),
            new CoucheDecrite(4, 'parement', 'parement', materiau: ArticleDouble::plaque(), position: PositionCouche::SurOssature),
            new CoucheDecrite(5, 'parement', 'parement', materiau: ArticleDouble::plaque(), position: PositionCouche::SurOssature, decalage: $decalageSecondePeau),
        ];
    }
}
