<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Calcul\Systeme\RegistreSystemes;
use App\Entity\CoucheAppliquee;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\CoteCloison;

/**
 * Calcule un système appliqué : entités vers descriptions pures de couches, puis pipeline. Pour un système posé
 * sur une cloison, chaque face est calculée avec ses couches (celles sans face valent pour les deux).
 */
final class CalculSysteme
{
    public function __construct(
        private readonly RegistreSystemes $systemes,
        private readonly PipelineCouches $pipeline,
        private readonly AdaptateurSurface $adaptateur,
        private readonly SpecificationSerialiseur $serialiseur,
    ) {
    }

    /**
     * @return array<string, ResultatPipeline> résultat par support (clé : nom de la surface)
     */
    public function calculer(SystemeApplique $systeme): array
    {
        $definition = $this->systemes->get($systeme->getDefinition());
        $resultats = [];
        $surface = $systeme->getSurface();
        $surfaces = $surface instanceof Surface ? [$surface] : ($systeme->getCloison()?->getFaces()->getValues() ?? []);
        foreach ($surfaces as $surface) {
            $resultats[$surface->getNom()] = $this->pipeline->executer(
                $definition,
                $this->adaptateur->versCalculable($surface),
                $this->decrire($systeme, $surface->getCote()),
                $systeme->getParametres(),
            );
        }

        return $resultats;
    }

    /**
     * @return list<CoucheDecrite>
     */
    public function decrire(SystemeApplique $systeme, ?CoteCloison $cote = null): array
    {
        $couches = [];
        foreach ($systeme->getCouches() as $couche) {
            if (null !== $couche->getFace() && null !== $cote && $couche->getFace() !== $cote) {
                continue;
            }
            $couches[] = $this->vers($couche);
        }

        return $couches;
    }

    private function vers(CoucheAppliquee $couche): CoucheDecrite
    {
        $specification = $couche->getSpecification();

        return new CoucheDecrite(
            $couche->getOrdre(),
            $couche->getEmplacement(),
            $couche->getRole(),
            null !== $specification ? $this->serialiseur->depuis($specification) : null,
            $couche->getMateriau(),
            $couche->getOrientation() ?? 0,
            null !== $couche->getPosition() ? PositionCouche::from($couche->getPosition()) : null,
            $couche->getDecalage()['u'] ?? null,
            $couche->getEpaisseurOverride(),
            $couche->getFace()?->value,
        );
    }
}
