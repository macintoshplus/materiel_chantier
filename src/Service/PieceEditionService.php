<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Surface;
use App\Enum\TypeSurface;
use App\Geometrie\Avertissement;
use App\Geometrie\ConstructeurSurface;
use App\Geometrie\ContrainteSommet;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\GenerateurMurs;
use App\Geometrie\GenerateurSol;
use App\Geometrie\OuvertureAPlacer;
use App\Geometrie\PlacementSurSurface;
use App\Geometrie\PlanModification;
use App\Geometrie\Plan;
use App\Geometrie\Point2D;
use App\Geometrie\Point3D;
use App\Geometrie\Polygone;
use App\Geometrie\Segment;
use App\Geometrie\SurfaceGeneree;
use App\Geometrie\Tolerance;
use App\Geometrie\TypeContrainte;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Édition du contour du sol d'une pièce : déplacer, scinder (ajouter) ou fusionner (retirer) un sommet. Les murs
 * gardent leur identité, leur nom et leurs ouvertures ; une ouverture qui sort de son mur est signalée, jamais
 * supprimée. Les pans de plafond ne sont pas recalculés (avertissement `ceiling_not_updated`).
 *
 * Chaque opération existe en `planifier*` (aucune modification, renvoie les impacts) et `appliquer*`.
 * Un nouveau contour doit rester simple, anti-horaire et garder au moins 3 sommets.
 */
final readonly class PieceEditionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PieceJonctions $jonctions,
        private CloisonEditionService $cloisons,
    ) {
    }

    /**
     * @throws ErreurGeometrique
     */
    public function planifierDeplacement(Piece $piece, string $idSommet, Point2D $point): PlanModification
    {
        return $this->calculerDeplacement($piece, $idSommet, $point)['plan'];
    }

    /**
     * @throws ErreurGeometrique
     */
    public function appliquerDeplacement(Piece $piece, string $idSommet, Point2D $point): PlanModification
    {
        return $this->appliquer($piece, $this->calculerDeplacement($piece, $idSommet, $point));
    }

    /**
     * Ajoute un sommet sur l'arête qui part de `$idDebutArete`, à `$distance` mm de ce sommet.
     *
     * @throws ErreurGeometrique
     */
    public function planifierScission(Piece $piece, string $idDebutArete, int $distance): PlanModification
    {
        return $this->calculerScission($piece, $idDebutArete, $distance)['plan'];
    }

    /**
     * @throws ErreurGeometrique
     */
    public function appliquerScission(Piece $piece, string $idDebutArete, int $distance): PlanModification
    {
        return $this->appliquer($piece, $this->calculerScission($piece, $idDebutArete, $distance));
    }

    /**
     * Retire un sommet : les deux murs voisins n'en font plus qu'un. Une confirmation est demandée si leurs noms diffèrent.
     *
     * @throws ErreurGeometrique
     */
    public function planifierFusion(Piece $piece, string $idSommet): PlanModification
    {
        return $this->calculerFusion($piece, $idSommet)['plan'];
    }

    /**
     * @throws ErreurGeometrique
     */
    public function appliquerFusion(Piece $piece, string $idSommet): PlanModification
    {
        return $this->appliquer($piece, $this->calculerFusion($piece, $idSommet));
    }

    /**
     * @return array<string, mixed>
     */
    private function calculerDeplacement(Piece $piece, string $id, Point2D $point): array
    {
        [$ids, $points] = $this->sommets($piece);
        $i = array_search($id, $ids, true);
        if (false === $i) {
            throw new ErreurGeometrique('vertex_unknown');
        }
        $points = array_map(static fn (Point2D $p, int $k): Point2D => $k === $i ? $point : $p, $points, array_keys($points));
        $sol = $this->sol($ids, $points);
        $n = \count($ids);
        $impacts = [];
        $murs = [];
        foreach ([($i + $n - 1) % $n, $i] as $k) {
            $mur = $this->murDeLArete($piece, $ids[$k]);
            if (null === $mur) {
                continue;
            }
            $generee = $this->largeurMiseAJour($mur, $points[$k], $points[($k + 1) % $n]);
            if ($generee['forme_conservee']) {
                $impacts[] = new Avertissement('wall_shape_unchanged', ['wall' => $mur->getNom()]);
            }
            $murs[] = ['surface' => $mur, 'generee' => $generee['generee'], 'nom' => $mur->getNom(), 'ouvertures' => $this->ouverturesDe($mur, 0)];
        }

        return $this->finaliser($piece, $sol, $murs, $impacts, false, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculerScission(Piece $piece, string $idDebutArete, int $distance): array
    {
        [$ids, $points] = $this->sommets($piece);
        $i = array_search($idDebutArete, $ids, true);
        if (false === $i) {
            throw new ErreurGeometrique('vertex_unknown');
        }
        $n = \count($ids);
        $a = $points[$i];
        $b = $points[($i + 1) % $n];
        $longueur = $a->distanceA($b);
        if ($distance <= Tolerance::MM || $distance >= $longueur - Tolerance::MM) {
            throw new ErreurGeometrique('split_position_invalid');
        }
        $p = new Point2D(
            $a->x + Tolerance::arrondir(($b->x - $a->x) * $distance / $longueur),
            $a->y + Tolerance::arrondir(($b->y - $a->y) * $distance / $longueur),
        );
        $nouvelId = $this->idLibre($ids);
        array_splice($ids, $i + 1, 0, [$nouvelId]);
        array_splice($points, $i + 1, 0, [$p]);
        $sol = $this->sol($ids, $points);

        $mur = $this->murDeLArete($piece, $idDebutArete);
        $impacts = [];
        $murs = [];
        $nouveau = null;
        if (null !== $mur) {
            [$hDebut, $hFin] = $this->hauteurs($piece, $mur);
            $hP = Tolerance::arrondir($hDebut + ($hFin - $hDebut) * $distance / $longueur);
            $gen = new GenerateurMurs();
            // arête A -> P : le mur existant (première moitié) ; arête P -> B : le nouveau mur
            $premiere = $gen->mur($idDebutArete, $a, $nouvelId, $p, $hDebut, $hP);
            $seconde = $gen->mur($nouvelId, $p, $ids[($i + 2) % \count($ids)], $b, $hP, $hFin);
            $largeurSeconde = $p->distanceA($b);
            $dansPremiere = [];
            $dansSeconde = [];
            foreach ($mur->getOuvertures() as $ouverture) {
                if ($ouverture->getU() >= $largeurSeconde - Tolerance::MM) {
                    $dansPremiere[] = [$ouverture, -$largeurSeconde, false];
                } else {
                    $dansSeconde[] = [$ouverture, 0, false];
                    if ($ouverture->getU() + $ouverture->getLargeur() > $largeurSeconde + Tolerance::MM) {
                        $impacts[] = new Avertissement('opening_straddles_split', ['opening' => $ouverture->libelle()]);
                    }
                }
            }
            $murs[] = ['surface' => $mur, 'generee' => $premiere, 'nom' => $mur->getNom().' (1)', 'ouvertures' => $dansPremiere];
            $murs[] = ['surface' => null, 'generee' => $seconde, 'nom' => $mur->getNom().' (2)', 'ouvertures' => $dansSeconde];
            $nouveau = $nouvelId;
        }

        return $this->finaliser($piece, $sol, $murs, $impacts, false, $nouveau);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculerFusion(Piece $piece, string $id): array
    {
        [$ids, $points] = $this->sommets($piece);
        $i = array_search($id, $ids, true);
        if (false === $i) {
            throw new ErreurGeometrique('vertex_unknown');
        }
        if (\count($ids) <= 3) {
            throw new ErreurGeometrique('vertex_min_count');
        }
        $n = \count($ids);
        $iPrecedent = ($i + $n - 1) % $n;
        $iSuivant = ($i + 1) % $n;
        $a = $points[$iPrecedent];
        $v = $points[$i];
        $b = $points[$iSuivant];
        $idPrecedent = $ids[$iPrecedent];
        $idSuivant = $ids[$iSuivant];
        array_splice($ids, $i, 1);
        array_splice($points, $i, 1);
        $sol = $this->sol($ids, $points);

        $murPrecedent = $this->murDeLArete($piece, $idPrecedent);
        $murSuivant = $this->murDeLArete($piece, $id);
        $impacts = [];
        $murs = [];
        $supprime = null;
        $confirmation = false;
        if (null !== $murPrecedent && null !== $murSuivant) {
            [$hDebut] = $this->hauteurs($piece, $murPrecedent);
            [, $hFin] = $this->hauteurs($piece, $murSuivant);
            $fusion = (new GenerateurMurs())->mur($idPrecedent, $a, $idSuivant, $b, $hDebut, $hFin);
            $largeurSuivant = $v->distanceA($b);
            $ouvertures = [];
            foreach ($murPrecedent->getOuvertures() as $o) {
                $ouvertures[] = [$o, $largeurSuivant, false];
            }
            foreach ($murSuivant->getOuvertures() as $o) {
                $ouvertures[] = [$o, 0, true];
                $impacts[] = new Avertissement('opening_moved', ['opening' => $o->libelle()]);
            }
            if ($murPrecedent->getNom() !== $murSuivant->getNom()) {
                $impacts[] = new Avertissement('merge_names_differ', ['first' => $murPrecedent->getNom(), 'second' => $murSuivant->getNom()]);
                $confirmation = true;
            }
            $murs[] = ['surface' => $murPrecedent, 'generee' => $fusion, 'nom' => $murPrecedent->getNom(), 'ouvertures' => $ouvertures];
            $supprime = $murSuivant;
        }

        return $this->finaliser($piece, $sol, $murs, $impacts, $confirmation, null, $supprime);
    }

    /**
     * Contrôle le contour, ajoute les avertissements communs (plafond, ouvertures, jonctions) et fabrique le plan.
     *
     * @param list<array<string, mixed>> $murs
     * @param list<Avertissement>        $impacts
     *
     * @return array<string, mixed>
     */
    private function finaliser(Piece $piece, SurfaceGeneree $sol, array $murs, array $impacts, bool $confirmation, ?string $nouvelId, ?Surface $supprime = null): array
    {
        if ([] !== $piece->getPans()) {
            $impacts[] = new Avertissement('ceiling_not_updated');
        }
        foreach ($murs as $mur) {
            $placees = [];
            $forme = $mur['generee']->contour->polygone();
            foreach ($mur['ouvertures'] as [$ouverture, $decalage]) {
                $placees[] = new OuvertureAPlacer($ouverture->libelle(), self::decaler($ouverture->getForme(), $decalage));
            }
            array_push($impacts, ...(new PlacementSurSurface())->verifier($forme, $placees));
        }

        return [
            'sol' => $sol,
            'murs' => $murs,
            'supprime' => $supprime,
            'nouvelId' => $nouvelId,
            'plan' => new PlanModification($impacts, $confirmation),
        ];
    }

    /**
     * @param array<string, mixed> $calcul
     */
    private function appliquer(Piece $piece, array $calcul): PlanModification
    {
        /** @var SurfaceGeneree $sol */
        $sol = $calcul['sol'];
        /** @var PlanModification $plan */
        $plan = $calcul['plan'];
        $piece->setFormeSol(array_map(static fn (Point2D $p): array => ['x' => $p->x, 'y' => $p->y], $sol->contour->polygone()->points));
        $piece->getSol()?->appliquerGeneree($sol);

        /** @var list<array{surface: ?Surface, generee: SurfaceGeneree, nom: string, ouvertures: list<array{Ouverture, int, bool}>}> $murs */
        $murs = $calcul['murs'];
        foreach ($murs as $mur) {
            $surface = $mur['surface'];
            if (null === $surface) {
                $surface = new Surface($piece, TypeSurface::Mur);
                $this->em->persist($surface);
            }
            $surface->setNom($mur['nom']);
            $surface->appliquerGeneree($mur['generee']);
            foreach ($mur['ouvertures'] as [$ouverture, $decalage]) {
                if ($ouverture->getSurface() !== $surface) {
                    $ouverture->deplacerVers($surface);
                }
                if (0 !== $decalage) {
                    $ouverture->definirRectangle($ouverture->getU() + $decalage, $ouverture->getV(), $ouverture->getLargeur(), $ouverture->getHauteur());
                    $ouverture->setPolygone($this->polygoneDecale($ouverture, $decalage));
                }
            }
        }
        /** @var Surface|null $supprime */
        $supprime = $calcul['supprime'];
        if (null !== $supprime) {
            $this->jonctions->retirerDeLaSurface($piece, $supprime);
            $piece->getSurfaces()->removeElement($supprime);
            $this->em->remove($supprime);
        }
        $this->em->flush();

        $impacts = $plan->impacts;
        array_push($impacts, ...$this->jonctions->synchroniser($piece));
        $confirmation = $plan->confirmationRequise;
        foreach ($piece->getCloisons() as $cloison) {
            $retour = $this->cloisons->regenerer($cloison);
            array_push($impacts, ...$retour->impacts);
            $confirmation = $confirmation || $retour->confirmationRequise;
        }
        $this->em->flush();

        return new PlanModification($impacts, $confirmation);
    }

    /**
     * @return array{list<string>, list<Point2D>}
     */
    private function sommets(Piece $piece): array
    {
        $sol = $piece->getSol();
        if (null === $sol) {
            throw new \LogicException('The room has no floor surface.');
        }
        $contour = $sol->getContour();

        return [$contour->ids(), array_map(static fn ($s): Point2D => $s->point, $contour->sommets)];
    }

    /**
     * @param list<string> $ids
     * @param list<Point2D> $points
     */
    private function sol(array $ids, array $points): SurfaceGeneree
    {
        if (\count($ids) < 3) {
            throw new ErreurGeometrique('vertex_min_count');
        }
        $polygone = new Polygone($points);
        if (!$polygone->estSimple() || !$polygone->sensAntiHoraire()) {
            throw new ErreurGeometrique('floor_orientation_changed');
        }

        return (new GenerateurSol())->generer($polygone, $ids);
    }

    /**
     * @param list<string> $ids
     */
    private function idLibre(array $ids): string
    {
        $max = 0;
        foreach ($ids as $id) {
            if (1 === preg_match('/^S(\d+)$/', $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return GenerateurSol::idSommet($max + 1);
    }

    private function murDeLArete(Piece $piece, string $idDebut): ?Surface
    {
        foreach ($piece->getMurs() as $mur) {
            if ($idDebut === ($mur->getAreteSol()['debut'] ?? null)) {
                return $mur;
            }
        }

        return null;
    }

    /**
     * Hauteurs du mur aux sommets début et fin de son arête.
     *
     * @return array{int, int}
     */
    private function hauteurs(Piece $piece, Surface $mur): array
    {
        $arete = $mur->getAreteSol();
        $contour = $mur->getContour();
        $defaut = $piece->getHauteurSousPlafond() ?? 2500;
        if (null === $arete) {
            return [$defaut, $defaut];
        }
        $hd = GenerateurMurs::idHaut($arete['debut']);
        $hf = GenerateurMurs::idHaut($arete['fin']);

        return [
            $contour->a($hd) ? $contour->point($hd)->y : $defaut,
            $contour->a($hf) ? $contour->point($hf)->y : $defaut,
        ];
    }

    /**
     * Met à jour la largeur d'un mur (plan et contrainte de distance) en gardant les autres contraintes.
     *
     * @return array{generee: SurfaceGeneree, forme_conservee: bool}
     */
    private function largeurMiseAJour(Surface $mur, Point2D $debut, Point2D $fin): array
    {
        $arete = $mur->getAreteSol();
        if (null === $arete) {
            throw new ErreurGeometrique('constraint_malformed', ['vertex' => $mur->getNom()]);
        }
        $largeur = $debut->distanceA($fin);
        $formeConservee = false;
        $contraintes = [];
        foreach ($mur->getContraintesSommets() as $c) {
            if (TypeContrainte::Fixe === $c->type && $c->id !== $arete['fin']) {
                $formeConservee = true;
            }
            $contraintes[] = TypeContrainte::Distance === $c->type && $c->id === $arete['debut'] && 'u' === $c->direction && $c->depuis === $arete['fin']
                ? ContrainteSommet::distance($c->id, $arete['fin'], 'u', $largeur)
                : $c;
        }
        $plan = new Plan(
            new Point3D($fin->x, $fin->y, 0),
            new Point3D($debut->x - $fin->x, $debut->y - $fin->y, 0),
            new Point3D(0, 0, 1000),
        );

        return [
            'generee' => new SurfaceGeneree($mur->getNom(), $plan, (new ConstructeurSurface())->construire($contraintes), $contraintes, $arete),
            'forme_conservee' => $formeConservee,
        ];
    }

    /**
     * @return list<array{Ouverture, int, bool}>
     */
    private function ouverturesDe(Surface $mur, int $decalage): array
    {
        $liste = [];
        foreach ($mur->getOuvertures() as $o) {
            $liste[] = [$o, $decalage, false];
        }

        return $liste;
    }

    private static function decaler(Polygone $forme, int $decalage): Polygone
    {
        return new Polygone(array_map(static fn (Point2D $p): Point2D => new Point2D($p->x + $decalage, $p->y), $forme->points));
    }

    /**
     * @return list<array{u: int, v: int}>|null
     */
    private function polygoneDecale(Ouverture $ouverture, int $decalage): ?array
    {
        $polygone = $ouverture->getPolygone();
        if (null === $polygone || [] === $polygone) {
            return $polygone;
        }

        return array_map(static fn (array $p): array => ['u' => $p['u'] + $decalage, 'v' => $p['v']], $polygone);
    }
}
