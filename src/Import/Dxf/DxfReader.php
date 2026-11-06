<?php

declare(strict_types=1);

namespace App\Import\Dxf;

use App\Geometrie\ErreurGeometrique;

/**
 * Lecteur minimal de DXF ASCII (paires code de groupe / valeur). Fichier non fiable : taille et nombre d'entités
 * limités, DXF binaire refusé, aucune interprétation de contenu actif. Les types d'entité qui ne servent pas à
 * l'import sont comptés mais leurs groupes ne sont pas conservés.
 */
final class DxfReader
{
    public const TAILLE_MAX = 10 * 1024 * 1024;
    public const ENTITES_MAX = 100000;

    /** Types dont les groupes sont conservés. */
    private const CONSERVES = ['LWPOLYLINE', 'POLYLINE', 'VERTEX', 'LINE', 'ARC', 'CIRCLE', 'INSERT', 'ATTRIB', 'TEXT', 'MTEXT', 'BLOCK', 'ELLIPSE', 'SPLINE'];

    public function __construct(private readonly int $tailleMax = self::TAILLE_MAX, private readonly int $entitesMax = self::ENTITES_MAX)
    {
    }

    /**
     * @throws ErreurGeometrique
     */
    public function lireFichier(string $chemin): DxfDocument
    {
        $taille = @filesize($chemin);
        if (false === $taille) {
            throw new ErreurGeometrique('import_file_unreadable');
        }
        $this->verifierTaille($taille);
        $contenu = @file_get_contents($chemin);
        if (false === $contenu) {
            throw new ErreurGeometrique('import_file_unreadable');
        }

        return $this->lireTexte($contenu);
    }

    /**
     * @throws ErreurGeometrique
     */
    public function lireTexte(string $contenu): DxfDocument
    {
        $this->verifierTaille(\strlen($contenu));
        if (str_starts_with($contenu, 'AutoCAD Binary DXF') || str_contains(substr($contenu, 0, 4096), "\0")) {
            throw new ErreurGeometrique('import_dxf_binary');
        }
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            $contenu = substr($contenu, 3);
        }
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $lignes = preg_split('/\r\n|\n|\r/', $contenu);
        if (false === $lignes) {
            throw new ErreurGeometrique('import_file_unreadable');
        }
        if ('' === end($lignes)) {
            array_pop($lignes);
        }
        $nbLignes = \count($lignes);
        if (0 === $nbLignes || 1 === $nbLignes % 2) {
            throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $nbLignes]);
        }

        return $this->structurer($this->items($lignes, $nbLignes));
    }

    private function verifierTaille(int $octets): void
    {
        if ($octets > $this->tailleMax) {
            throw new ErreurGeometrique('import_file_too_large', ['size' => round($octets / 1048576, 1), 'max' => round($this->tailleMax / 1048576, 1)]);
        }
    }

    /**
     * Découpe en éléments : un élément commence à chaque groupe 0.
     *
     * @param list<string> $lignes
     *
     * @return list<array{string, list<array{int, string}>, int}> type, groupes, numéro de ligne
     */
    private function items(array $lignes, int $nbLignes): array
    {
        $items = [];
        $courant = null;
        for ($i = 0; $i < $nbLignes; $i += 2) {
            $code = trim($lignes[$i]);
            if (1 !== preg_match('/^-?\d{1,4}$/', $code)) {
                throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $i + 1]);
            }
            $code = (int) $code;
            $valeur = trim($lignes[$i + 1]);
            if (0 === $code) {
                if (null !== $courant) {
                    $items[] = $courant;
                }
                if (\count($items) > $this->entitesMax * 2) {
                    throw new ErreurGeometrique('import_dxf_too_many_entities', ['max' => $this->entitesMax]);
                }
                $courant = [$valeur, [], $i + 1];
            } elseif (null === $courant) {
                if (999 !== $code) {
                    throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $i + 1]);
                }
            } elseif ('SECTION' === $courant[0] || \in_array($courant[0], self::CONSERVES, true)) {
                $courant[1][] = [$code, $valeur];
            }
        }
        if (null !== $courant) {
            $items[] = $courant;
        }

        return $items;
    }

    /**
     * @param list<array{string, list<array{int, string}>, int}> $items
     */
    private function structurer(array $items): DxfDocument
    {
        $variables = [];
        $blocs = [];
        $entites = [];
        $section = null;
        $bloc = null;
        $parent = null;
        $fini = false;
        $nombre = 0;
        $derniere = 1;
        foreach ($items as [$type, $groupes, $ligne]) {
            $derniere = $ligne;
            if ('EOF' === $type) {
                $fini = true;
                break;
            }
            if ('SECTION' === $type) {
                if (null !== $section) {
                    throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $ligne]);
                }
                $section = null;
                foreach ($groupes as [$code, $valeur]) {
                    if (2 === $code) {
                        $section = $valeur;
                        break;
                    }
                }
                if (null === $section) {
                    throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $ligne]);
                }
                if ('HEADER' === $section) {
                    $variables = $this->entete($groupes);
                }
                continue;
            }
            if ('ENDSEC' === $type) {
                if (null === $section || null !== $bloc) {
                    throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $ligne]);
                }
                $section = null;
                $parent = null;
                continue;
            }
            if (null === $section) {
                throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $ligne]);
            }
            if ('ENTITIES' !== $section && 'BLOCKS' !== $section) {
                continue;
            }
            if ('BLOCK' === $type) {
                $entiteBloc = new DxfEntite('BLOCK', $groupes);
                $nom = $entiteBloc->valeur(2) ?? '';
                $bloc = new DxfBloc($nom, $entiteBloc->nombre(10), $entiteBloc->nombre(20));
                $blocs[$nom] = $bloc;
                $parent = null;
                continue;
            }
            if ('ENDBLK' === $type) {
                $bloc = null;
                $parent = null;
                continue;
            }
            if ('SEQEND' === $type) {
                $parent = null;
                continue;
            }
            if (('VERTEX' === $type || 'ATTRIB' === $type) && null !== $parent) {
                $parent->ajouterEnfant(new DxfEntite($type, $groupes));
                $this->compter($nombre);
                continue;
            }
            if ('BLOCKS' === $section && null === $bloc) {
                throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $ligne]);
            }
            $entite = new DxfEntite($type, $groupes);
            $this->compter($nombre);
            $parent = 'POLYLINE' === $type || ('INSERT' === $type && 1 === $entite->entier(66)) ? $entite : null;
            if (null !== $bloc) {
                $bloc->ajouter($entite);
            } else {
                $entites[] = $entite;
            }
        }
        if (!$fini || null !== $section) {
            throw new ErreurGeometrique('import_dxf_corrupt', ['line' => $derniere]);
        }

        return new DxfDocument($variables, $blocs, $entites, $nombre);
    }

    private function compter(int &$nombre): void
    {
        if (++$nombre > $this->entitesMax) {
            throw new ErreurGeometrique('import_dxf_too_many_entities', ['max' => $this->entitesMax]);
        }
    }

    /**
     * @param list<array{int, string}> $groupes
     *
     * @return array<string, string>
     */
    private function entete(array $groupes): array
    {
        $variables = [];
        $nom = null;
        foreach ($groupes as [$code, $valeur]) {
            if (9 === $code) {
                $nom = $valeur;
            } elseif (null !== $nom && !isset($variables[$nom])) {
                $variables[$nom] = $valeur;
            }
        }

        return $variables;
    }
}
