<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Surface;
use App\Enum\TypeSurface;
use App\Form\Dto\SurfaceSaisie;
use App\Geometrie\Avertissement;
use App\Geometrie\ConstructeurSurface;
use App\Geometrie\ContrainteSommet;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\TypeContrainte;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre la page d'une surface (mur, pan, sol) : nom, valeurs des contraintes de sommets (distance, angle),
 * ouvertures. Le contour est recalculé par `ConstructeurSurface` et les jonctions sont resynchronisées.
 */
final readonly class SurfaceEditionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OuvertureEditionService $ouvertures,
        private PieceJonctions $jonctions,
    ) {
    }

    /**
     * @return list<Avertissement> avertissements de jonction (longueurs d'arêtes jointes différentes)
     *
     * @throws ErreurGeometrique une contrainte invalide ; `champ` est l'identifiant du sommet fautif
     */
    public function enregistrer(Surface $surface, SurfaceSaisie $saisie): array
    {
        $avertissements = [];
        $contraintes = $surface->getContraintesSommets();
        $nouvelles = [];
        $change = false;
        $parId = [];
        foreach ($saisie->sommets as $s) {
            $parId[$s->id] = $s;
        }
        foreach ($contraintes as $c) {
            $s = $parId[$c->id] ?? null;
            if (null === $s || TypeContrainte::Fixe === $c->type || TypeContrainte::Controle === $c->type) {
                $nouvelles[] = $c;
                continue;
            }
            $distance = $s->distance ?? $c->distance;
            $angle = TypeContrainte::Angle === $c->type && null !== $s->angle ? (int) round($s->angle * 10) : $c->angle;
            if ($distance !== $c->distance || $angle !== $c->angle) {
                $change = true;
            }
            $nouvelles[] = new ContrainteSommet($c->id, $c->type, $c->depuis, $c->reference, $c->direction, $distance, $angle, $c->u, $c->v, $c->vers);
        }
        if ($change && TypeSurface::Sol !== $surface->getType()) {
            $contour = (new ConstructeurSurface())->construire($nouvelles);
            $surface->definirContour($contour);
            $surface->definirContraintesSommets($nouvelles);
        }

        $surface->setNom(trim($saisie->nom));
        $this->ouvertures->synchroniser($surface, $saisie->ouvertures);
        if ($change) {
            $avertissements = $this->jonctions->synchroniser($surface->getPiece());
        }
        $this->em->flush();

        return $avertissements;
    }
}
