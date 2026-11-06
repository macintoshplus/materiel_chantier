<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Entity\Piece;
use App\Enum\TypeAncrage;
use App\Form\Dto\CloisonSaisie;
use App\Geometrie\Ancrage;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\PlanModification;
use App\Geometrie\Point2D;
use App\Geometrie\Segment;
use App\Geometrie\TraceCloison;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée ou modifie une cloison à partir de la saisie : tracé (deux points, ou longueur et angle), décalage par rapport
 * à un mur, ancrages imposés. Même principe que le reste : `planifier` ne modifie rien, `enregistrer` applique.
 */
final readonly class EditionCloisonSaisie
{
    public function __construct(private CloisonEditionService $edition, private OuvertureEditionService $ouvertures, private EntityManagerInterface $em)
    {
    }

    /**
     * @throws ErreurGeometrique
     */
    public function planifier(Piece $piece, ?Cloison $cloison, CloisonSaisie $saisie): PlanModification
    {
        if (null !== $cloison) {
            return $this->edition->planifier($cloison, $this->trace($piece, $cloison, $saisie));
        }
        $temporaire = new Cloison($piece);
        try {
            return $this->edition->planifier($temporaire, $this->trace($piece, null, $saisie));
        } finally {
            $piece->getCloisons()->removeElement($temporaire);
        }
    }

    /**
     * @return array{0: Cloison, 1: PlanModification}
     *
     * @throws ErreurGeometrique
     */
    public function enregistrer(Piece $piece, ?Cloison $cloison, CloisonSaisie $saisie): array
    {
        $trace = $this->trace($piece, $cloison, $saisie);
        $nouvelle = null === $cloison;
        $cloison ??= new Cloison($piece);
        if ($nouvelle) {
            $cloison->setNom(self::nomParDefaut($piece));
            $this->em->persist($cloison);
        }
        try {
            $nom = null === $saisie->nom ? '' : trim($saisie->nom);
            if ('' !== $nom) {
                $cloison->setNom($nom);
            }
            $this->ouvertures->synchroniser($cloison, $saisie->ouvertures);
            $this->imposerAncrage($cloison, true, $saisie->ancrageDebut, $trace->debut);
            $this->imposerAncrage($cloison, false, $saisie->ancrageFin, $trace->fin);
            $plan = $this->edition->appliquer($cloison, $trace);
        } catch (ErreurGeometrique $e) {
            if ($nouvelle) {
                $piece->getCloisons()->removeElement($cloison);
                $this->em->detach($cloison);
            }
            throw $e;
        }
        $this->em->flush();

        return [$cloison, $plan];
    }

    /**
     * @throws ErreurGeometrique
     */
    private function trace(Piece $piece, ?Cloison $cloison, CloisonSaisie $saisie): TraceCloison
    {
        $actuelle = null === $cloison ? null : $cloison->getTrace();
        $debut = new Point2D($saisie->debutX ?? $actuelle?->debut->x ?? 0, $saisie->debutY ?? $actuelle?->debut->y ?? 0);
        if (null !== $saisie->finX && null !== $saisie->finY) {
            $trace = new TraceCloison($debut, new Point2D($saisie->finX, $saisie->finY));
        } elseif (null !== $saisie->longueur && null !== $saisie->angle) {
            $trace = new TraceCloison($debut, new Point2D($debut->x + $saisie->longueur, $debut->y));
            $trace = $trace->definirAngleDegres($saisie->angle);
        } else {
            throw new ErreurGeometrique('partition_input_missing');
        }
        if (null !== $actuelle && null !== $saisie->finX && null !== $saisie->finY) {
            // Longueur et angle ne s'appliquent que s'ils ont été modifiés par rapport au tracé enregistré.
            $inchange = $trace->debut->x === $actuelle->debut->x && $trace->debut->y === $actuelle->debut->y
                && $trace->fin->x === $actuelle->fin->x && $trace->fin->y === $actuelle->fin->y;
            if ($inchange && null !== $saisie->longueur && $saisie->longueur !== $actuelle->longueur()) {
                $trace = $trace->definirLongueur($saisie->longueur);
            }
            if ($inchange && null !== $saisie->angle && abs($saisie->angle - round($actuelle->angleDegres(), 1)) > 0.05) {
                $trace = $trace->definirAngleDegres($saisie->angle);
            }
        }
        if (null !== $saisie->murReference && '' !== $saisie->murReference && null !== $saisie->decalage) {
            $reference = $this->segmentMur($piece, (int) $saisie->murReference);
            if (null !== $reference) {
                $trace = $trace->decalerParRapportA($reference, $saisie->decalage);
            }
        }

        return $trace;
    }

    private function segmentMur(Piece $piece, int $id): ?Segment
    {
        $contour = $piece->getSol()?->getContour();
        foreach ($piece->getMurs() as $mur) {
            $arete = $mur->getAreteSol();
            if ($mur->getId() === $id && null !== $contour && null !== $arete && $contour->a($arete['debut']) && $contour->a($arete['fin'])) {
                return new Segment($contour->point($arete['debut']), $contour->point($arete['fin']));
            }
        }

        return null;
    }

    /**
     * `auto` : l'ancrage est recalculé (le forçage éventuel est levé) ; `libre` ou `mur:ID` / `cloison:ID` : imposé.
     *
     * @throws ErreurGeometrique anchor_target_unknown
     */
    private function imposerAncrage(Cloison $cloison, bool $debut, string $choix, Point2D $extremite): void
    {
        $cle = $debut ? 'debut' : 'fin';
        $parametres = $cloison->getParametres();
        $forces = \is_array($parametres['ancrages_forces'] ?? null) ? $parametres['ancrages_forces'] : [];
        if (CloisonSaisie::AUTO === $choix) {
            unset($forces[$cle]);
            $parametres['ancrages_forces'] = $forces;
            $cloison->setParametres($parametres);

            return;
        }
        if (CloisonSaisie::LIBRE === $choix) {
            $this->edition->forcerAncrage($cloison, $debut, Ancrage::libre());

            return;
        }
        [$type, $id] = array_pad(explode(':', $choix, 2), 2, '');
        $type = TypeAncrage::tryFrom($type);
        foreach ($this->edition->cibles($cloison, $extremite) as $cible) {
            if ($cible->type === $type && (string) $cible->id === $id) {
                $this->edition->forcerAncrage($cloison, $debut, new Ancrage($cible->type, $cible, $cible->axe->positionDe($extremite)));

                return;
            }
        }
        throw new ErreurGeometrique('anchor_target_unknown', [], $debut ? 'ancrageDebut' : 'ancrageFin');
    }

    private static function nomParDefaut(Piece $piece): string
    {
        $pris = [];
        foreach ($piece->getCloisons() as $autre) {
            $pris[$autre->getNom()] = true;
        }
        $n = 1;
        while (isset($pris['Partition '.$n])) {
            ++$n;
        }

        return 'Partition '.$n;
    }
}
