<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Thermique\ResistanceThermique;
use App\Entity\Cloison;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\TypeSurface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Comparaison et activation des variantes de systèmes. La comparaison calcule chaque variante sans jamais
 * changer l'état actif ; l'activation est une action explicite.
 */
final class ServiceVariantes
{
    public function __construct(
        private readonly CalculSysteme $calcul,
        private readonly CloisonEditionService $cloisons,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<ComparaisonVariantes>
     */
    public function comparer(Surface|Piece|Projet $cible): array
    {
        $lignes = [];
        foreach ($this->supports($cible) as $support) {
            foreach ($support->getSystemes() as $systeme) {
                $lignes[] = $this->comparerUn($support, $systeme);
            }
        }

        return $lignes;
    }

    /**
     * Rend la variante active (et désactive les autres du même support). Pour une cloison, l'épaisseur est
     * recalculée à partir de cette variante et les faces sont régénérées.
     */
    public function activer(SystemeApplique $systeme): void
    {
        $systeme->activer();
        $cloison = $systeme->getCloison();
        if ($cloison instanceof Cloison) {
            $epaisseur = $this->calcul->calculerSysteme($systeme)->epaisseurTotale;
            if (null !== $epaisseur) {
                $this->cloisons->appliquer($cloison, $cloison->getTrace(), $epaisseur);

                return;
            }
        }
        $this->em->flush();
    }

    /**
     * @return list<Surface|Cloison>
     */
    private function supports(Surface|Piece|Projet $cible): array
    {
        if ($cible instanceof Surface) {
            return [$cible];
        }
        if ($cible instanceof Piece) {
            $supports = [];
            foreach ($cible->getSurfaces() as $surface) {
                if (null === $surface->getCloison()) {
                    $supports[] = $surface;
                }
            }
            foreach ($cible->getCloisons() as $cloison) {
                $supports[] = $cloison;
            }

            return $supports;
        }
        $supports = [];
        foreach ($cible->getPieces() as $piece) {
            array_push($supports, ...$this->supports($piece));
        }

        return $supports;
    }

    private function comparerUn(Surface|Cloison $support, SystemeApplique $systeme): ComparaisonVariantes
    {
        $resultat = $this->calcul->calculerSysteme($systeme);
        $id = $systeme->getId() ?? 0;
        if (!$resultat->estValide()) {
            return new ComparaisonVariantes($id, $support->getNom(), $systeme->getDefinition(), $systeme->getVariante(), $systeme->isActif(), null, null, null, null, $resultat->erreurs);
        }
        $type = $support instanceof Surface ? $support->getType() : TypeSurface::CloisonFace;
        $paroi = $support instanceof Surface ? ($support->getParoiExistante()?->resistanceMilli() ?? 0) : 0;
        $rt = ResistanceThermique::totale($type, $paroi, $resultat->rSystemeMilli);

        return new ComparaisonVariantes($id, $support->getNom(), $systeme->getDefinition(), $systeme->getVariante(), $systeme->isActif(), $rt, ResistanceThermique::coefficientU($rt), $resultat->epaisseurTotale);
    }
}
