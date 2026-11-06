<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Entity\Surface;
use App\Enum\CoteCloison;
use App\Enum\TypeAncrage;
use App\Enum\TypeSurface;
use App\Geometrie\Ancrage;
use App\Geometrie\Avertissement;
use App\Geometrie\CibleAncrage;
use App\Geometrie\DetecteurAncrages;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\FacesCloison;
use App\Geometrie\GenerateurFacesCloison;
use App\Geometrie\JonctionCloisonDecrite;
use App\Geometrie\JonctionsCloison;
use App\Geometrie\OuvertureAPlacer;
use App\Geometrie\PlacementSurSurface;
use App\Geometrie\PlanModification;
use App\Geometrie\Point2D;
use App\Geometrie\Segment;
use App\Geometrie\Tolerance;
use App\Geometrie\TraceCloison;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Édition d'une cloison : le tracé (ou l'épaisseur) change, ses faces A/B, ses ancrages, ses jonctions et le contrôle
 * de ses ouvertures sont recalculés. Les ouvertures ne sont jamais supprimées : celles qui sortent de la face sont
 * signalées. Chaque changement se fait en deux temps : `planifier` (sans rien modifier) puis `appliquer`.
 */
final readonly class CloisonEditionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PieceJonctions $jonctions,
    ) {
    }

    /**
     * Impacts d'un nouveau tracé, sans rien modifier.
     *
     * @throws ErreurGeometrique tracé invalide (longueur ou épaisseur nulle, pas de plafond)
     */
    public function planifier(Cloison $cloison, TraceCloison $trace, ?int $epaisseur = null): PlanModification
    {
        return $this->analyser($cloison, $trace, $epaisseur ?? $cloison->getEpaisseur())['plan'];
    }

    /**
     * Applique le nouveau tracé, recalcule faces, ancrages et jonctions, suit les cloisons ancrées sur celle-ci.
     *
     * @throws ErreurGeometrique
     */
    public function appliquer(Cloison $cloison, TraceCloison $trace, ?int $epaisseur = null): PlanModification
    {
        $epaisseur ??= $cloison->getEpaisseur();
        $analyse = $this->analyser($cloison, $trace, $epaisseur);
        $this->ecrire($cloison, $trace, $epaisseur, $analyse);
        $this->em->flush();
        $impacts = $analyse['plan']->impacts;
        foreach ($analyse['suiveuses'] as [$autre, $debut, $point]) {
            array_push($impacts, ...$this->suivre($autre, $debut, $point));
        }
        $this->em->flush();

        return new PlanModification($impacts, $analyse['plan']->confirmationRequise);
    }

    /**
     * Recalcule une cloison depuis son tracé actuel (après un changement de la pièce : mur déplacé, sommet fendu…).
     * Une cloison qui devient invalide est signalée sans lever d'exception.
     */
    public function regenerer(Cloison $cloison): PlanModification
    {
        try {
            return $this->appliquer($cloison, $cloison->getTrace());
        } catch (ErreurGeometrique $e) {
            return new PlanModification([new Avertissement('partition_invalid_after_change', ['partition' => $cloison->getNom()]), $e->avertissement()], true);
        }
    }

    public function deplacerExtremite(Cloison $cloison, bool $debut, Point2D $point): PlanModification
    {
        return $this->appliquer($cloison, $cloison->getTrace()->deplacerExtremite($debut, $point));
    }

    public function definirLongueur(Cloison $cloison, int $longueur): PlanModification
    {
        return $this->appliquer($cloison, $cloison->getTrace()->definirLongueur($longueur));
    }

    public function definirAngle(Cloison $cloison, float $degres): PlanModification
    {
        return $this->appliquer($cloison, $cloison->getTrace()->definirAngleDegres($degres));
    }

    public function decaler(Cloison $cloison, Segment $reference, int $distance): PlanModification
    {
        return $this->appliquer($cloison, $cloison->getTrace()->decalerParRapportA($reference, $distance));
    }

    /**
     * Impose un ancrage (il n'est plus recalculé tant qu'il n'est pas libéré).
     */
    public function forcerAncrage(Cloison $cloison, bool $debut, Ancrage $ancrage): void
    {
        $tableau = $ancrage->versTableau();
        $debut ? $cloison->setAncrageDebut($tableau) : $cloison->setAncrageFin($tableau);
        $parametres = $cloison->getParametres();
        $forces = \is_array($parametres['ancrages_forces'] ?? null) ? $parametres['ancrages_forces'] : [];
        $forces[$debut ? 'debut' : 'fin'] = true;
        $parametres['ancrages_forces'] = $forces;
        $cloison->setParametres($parametres);
    }

    /**
     * @return array{faces: FacesCloison, debut: Ancrage, fin: Ancrage, decrites: list<JonctionCloisonDecrite>, plan: PlanModification, suiveuses: list<array{Cloison, bool, Point2D}>}
     */
    private function analyser(Cloison $cloison, TraceCloison $trace, ?int $epaisseur): array
    {
        $piece = $cloison->getPiece();
        $plafond = $piece->hauteurPlafond();
        $faces = (new GenerateurFacesCloison())->generer($trace, $epaisseur, $plafond);
        $impacts = [];
        $confirmation = false;

        $sol = $piece->getPolygoneSol();
        if ([] !== $piece->getFormeSol()) {
            foreach ([$trace->debut, $trace->fin] as $extremite) {
                if (!$sol->contient($extremite, true)) {
                    $impacts[] = new Avertissement('partition_end_outside_room');
                    $confirmation = true;
                    break;
                }
            }
        }

        $forces = \is_array($cloison->getParametres()['ancrages_forces'] ?? null) ? $cloison->getParametres()['ancrages_forces'] : [];
        $detecteur = new DetecteurAncrages();
        $ancrages = [];
        foreach ([[true, $trace->debut, $cloison->getAncrageDebut()], [false, $trace->fin, $cloison->getAncrageFin()]] as [$debut, $point, $actuel]) {
            $cle = $debut ? 'debut' : 'fin';
            if (!empty($forces[$cle])) {
                $type = TypeAncrage::from($actuel['type']);
                $cibleForcee = null;
                foreach ($this->cibles($cloison, $point) as $candidate) {
                    if (null !== $actuel['cible'] && $candidate->type === $type && $candidate->id === $actuel['cible']) {
                        $cibleForcee = $candidate;
                    }
                }
                $ancrage = new Ancrage($type, $cibleForcee, $actuel['position']);
            } else {
                $ancrage = $detecteur->proposer($point, $this->cibles($cloison, $point));
                if (TypeAncrage::Libre->value !== $actuel['type'] && TypeAncrage::Libre === $ancrage->type) {
                    $impacts[] = new Avertissement('partition_anchor_lost', ['partition' => $cloison->getNom(), 'end' => $debut ? 'start' : 'end']);
                    $confirmation = true;
                } elseif (TypeAncrage::Libre !== $ancrage->type && TypeAncrage::Libre->value !== $actuel['type'] && ($ancrage->versTableau()['cible'] !== $actuel['cible'] || $ancrage->type->value !== $actuel['type'])) {
                    $impacts[] = new Avertissement('partition_anchor_changed', ['partition' => $cloison->getNom(), 'end' => $debut ? 'start' : 'end']);
                }
            }
            $ancrages[] = $ancrage;
        }

        $decrites = (new JonctionsCloison())->decrire($faces, $trace, $plafond, $ancrages[0], $ancrages[1]);

        $aPlacer = [];
        foreach ($cloison->getOuvertures() as $ouverture) {
            $aPlacer[] = new OuvertureAPlacer($ouverture->libelle(), $ouverture->getForme());
        }
        foreach ((new PlacementSurSurface())->verifier($faces->b->contour->polygone(), $aPlacer) as $avertissement) {
            $impacts[] = $avertissement;
        }

        $suiveuses = [];
        $ancien = $cloison->getTrace();
        $id = $cloison->getId();
        foreach ($piece->getCloisons() as $autre) {
            if ($autre === $cloison || null === $id) {
                continue;
            }
            foreach ([[true, $autre->getAncrageDebut(), $autre->getTrace()->debut], [false, $autre->getAncrageFin(), $autre->getTrace()->fin]] as [$debut, $ancrage, $point]) {
                if (TypeAncrage::Cloison->value !== $ancrage['type'] || $ancrage['cible'] !== $id) {
                    continue;
                }
                $cote = $ancien->segment()->distanceSigneeALaDroite($point) >= 0;
                $position = $ancrage['position'] ?? 0;
                $longueur = $trace->longueur();
                if ($position < 0 || $position > $longueur) {
                    $impacts[] = new Avertissement('partition_anchor_lost', ['partition' => $autre->getNom(), 'end' => $debut ? 'start' : 'end']);
                    $confirmation = true;
                    $suiveuses[] = [$autre, $debut, $point];
                    continue;
                }
                $nouveau = self::pointSurCloison($trace, $cote, $position);
                $impacts[] = new Avertissement('partition_anchor_affected', ['partition' => $autre->getNom()]);
                $suiveuses[] = [$autre, $debut, $nouveau];
            }
        }

        return [
            'faces' => $faces,
            'debut' => $ancrages[0],
            'fin' => $ancrages[1],
            'decrites' => $decrites,
            'plan' => new PlanModification($impacts, $confirmation),
            'suiveuses' => $suiveuses,
        ];
    }

    /**
     * @param array{faces: FacesCloison, debut: Ancrage, fin: Ancrage, decrites: list<JonctionCloisonDecrite>, plan: PlanModification, suiveuses: list<array{Cloison, bool, Point2D}>} $analyse
     */
    private function ecrire(Cloison $cloison, TraceCloison $trace, ?int $epaisseur, array $analyse): void
    {
        $cloison->definirTrace($trace);
        $cloison->setEpaisseur($epaisseur);
        $forces = \is_array($cloison->getParametres()['ancrages_forces'] ?? null) ? $cloison->getParametres()['ancrages_forces'] : [];
        if (empty($forces['debut'])) {
            $cloison->setAncrageDebut($analyse['debut']->versTableau());
        }
        if (empty($forces['fin'])) {
            $cloison->setAncrageFin($analyse['fin']->versTableau());
        }

        $piece = $cloison->getPiece();
        foreach ([[CoteCloison::A, $analyse['faces']->a], [CoteCloison::B, $analyse['faces']->b]] as [$cote, $generee]) {
            $face = null;
            foreach ($cloison->getFaces() as $existante) {
                if ($cote === $existante->getCote()) {
                    $face = $existante;
                }
            }
            if (null === $face) {
                $face = new Surface($piece, TypeSurface::CloisonFace);
                $face->rattacherACloison($cloison, $cote);
            }
            $face->setNom($generee->nom);
            $face->appliquerGeneree($generee);
            $this->em->persist($face);
        }

        $cibles = [];
        foreach ($piece->getSurfaces() as $surface) {
            $cibles[$surface->cle()] = $surface;
        }
        $this->jonctions->synchroniserCloison($cloison, $analyse['decrites'], $cibles);
    }

    /**
     * @return list<Avertissement>
     */
    private function suivre(Cloison $cloison, bool $debut, Point2D $point): array
    {
        $trace = $cloison->getTrace()->deplacerExtremite($debut, $point);
        try {
            return $this->appliquerSansSuite($cloison, $trace);
        } catch (ErreurGeometrique $e) {
            return [new Avertissement('partition_invalid_after_change', ['partition' => $cloison->getNom()]), $e->avertissement()];
        }
    }

    /**
     * @return list<Avertissement>
     */
    private function appliquerSansSuite(Cloison $cloison, TraceCloison $trace): array
    {
        $analyse = $this->analyser($cloison, $trace, $cloison->getEpaisseur());
        $this->ecrire($cloison, $trace, $cloison->getEpaisseur(), $analyse);

        return $analyse['plan']->impacts;
    }

    /**
     * Cibles d'ancrage possibles (murs, autres cloisons) pour une extrémité.
     *
     * @return list<CibleAncrage>
     */
    public function cibles(Cloison $cloison, Point2D $extremite): array
    {
        $piece = $cloison->getPiece();
        $cibles = [];
        $sol = $piece->getSol();
        if (null !== $sol) {
            $contour = $sol->getContour();
            foreach ($piece->getMurs() as $mur) {
                $arete = $mur->getAreteSol();
                if (null === $arete || !$contour->a($arete['debut']) || !$contour->a($arete['fin'])) {
                    continue;
                }
                $cibles[] = new CibleAncrage(
                    TypeAncrage::Mur,
                    $mur->getId(),
                    $mur->cle(),
                    new Segment($contour->point($arete['fin']), $contour->point($arete['debut'])),
                    0,
                    $arete['fin'],
                    $arete['debut'],
                );
            }
        }
        foreach ($piece->getCloisons() as $autre) {
            if ($autre === $cloison) {
                continue;
            }
            $trace = $autre->getTrace();
            $epaisseur = $autre->getEpaisseur() ?? GenerateurFacesCloison::EPAISSEUR_PROVISOIRE;
            $coteA = $trace->segment()->distanceSigneeALaDroite($extremite) >= 0;
            $cible = null;
            foreach ($autre->getFaces() as $face) {
                if ($coteA === (CoteCloison::A === $face->getCote())) {
                    $cible = $face;
                }
            }
            if (null === $cible) {
                continue;
            }
            $cibles[] = new CibleAncrage(
                TypeAncrage::Cloison,
                $autre->getId(),
                $cible->cle(),
                $coteA ? new Segment($trace->fin, $trace->debut) : $trace->segment(),
                $epaisseur,
                $coteA ? 'fb' : 'db',
                $coteA ? 'db' : 'fb',
            );
        }

        return $cibles;
    }

    /**
     * Point de la cloison à `position` mm de l'extrémité qui sert d'origine à la face (début pour B, fin pour A).
     */
    private static function pointSurCloison(TraceCloison $trace, bool $coteA, int $position): Point2D
    {
        $longueur = max(1, $trace->longueur());
        $v = $trace->segment()->vecteur();
        $origine = $coteA ? $trace->fin : $trace->debut;
        $signe = $coteA ? -1 : 1;

        return new Point2D(
            $origine->x + $signe * Tolerance::arrondir($v->x * $position / $longueur),
            $origine->y + $signe * Tolerance::arrondir($v->y * $position / $longueur),
        );
    }
}
