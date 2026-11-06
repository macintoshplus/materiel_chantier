<?php

declare(strict_types=1);

namespace App\Apercu;

use App\Geometrie\BoiteEnglobante;
use App\Geometrie\Point2D;
use App\Import\PlanImporte;
use App\Import\RoleImport;

/**
 * Aperçus SVG de l'assistant d'import : contours détectés (retenu, rejeté, autres), contour final décalé, repères de
 * blocs et tracé « côtés et angles ». Une unité SVG = 1 mm, `y` inversé comme `ApercuPiece`.
 *
 * @phpstan-type VueContour array{index: int|null, classe: string, titre: string, ferme: bool, points: string, aretes: list<array{x: int, y: int, texte: string}>}
 * @phpstan-type VueBloc array{x: int, y: int, nom: string, role: string}
 * @phpstan-type VueImport array{vide: bool, viewBox: string, contours: list<VueContour>, blocs: list<VueBloc>, police: int, trait: int}
 */
final class ApercuImport
{
    /**
     * @param array<int, RoleImport> $roles   rôle de chaque bloc (index dans `PlanImporte::blocs`) ; vide : blocs non montrés
     * @param list<Point2D>|null     $final   contour final (après décalage) tracé par-dessus, en mm du plan
     *
     * @return VueImport
     */
    public function depuisPlan(PlanImporte $plan, ?int $choisi = null, ?array $final = null, array $roles = []): array
    {
        $contours = [];
        $tous = [];
        foreach ($plan->contours as $i => $c) {
            $classe = null !== $c->rejet ? 'rejete' : ($i === $choisi ? 'choisi' : 'autre');
            $contours[] = ['index' => $i, 'classe' => $classe, 'titre' => $c->calque, 'ferme' => $c->ferme, 'points' => $c->points, 'etiquettes' => 'choisi' === $classe];
            array_push($tous, ...$c->points);
        }
        if (null !== $final) {
            $contours[] = ['index' => null, 'classe' => 'final', 'titre' => '', 'ferme' => true, 'points' => $final, 'etiquettes' => true];
            array_push($tous, ...$final);
        }
        $blocs = [];
        foreach ($roles as $index => $role) {
            $b = $plan->blocs[$index] ?? null;
            if (null === $b) {
                continue;
            }
            $blocs[] = ['x' => $b->centre->x, 'y' => $b->centre->y, 'nom' => $b->nom, 'role' => $role->value];
            $tous[] = $b->centre;
        }

        return $this->modele($contours, $blocs, $tous);
    }

    /**
     * Tracé de côtés et d'angles : polyligne saisie (ouverte tant que le contour ne se referme pas).
     *
     * @param list<Point2D> $trace
     *
     * @return VueImport
     */
    public function depuisTrace(array $trace, bool $ferme): array
    {
        return $this->modele([['index' => null, 'classe' => $ferme ? 'final' : 'trace', 'titre' => '', 'ferme' => $ferme, 'points' => $trace, 'etiquettes' => true]], [], $trace);
    }

    /**
     * @param list<array{index: int|null, classe: string, titre: string, ferme: bool, points: list<Point2D>, etiquettes: bool}> $contours
     * @param list<VueBloc>                                                                                                       $blocs
     * @param list<Point2D>                                                                                                       $tous
     *
     * @return VueImport
     */
    private function modele(array $contours, array $blocs, array $tous): array
    {
        if ([] === $tous) {
            return ['vide' => true, 'viewBox' => '0 0 1000 1000', 'contours' => [], 'blocs' => [], 'police' => 40, 'trait' => 8];
        }
        $boite = BoiteEnglobante::depuisPoints($tous);
        $taille = max($boite->largeur(), $boite->hauteur(), 1000);
        $police = max(40, intdiv($taille, 30));
        $marge = intdiv($taille, 10) + 3 * $police;
        $vues = [];
        foreach ($contours as $c) {
            $aretes = [];
            if ($c['etiquettes']) {
                $n = \count($c['points']);
                for ($i = 0; $i < ($c['ferme'] ? $n : $n - 1); ++$i) {
                    $a = $c['points'][$i];
                    $b = $c['points'][($i + 1) % $n];
                    $aretes[] = ['x' => intdiv($a->x + $b->x, 2), 'y' => -intdiv($a->y + $b->y, 2) - $police, 'texte' => (string) $a->distanceA($b)];
                }
            }
            $vues[] = [
                'index' => $c['index'],
                'classe' => $c['classe'],
                'titre' => $c['titre'],
                'ferme' => $c['ferme'],
                'points' => implode(' ', array_map(static fn (Point2D $p): string => $p->x.','.(-$p->y), $c['points'])),
                'aretes' => $aretes,
            ];
        }

        return [
            'vide' => false,
            'viewBox' => \sprintf('%d %d %d %d', $boite->minX - $marge, -$boite->maxY - $marge, $boite->largeur() + 2 * $marge, $boite->hauteur() + 2 * $marge),
            'contours' => $vues,
            'blocs' => $blocs,
            'police' => $police,
            'trait' => max(6, intdiv($taille, 250)),
        ];
    }
}
