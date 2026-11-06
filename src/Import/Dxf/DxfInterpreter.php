<?php

declare(strict_types=1);

namespace App\Import\Dxf;

use App\Geometrie\Avertissement;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Import\BlocImporte;
use App\Import\ContourImporte;
use App\Import\PlanImporte;
use App\Import\RapportPlan;
use App\Import\TexteImporte;
use App\Import\UniteImport;

/**
 * Convertit un `DxfDocument` en `PlanImporte` (mm) : polylignes (LWPOLYLINE, POLYLINE/VERTEX 2D), LINE chaînées avec
 * tolérance, INSERT résolus, TEXT/MTEXT. Les courbes (ARC, bulges) ne sont jamais approchées : un contour qui en
 * contient un est rejeté, une courbe hors contour est ignorée avec un avertissement. Le reste est compté « ignoré ».
 *
 * @phpstan-type Pt array{float, float}
 * @phpstan-type Seg array{calque: string, a: Pt, b: Pt, courbe: bool}
 * @phpstan-type Poly array{calque: string, points: list<Pt>, ferme: bool, courbe: bool}
 * @phpstan-type Brut array{polylignes: list<Poly>, segments: list<Seg>, inserts: list<DxfEntite>, textes: list<array{texte: string, calque: string, point: Pt}>, ignores: array<string, int>, courbes: array<string, int>}
 */
final class DxfInterpreter
{
    /** Écart maximal (mm) entre deux extrémités de lignes pour les considérer jointes. */
    public const TOLERANCE_CHAINAGE_MM = 5.0;

    public function __construct(private readonly float $tolerance = self::TOLERANCE_CHAINAGE_MM)
    {
    }

    /**
     * Unité proposée : `$INSUNITS` si renseigné, sinon estimée d'après l'étendue du dessin (deuxième valeur vraie).
     *
     * @return array{UniteImport, bool}
     */
    public function proposerUnite(DxfDocument $document): array
    {
        $declaree = UniteImport::depuisInsUnits((int) ($document->variable('$INSUNITS') ?? 0));
        if (null !== $declaree) {
            return [$declaree, false];
        }
        $brut = $this->extraire($document);
        $xs = [];
        $ys = [];
        foreach ($brut['polylignes'] as $p) {
            foreach ($p['points'] as [$x, $y]) {
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        foreach ($brut['segments'] as $s) {
            foreach ([$s['a'], $s['b']] as [$x, $y]) {
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        $etendue = [] === $xs || [] === $ys ? 0.0 : max(max($xs) - min($xs), max($ys) - min($ys));
        $unite = match (true) {
            $etendue >= 1000.0 => UniteImport::Millimetre,
            $etendue >= 100.0 => UniteImport::Centimetre,
            default => UniteImport::Metre,
        };

        return [$unite, true];
    }

    public function interpreter(DxfDocument $document, UniteImport $unite, bool $uniteDevinee = false): PlanImporte
    {
        $f = $unite->enMillimetres();
        $brut = $this->extraire($document);
        $avertissements = [];
        if ($uniteDevinee) {
            $avertissements[] = new Avertissement('import_unit_guessed', ['unit' => $unite->value]);
        }
        $calques = [];
        $contours = [];

        /** @var array<string, list<array{a: Pt, b: Pt, courbe: bool}>> $parCalque */
        $parCalque = [];
        foreach ($brut['polylignes'] as $poly) {
            $calques[$poly['calque']] = true;
            $points = array_map(static fn (array $p): array => [$p[0] * $f, $p[1] * $f], $poly['points']);
            if ($poly['ferme']) {
                $contours[] = $this->contourDePolyligne($points, $poly['calque'], $poly['courbe']);
                continue;
            }
            for ($i = 0; $i + 1 < \count($points); ++$i) {
                $parCalque[$poly['calque']][] = ['a' => $points[$i], 'b' => $points[$i + 1], 'courbe' => $poly['courbe']];
            }
        }
        foreach ($brut['segments'] as $seg) {
            $calques[$seg['calque']] = true;
            $parCalque[$seg['calque']][] = [
                'a' => [$seg['a'][0] * $f, $seg['a'][1] * $f],
                'b' => [$seg['b'][0] * $f, $seg['b'][1] * $f],
                'courbe' => $seg['courbe'],
            ];
        }
        foreach ($parCalque as $calque => $segments) {
            [$fermes, $ouverts] = $this->chainer($segments);
            foreach ($fermes as $chaine) {
                $contours[] = $this->contourDeChaine($chaine, (string) $calque);
                if ($chaine['ecart'] > 0.5) {
                    $avertissements[] = new Avertissement('import_chain_gap', ['layer' => (string) $calque, 'gap' => round($chaine['ecart'], 1)]);
                }
            }
            foreach ($ouverts as $ouvert) {
                $avertissements[] = $ouvert['courbes']
                    ? new Avertissement('import_curve_ignored', ['type' => 'ARC', 'layer' => (string) $calque, 'count' => $ouvert['nombre']])
                    : new Avertissement('import_open_chain', ['layer' => (string) $calque, 'count' => $ouvert['nombre']]);
            }
        }
        foreach ($brut['courbes'] as $cle => $nombre) {
            [$type, $calque] = explode('|', $cle, 2);
            $calques[$calque] = true;
            $avertissements[] = new Avertissement('import_curve_ignored', ['type' => $type, 'layer' => $calque, 'count' => $nombre]);
        }

        $blocs = [];
        foreach ($brut['inserts'] as $insert) {
            $calques[$insert->calque()] = true;
            $blocs[] = $this->bloc($insert, $document, $f);
        }
        $textes = [];
        foreach ($brut['textes'] as $t) {
            $calques[$t['calque']] = true;
            $textes[] = new TexteImporte($t['texte'], $t['calque'], new Point2D((int) round($t['point'][0] * $f), (int) round($t['point'][1] * $f)));
        }

        return new PlanImporte(
            $contours,
            $blocs,
            $textes,
            new RapportPlan($brut['ignores'], $avertissements),
            array_map(strval(...), array_keys($calques)),
            $unite,
            $uniteDevinee,
        );
    }

    /**
     * @return Brut
     */
    private function extraire(DxfDocument $document): array
    {
        $brut = ['polylignes' => [], 'segments' => [], 'inserts' => [], 'textes' => [], 'ignores' => [], 'courbes' => []];
        foreach ($document->entites as $e) {
            switch ($e->type) {
                case 'LWPOLYLINE':
                    $brut['polylignes'][] = $this->lwpolyligne($e);
                    break;
                case 'POLYLINE':
                    $poly = $this->polyligne($e);
                    if (null === $poly) {
                        $this->ignorer($brut, 'POLYLINE (3D)');
                    } else {
                        $brut['polylignes'][] = $poly;
                    }
                    break;
                case 'LINE':
                    $brut['segments'][] = [
                        'calque' => $e->calque(),
                        'a' => [$e->nombre(10), $e->nombre(20)],
                        'b' => [$e->nombre(11), $e->nombre(21)],
                        'courbe' => false,
                    ];
                    break;
                case 'ARC':
                    $r = $e->nombre(40);
                    $a1 = deg2rad($e->nombre(50));
                    $a2 = deg2rad($e->nombre(51));
                    $brut['segments'][] = [
                        'calque' => $e->calque(),
                        'a' => [$e->nombre(10) + $r * cos($a1), $e->nombre(20) + $r * sin($a1)],
                        'b' => [$e->nombre(10) + $r * cos($a2), $e->nombre(20) + $r * sin($a2)],
                        'courbe' => true,
                    ];
                    break;
                case 'CIRCLE':
                case 'ELLIPSE':
                case 'SPLINE':
                    $cle = $e->type.'|'.$e->calque();
                    $brut['courbes'][$cle] = ($brut['courbes'][$cle] ?? 0) + 1;
                    break;
                case 'INSERT':
                    $brut['inserts'][] = $e;
                    break;
                case 'TEXT':
                    $brut['textes'][] = ['texte' => self::nettoyerTexte($e->valeur(1) ?? ''), 'calque' => $e->calque(), 'point' => [$e->nombre(10), $e->nombre(20)]];
                    break;
                case 'MTEXT':
                    $texte = implode('', $e->valeurs(3)).($e->valeur(1) ?? '');
                    $brut['textes'][] = ['texte' => self::nettoyerTexte($texte), 'calque' => $e->calque(), 'point' => [$e->nombre(10), $e->nombre(20)]];
                    break;
                default:
                    $this->ignorer($brut, $e->type);
            }
        }

        return $brut;
    }

    /**
     * @param Brut $brut
     */
    private function ignorer(array &$brut, string $type): void
    {
        $brut['ignores'][$type] = ($brut['ignores'][$type] ?? 0) + 1;
    }

    /**
     * @return Poly
     */
    private function lwpolyligne(DxfEntite $e): array
    {
        $points = [];
        $courbe = false;
        $x = null;
        foreach ($e->groupes as [$code, $valeur]) {
            if (10 === $code) {
                $x = (float) $valeur;
            } elseif (20 === $code && null !== $x) {
                $points[] = [$x, (float) $valeur];
                $x = null;
            } elseif (42 === $code && abs((float) $valeur) > 1e-9) {
                $courbe = true;
            }
        }

        return ['calque' => $e->calque(), 'points' => $points, 'ferme' => 1 === ($e->entier(70) & 1), 'courbe' => $courbe];
    }

    /**
     * @return Poly|null null pour une polyligne 3D, un maillage ou un polyface (hors périmètre)
     */
    private function polyligne(DxfEntite $e): ?array
    {
        if (0 !== ($e->entier(70) & (8 | 16 | 64))) {
            return null;
        }
        $points = [];
        $courbe = false;
        foreach ($e->enfants() as $vertex) {
            if ('VERTEX' !== $vertex->type || 0 !== ($vertex->entier(70) & (16 | 128))) {
                continue;
            }
            $points[] = [$vertex->nombre(10), $vertex->nombre(20)];
            if (abs($vertex->nombre(42)) > 1e-9) {
                $courbe = true;
            }
        }

        return ['calque' => $e->calque(), 'points' => $points, 'ferme' => 1 === ($e->entier(70) & 1), 'courbe' => $courbe];
    }

    /**
     * @param list<Pt> $points mm
     */
    private function contourDePolyligne(array $points, string $calque, bool $courbe): ContourImporte
    {
        $entiers = $this->entiers($points);
        if ($courbe) {
            return new ContourImporte($entiers, $calque, true, new Avertissement('import_curved_walls', ['layer' => $calque]));
        }
        if (!(new Polygone($entiers))->estSimple()) {
            return new ContourImporte($entiers, $calque, true, new Avertissement('polygon_not_simple'));
        }

        return new ContourImporte($entiers, $calque);
    }

    /**
     * @param array{points: list<Pt>, ecart: float, courbe: bool} $chaine
     */
    private function contourDeChaine(array $chaine, string $calque): ContourImporte
    {
        if ($chaine['courbe']) {
            return new ContourImporte($this->entiers($chaine['points']), $calque, true, new Avertissement('import_curved_walls', ['layer' => $calque]), (int) ceil($chaine['ecart']), 'lignes');
        }
        $points = $this->entiers($this->sansPointsAlignes($chaine['points']));
        $rejet = (new Polygone($points))->estSimple() ? null : new Avertissement('polygon_not_simple');

        return new ContourImporte($points, $calque, true, $rejet, (int) ceil($chaine['ecart']), 'lignes');
    }

    /**
     * Arrondit au mm, retire les doublons consécutifs et le point de fermeture répété.
     *
     * @param list<Pt> $points
     *
     * @return list<Point2D>
     */
    private function entiers(array $points): array
    {
        $res = [];
        foreach ($points as [$x, $y]) {
            $p = new Point2D((int) round($x), (int) round($y));
            if ([] === $res || !$res[\count($res) - 1]->egal($p)) {
                $res[] = $p;
            }
        }
        while (\count($res) > 1 && $res[0]->egal($res[\count($res) - 1])) {
            array_pop($res);
        }

        return $res;
    }

    /**
     * @param list<Pt> $points
     *
     * @return list<Pt>
     */
    private function sansPointsAlignes(array $points): array
    {
        $n = \count($points);
        $res = [];
        for ($i = 0; $i < $n; ++$i) {
            $p = $points[$i];
            $a = $points[($i + $n - 1) % $n];
            $b = $points[($i + 1) % $n];
            $d = hypot($b[0] - $a[0], $b[1] - $a[1]);
            $ecart = $d < 1e-9 ? 0.0 : abs(($b[0] - $a[0]) * ($a[1] - $p[1]) - ($a[0] - $p[0]) * ($b[1] - $a[1])) / $d;
            if ($ecart > 1.0 || $n <= 3) {
                $res[] = $p;
            }
        }

        return $res;
    }

    /**
     * Chaîne des segments (mm) : extrémités à moins de `$tolerance` considérées jointes.
     *
     * @param list<array{a: Pt, b: Pt, courbe: bool}> $segments
     *
     * @return array{list<array{points: list<Pt>, ecart: float, courbe: bool}>, list<array{nombre: int, courbes: bool}>}
     */
    private function chainer(array $segments): array
    {
        $restants = [];
        foreach ($segments as $s) {
            if ($this->distance($s['a'], $s['b']) < 1.0) {
                continue;
            }
            foreach ($restants as $autre) {
                if (($this->distance($autre['a'], $s['a']) < 1.0 && $this->distance($autre['b'], $s['b']) < 1.0)
                    || ($this->distance($autre['a'], $s['b']) < 1.0 && $this->distance($autre['b'], $s['a']) < 1.0)) {
                    continue 2;
                }
            }
            $restants[] = $s;
        }
        $fermes = [];
        $ouverts = [];
        while ([] !== $restants) {
            $premier = array_shift($restants);
            $chaine = [$premier];
            $ecart = 0.0;
            $ferme = false;
            while (true) {
                $debut = $chaine[0]['a'];
                $fin = $chaine[\count($chaine) - 1]['b'];
                if (\count($chaine) >= 3 && $this->distance($fin, $debut) <= $this->tolerance) {
                    $ferme = true;
                    break;
                }
                [$i, $retourne, $d] = $this->plusProche($restants, $fin);
                if (null !== $i) {
                    $s = $restants[$i];
                    array_splice($restants, $i, 1);
                    $chaine[] = $retourne ? ['a' => $s['b'], 'b' => $s['a'], 'courbe' => $s['courbe']] : $s;
                    $ecart = max($ecart, $d);
                    continue;
                }
                [$i, $retourne, $d] = $this->plusProche($restants, $debut, true);
                if (null !== $i) {
                    $s = $restants[$i];
                    array_splice($restants, $i, 1);
                    array_unshift($chaine, $retourne ? ['a' => $s['b'], 'b' => $s['a'], 'courbe' => $s['courbe']] : $s);
                    $ecart = max($ecart, $d);
                    continue;
                }
                break;
            }
            if ($ferme) {
                $points = [];
                $n = \count($chaine);
                for ($k = 0; $k < $n; ++$k) {
                    $b = $chaine[$k]['b'];
                    $a = $chaine[($k + 1) % $n]['a'];
                    $ecart = max($ecart, $this->distance($a, $b));
                    $points[] = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
                }
                $fermes[] = ['points' => $points, 'ecart' => $ecart, 'courbe' => [] !== array_filter($chaine, static fn (array $s): bool => $s['courbe'])];
            } else {
                $ouverts[] = ['nombre' => \count($chaine), 'courbes' => \count($chaine) === \count(array_filter($chaine, static fn (array $s): bool => $s['courbe']))];
            }
        }

        return [$fermes, $ouverts];
    }

    /**
     * Segment restant dont une extrémité est la plus proche de `$point` (et à moins de la tolérance).
     *
     * @param list<array{a: Pt, b: Pt, courbe: bool}> $restants
     * @param Pt                                      $point
     * @param bool                                    $versFin si vrai, c'est l'extrémité `b` qui doit toucher `$point` (prolongement avant le début)
     *
     * @return array{int|null, bool, float} index, segment à retourner, écart
     */
    private function plusProche(array $restants, array $point, bool $versFin = false): array
    {
        $meilleur = [null, false, INF];
        foreach ($restants as $i => $s) {
            $contact = $versFin ? $s['b'] : $s['a'];
            $autre = $versFin ? $s['a'] : $s['b'];
            $d1 = $this->distance($contact, $point);
            $d2 = $this->distance($autre, $point);
            if ($d1 <= $this->tolerance && $d1 < $meilleur[2]) {
                $meilleur = [$i, false, $d1];
            }
            if ($d2 <= $this->tolerance && $d2 < $meilleur[2]) {
                $meilleur = [$i, true, $d2];
            }
        }

        return $meilleur;
    }

    /**
     * @param Pt $a
     * @param Pt $b
     */
    private function distance(array $a, array $b): float
    {
        return hypot($a[0] - $b[0], $a[1] - $b[1]);
    }

    private function bloc(DxfEntite $insert, DxfDocument $document, float $f): BlocImporte
    {
        $nom = $insert->valeur(2) ?? '';
        $ix = $insert->nombre(10);
        $iy = $insert->nombre(20);
        $sx = $insert->nombre(41, 1.0);
        $sy = $insert->nombre(42, 1.0);
        $rot = $insert->nombre(50);
        $attributs = [];
        foreach ($insert->enfants() as $attrib) {
            $etiquette = strtoupper(trim($attrib->valeur(2) ?? ''));
            if ('ATTRIB' === $attrib->type && '' !== $etiquette) {
                $attributs[$etiquette] = trim(self::nettoyerTexte($attrib->valeur(1) ?? ''));
            }
        }
        $centre = new Point2D((int) round($ix * $f), (int) round($iy * $f));
        $largeur = null;
        $definition = $document->blocs[$nom] ?? null;
        $etendue = isset($document->blocs[$nom]) ? $this->etendueBloc($document->blocs[$nom]) : null;
        if (null !== $etendue && null !== $definition) {
            [$minX, $minY, $maxX, $maxY] = $etendue;
            $lx = (($minX + $maxX) / 2 - $definition->baseX) * $sx;
            $ly = (($minY + $maxY) / 2 - $definition->baseY) * $sy;
            $c = cos(deg2rad($rot));
            $s = sin(deg2rad($rot));
            $centre = new Point2D(
                (int) round(($ix + $lx * $c - $ly * $s) * $f),
                (int) round(($iy + $lx * $s + $ly * $c) * $f),
            );
            $largeur = (int) round(max(($maxX - $minX) * abs($sx), ($maxY - $minY) * abs($sy)) * $f);
        }

        return new BlocImporte(
            $nom,
            $insert->calque(),
            new Point2D((int) round($ix * $f), (int) round($iy * $f)),
            $centre,
            $rot,
            $sx,
            $sy,
            $largeur,
            $attributs,
        );
    }

    /**
     * Boîte englobante (unités du dessin) de la géométrie d'une définition de bloc.
     *
     * @return array{float, float, float, float}|null
     */
    private function etendueBloc(DxfBloc $bloc): ?array
    {
        $xs = [];
        $ys = [];
        foreach ($bloc->entites() as $e) {
            switch ($e->type) {
                case 'LINE':
                    array_push($xs, $e->nombre(10), $e->nombre(11));
                    array_push($ys, $e->nombre(20), $e->nombre(21));
                    break;
                case 'LWPOLYLINE':
                    foreach ($this->lwpolyligne($e)['points'] as [$x, $y]) {
                        $xs[] = $x;
                        $ys[] = $y;
                    }
                    break;
                case 'POLYLINE':
                    foreach ($e->enfants() as $v) {
                        $xs[] = $v->nombre(10);
                        $ys[] = $v->nombre(20);
                    }
                    break;
                case 'CIRCLE':
                    array_push($xs, $e->nombre(10) - $e->nombre(40), $e->nombre(10) + $e->nombre(40));
                    array_push($ys, $e->nombre(20) - $e->nombre(40), $e->nombre(20) + $e->nombre(40));
                    break;
                case 'ARC':
                    $r = $e->nombre(40);
                    $debut = $e->nombre(50);
                    $fin = $e->nombre(51);
                    $angles = [$debut, $fin];
                    foreach ([0.0, 90.0, 180.0, 270.0, 360.0] as $q) {
                        $apres = fmod($q - $debut + 720.0, 360.0);
                        $etendue = fmod($fin - $debut + 720.0, 360.0);
                        if ($apres <= $etendue) {
                            $angles[] = $q;
                        }
                    }
                    foreach ($angles as $angle) {
                        $xs[] = $e->nombre(10) + $r * cos(deg2rad($angle));
                        $ys[] = $e->nombre(20) + $r * sin(deg2rad($angle));
                    }
                    break;
            }
        }

        return [] === $xs || [] === $ys ? null : [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * Texte brut d'un TEXT/MTEXT : séquences de mise en forme retirées, `\U+XXXX` décodés.
     */
    private static function nettoyerTexte(string $texte): string
    {
        $texte = preg_replace_callback('/\\\\U\+([0-9A-Fa-f]{4})/', static fn (array $m): string => mb_chr((int) hexdec($m[1]), 'UTF-8'), $texte) ?? $texte;
        $texte = str_replace(['\\P', '\\~'], [' ', ' '], $texte);
        $texte = preg_replace('/\\\\[A-Za-z][^;\\\\]*;/', '', $texte) ?? $texte;
        $texte = str_replace(['{', '}'], '', $texte);

        return trim($texte);
    }
}
