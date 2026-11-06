<?php

declare(strict_types=1);

namespace App\Import\Csv;

use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Import\ContourImporte;
use App\Import\Dxf\DxfReader;
use App\Import\FormatCsv;
use App\Import\PlanImporte;
use App\Import\RapportPlan;
use App\Import\SaisieCotesAngles;
use App\Import\UniteImport;

/**
 * Relevé d'un télémètre laser exporté en CSV : colonnes `x;y` (points) ou `cote;angle` (côtés et angles intérieurs).
 * Séparateur `;` ou `,` détecté, décimale `.` ou `,` (avec `;`), en-tête et lignes `#` optionnels. Une seule ligne
 * invalide rejette tout le fichier (avec les numéros de ligne) : rien n'est importé partiellement.
 */
final class ReleveCsvReader
{
    /**
     * @throws ErreurGeometrique
     */
    public function lireFichier(string $chemin, UniteImport $unite, ?FormatCsv $format = null): PlanImporte
    {
        $taille = @filesize($chemin);
        if (false === $taille) {
            throw new ErreurGeometrique('import_file_unreadable');
        }
        if ($taille > DxfReader::TAILLE_MAX) {
            throw new ErreurGeometrique('import_file_too_large', ['size' => round($taille / 1048576, 1), 'max' => DxfReader::TAILLE_MAX / 1048576]);
        }
        $contenu = @file_get_contents($chemin);
        if (false === $contenu) {
            throw new ErreurGeometrique('import_file_unreadable');
        }

        return $this->lire($contenu, $unite, $format);
    }

    /**
     * @throws ErreurGeometrique
     */
    public function lire(string $contenu, UniteImport $unite, ?FormatCsv $format = null): PlanImporte
    {
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            $contenu = substr($contenu, 3);
        }
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $lignes = preg_split('/\r\n|\r|\n/', $contenu) ?: [];
        $separateur = $this->separateur($lignes);
        $facteur = $unite->enMillimetres();

        $donnees = [];
        $invalides = [];
        $entete = null;
        $premiere = true;
        foreach ($lignes as $i => $ligne) {
            $numero = $i + 1;
            $ligne = trim($ligne);
            if ('' === $ligne || str_starts_with($ligne, '#')) {
                continue;
            }
            $champs = array_map(trim(...), explode($separateur, $ligne));
            if ($premiere) {
                $premiere = false;
                if (!$this->nombre($champs[0], $separateur) && preg_match('/[a-zA-Z]/', $champs[0])) {
                    $entete = array_map(static fn (string $c): string => strtolower($c), $champs);
                    continue;
                }
            }
            $a = $this->nombre($champs[0], $separateur);
            $b = isset($champs[1]) ? $this->nombre($champs[1], $separateur) : null;
            if (null === $a || null === $b) {
                $invalides[] = $numero;
                continue;
            }
            $donnees[] = [$a, $b, $numero];
        }
        if ([] !== $invalides) {
            throw new ErreurGeometrique('import_csv_invalid_lines', ['lines' => implode(', ', \array_slice($invalides, 0, 20)).(\count($invalides) > 20 ? ', …' : '')]);
        }
        if ([] === $donnees) {
            throw new ErreurGeometrique('import_csv_empty');
        }
        $format ??= $this->detecterFormat($entete);

        $avertissements = [];
        if (FormatCsv::CotesAngles === $format) {
            foreach ($donnees as [$cote, $angle, $numero]) {
                if ($cote <= 0.0 || $angle < 1.0 || $angle > 359.0) {
                    $invalides[] = $numero;
                }
            }
            if ([] !== $invalides) {
                throw new ErreurGeometrique('import_csv_invalid_lines', ['lines' => implode(', ', $invalides)]);
            }
            $resultat = (new SaisieCotesAngles())->tracer(
                array_map(static fn (array $d): float => $d[0] * $facteur, $donnees),
                array_map(static fn (array $d): float => $d[1], $donnees),
            );
            if (null === $resultat->contour) {
                throw new ErreurGeometrique('import_closure_gap', ['gap' => round($resultat->ecart, 1), 'threshold' => SaisieCotesAngles::SEUIL_FERMETURE_MM]);
            }
            $points = $resultat->contour;
            if (null !== $resultat->avertissement) {
                $avertissements[] = $resultat->avertissement;
            }
        } else {
            $points = [];
            foreach ($donnees as [$x, $y]) {
                $p = new Point2D((int) round($x * $facteur), (int) round($y * $facteur));
                if ([] === $points || !$points[\count($points) - 1]->egal($p)) {
                    $points[] = $p;
                }
            }
            while (\count($points) > 1 && $points[0]->egal($points[\count($points) - 1])) {
                array_pop($points);
            }
            if (\count($points) < 3) {
                throw new ErreurGeometrique('polygon_not_simple');
            }
        }

        $rejet = (new Polygone($points))->estSimple() ? null : new Avertissement('polygon_not_simple');

        return new PlanImporte(
            [new ContourImporte($points, 'CSV', true, $rejet, 0, 'csv')],
            [],
            [],
            new RapportPlan([], $avertissements),
            ['CSV'],
            $unite,
        );
    }

    /**
     * @param list<string>|null $entete
     */
    private function detecterFormat(?array $entete): FormatCsv
    {
        if (null !== $entete) {
            $texte = implode(' ', $entete);
            if (str_contains($texte, 'angle') || str_contains($texte, 'cote') || str_contains($texte, 'côté') || str_contains($texte, 'side') || str_contains($texte, 'length')) {
                return FormatCsv::CotesAngles;
            }
        }

        return FormatCsv::Points;
    }

    /**
     * @param list<string> $lignes
     *
     * @return non-empty-string
     */
    private function separateur(array $lignes): string
    {
        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);
            if ('' === $ligne || str_starts_with($ligne, '#')) {
                continue;
            }

            return str_contains($ligne, ';') ? ';' : ',';
        }

        return ';';
    }

    private function nombre(string $champ, string $separateur): ?float
    {
        if (';' === $separateur) {
            $champ = str_replace(',', '.', $champ);
        }

        return 1 === preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $champ) ? (float) $champ : null;
    }
}
