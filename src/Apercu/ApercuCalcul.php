<?php

declare(strict_types=1);

namespace App\Apercu;

use App\Calcul\Pipeline\ResultatPipeline;
use App\Entity\Surface;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;
use App\Service\CalculSysteme;

/**
 * Résultat du calcul du système actif d'une surface, tel qu'il s'affiche sur sa page : ossature (entraxe retenu) et
 * plaques du dernier parement (calepinage) dessinées dans le repère de l'aperçu (1 unité = 1 mm, `y = -v`), plus les
 * avertissements et erreurs du calcul. Recalculé à chaque affichage : après un enregistrement, la page montre donc
 * le résultat à jour sans action de l'utilisateur.
 *
 * @phpstan-type VueCalcul array{montants: list<array{x1: int, y1: int, x2: int, y2: int}>, plaques: list<array{x: int, y: int, largeur: int, hauteur: int}>, avertissements: list<Avertissement>, erreurs: list<Avertissement>}
 */
final class ApercuCalcul
{
    public function __construct(private readonly CalculSysteme $calcul)
    {
    }

    /**
     * @return VueCalcul|null `null` quand la surface n'a pas de système actif
     */
    public function pourSurface(Surface $surface): ?array
    {
        $systeme = null;
        foreach ($surface->getSystemes() as $candidat) {
            if ($candidat->isActif()) {
                $systeme = $candidat;
            }
        }
        if (null === $systeme) {
            return null;
        }
        $resultat = $this->calcul->calculer($systeme)[$surface->getNom()] ?? null;
        if (!$resultat instanceof ResultatPipeline) {
            return null;
        }
        if (!$resultat->estValide()) {
            return ['montants' => [], 'plaques' => [], 'avertissements' => $resultat->avertissements, 'erreurs' => $resultat->erreurs];
        }

        $boite = $surface->getContour()->polygone()->boiteEnglobante();
        $contexte = $resultat->contexte;

        $montants = [];
        $entraxe = $contexte->entraxeRetenu;
        if (null !== $entraxe && $entraxe > 0) {
            if (Direction::Verticale === $contexte->directionOssature) {
                for ($x = $boite->minX; $x < $boite->maxX; $x += $entraxe) {
                    $montants[] = ['x1' => $x, 'y1' => -$boite->maxY, 'x2' => $x, 'y2' => -$boite->minY];
                }
                $montants[] = ['x1' => $boite->maxX, 'y1' => -$boite->maxY, 'x2' => $boite->maxX, 'y2' => -$boite->minY];
            } else {
                for ($v = $boite->minY; $v < $boite->maxY; $v += $entraxe) {
                    $montants[] = ['x1' => $boite->minX, 'y1' => -$v, 'x2' => $boite->maxX, 'y2' => -$v];
                }
                $montants[] = ['x1' => $boite->minX, 'y1' => -$boite->maxY, 'x2' => $boite->maxX, 'y2' => -$boite->maxY];
            }
        }

        $plaques = [];
        foreach ($contexte->calepinagePrecedent->plaques ?? [] as $plaque) {
            $plaques[] = [
                'x' => $boite->minX + $plaque->x,
                'y' => -($boite->minY + $plaque->y + $plaque->hauteur),
                'largeur' => $plaque->largeur,
                'hauteur' => $plaque->hauteur,
            ];
        }

        return ['montants' => $montants, 'plaques' => $plaques, 'avertissements' => $resultat->avertissements, 'erreurs' => []];
    }
}
