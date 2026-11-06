<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Piece;
use App\Form\Dto\PieceSaisie;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\PlanModification;
use App\Geometrie\Point2D;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traduit la saisie de l'éditeur de pièce en opération de `PieceEditionService`. Une seule opération sur les
 * sommets du sol par enregistrement : déplacer un sommet, en ajouter un sur une arête ou en supprimer un.
 */
final readonly class EditionPieceSaisie
{
    public function __construct(private PieceEditionService $edition, private EntityManagerInterface $em)
    {
    }

    /**
     * Impacts de l'opération demandée, sans rien modifier (le nom et la hauteur n'ont pas d'impact).
     *
     * @throws ErreurGeometrique
     */
    public function planifier(Piece $piece, PieceSaisie $saisie): PlanModification
    {
        $operation = $this->operation($piece, $saisie);
        if (null === $operation) {
            return new PlanModification();
        }

        return match ($operation['type']) {
            'deplacer' => $this->edition->planifierDeplacement($piece, $operation['id'], $operation['point']),
            'ajouter' => $this->edition->planifierScission($piece, $operation['id'], $operation['distance']),
            'supprimer' => $this->edition->planifierFusion($piece, $operation['id']),
        };
    }

    /**
     * @throws ErreurGeometrique
     */
    public function enregistrer(Piece $piece, PieceSaisie $saisie): PlanModification
    {
        $operation = $this->operation($piece, $saisie);
        $plan = new PlanModification();
        if (null !== $operation) {
            $plan = match ($operation['type']) {
                'deplacer' => $this->edition->appliquerDeplacement($piece, $operation['id'], $operation['point']),
                'ajouter' => $this->edition->appliquerScission($piece, $operation['id'], $operation['distance']),
                'supprimer' => $this->edition->appliquerFusion($piece, $operation['id']),
            };
        }
        $piece->setNom(trim($saisie->nom));
        $piece->setHauteurSousPlafond($saisie->hauteurSousPlafond);
        $this->em->flush();

        return $plan;
    }

    /**
     * @return array{type: 'deplacer', id: string, point: Point2D}|array{type: 'ajouter', id: string, distance: int}|array{type: 'supprimer', id: string}|null
     *
     * @throws ErreurGeometrique edit_one_vertex_at_a_time
     */
    private function operation(Piece $piece, PieceSaisie $saisie): ?array
    {
        $sol = $piece->getSol();
        if (null === $sol) {
            return null;
        }
        $contour = $sol->getContour();
        $operations = [];
        foreach ($saisie->sommets as $s) {
            if (!$contour->a($s->id)) {
                continue;
            }
            if ($s->supprimer) {
                $operations[] = ['type' => 'supprimer', 'id' => $s->id];
                continue;
            }
            $actuel = $contour->point($s->id);
            if (null !== $s->x && null !== $s->y && ($actuel->x !== $s->x || $actuel->y !== $s->y)) {
                $operations[] = ['type' => 'deplacer', 'id' => $s->id, 'point' => new Point2D($s->x, $s->y)];
            }
        }
        if (null !== $saisie->ajoutArete && '' !== $saisie->ajoutArete && null !== $saisie->ajoutDistance) {
            $operations[] = ['type' => 'ajouter', 'id' => $saisie->ajoutArete, 'distance' => $saisie->ajoutDistance];
        }
        if (\count($operations) > 1) {
            throw new ErreurGeometrique('edit_one_vertex_at_a_time');
        }

        return $operations[0] ?? null;
    }
}
