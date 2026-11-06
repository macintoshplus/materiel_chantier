<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Piece;
use App\Entity\Surface;
use App\Enum\TypeSurface;
use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\GenerateurMurs;
use App\Geometrie\GenerateurPlafond;
use App\Geometrie\GenerateurSol;
use App\Geometrie\SurfaceGeneree;

/**
 * Raccourci de création d'une pièce : sol, murs, plafond (plat ou double pente) et jonctions, à partir du
 * contour `formeSol` et de la hauteur sous plafond (hauteur d'égout pour une double pente). Les surfaces sont
 * rattachées à la pièce ; l'appelant persiste et flushe.
 */
final readonly class PieceCreationService
{
    public function __construct(private PieceJonctions $jonctions)
    {
    }

    /**
     * @param int|null    $hauteurFaitage null : plafond plat ; sinon double pente (sol rectangulaire)
     * @param string|null $axeFaitage     'x' ou 'y' ; par défaut parallèle au plus grand côté
     *
     * @return list<Avertissement>
     *
     * @throws ErreurGeometrique
     */
    public function generer(Piece $piece, ?int $hauteurFaitage = null, ?string $axeFaitage = null): array
    {
        if (null !== $piece->getSol()) {
            throw new \LogicException('The room already has surfaces.');
        }
        $hauteur = $piece->getHauteurSousPlafond();
        if (null === $hauteur || $hauteur <= 0) {
            throw new ErreurGeometrique('ceiling_invalid_heights', [], 'hauteurSousPlafond');
        }
        $sol = (new GenerateurSol())->generer($piece->getPolygoneSol());
        $murs = (new GenerateurMurs())->generer($sol->contour, $hauteur);
        $plafond = null === $hauteurFaitage
            ? new \App\Geometrie\PlafondGenere((new GenerateurPlafond())->plat($sol->contour, $hauteur))
            : (new GenerateurPlafond())->doublePente($sol->contour, $hauteur, $hauteurFaitage, $axeFaitage);

        $this->creer($piece, TypeSurface::Sol, $sol);
        foreach ($murs as $i => $mur) {
            $pignon = null;
            foreach ($plafond->pignons as $candidat) {
                if ($candidat->areteSol === $mur->areteSol) {
                    $pignon = $candidat;
                }
            }
            $this->creer($piece, TypeSurface::Mur, $pignon ?? $mur, 'Wall '.($i + 1));
        }
        foreach ($plafond->pans as $pan) {
            $this->creer($piece, TypeSurface::PlafondPan, $pan);
        }

        return $this->jonctions->synchroniser($piece);
    }

    private function creer(Piece $piece, TypeSurface $type, SurfaceGeneree $generee, ?string $nom = null): Surface
    {
        $surface = new Surface($piece, $type);
        $surface->setNom($nom ?? $generee->nom);
        $surface->appliquerGeneree($generee);

        return $surface;
    }
}
