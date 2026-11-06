<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Electricite\RegistreTypesElements;
use App\Calcul\Electricite\TypeElement;
use App\Entity\Equipement;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\FamilleEquipement;
use App\Enum\TypeSurface;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;
use App\Geometrie\Point2D;

/**
 * Contrôles non bloquants d'une installation électrique : minimum de prises et de points lumineux par pièce, charge
 * d'un circuit, position d'une boîte (hors de la surface, sur une ouverture, trop près d'une ouverture, sur un
 * montant d'ossature).
 */
final class ControleElectricite
{
    /** Prises et points lumineux minimaux par pièce (surchargeables dans `Projet.parametres['electricite']`). */
    public const int PRISES_MIN = 3;
    public const int POINTS_LUMINEUX_MIN = 1;

    /** Tension nominale (V) pour la charge d'un circuit : puissance maximale = protection × tension. */
    public const int TENSION_V = 230;

    /** Distance minimale (mm) entre le centre d'une boîte et le bord d'une ouverture. */
    public const int DISTANCE_MINI_OUVERTURE = 100;

    /** Demi-largeur d'un montant (mm) et rayon d'une boîte encastrée Ø 67 (mm) : au-dessous de leur somme, la boîte touche le montant. */
    public const int DEMI_LARGEUR_MONTANT = 25;
    public const int RAYON_BOITE = 34;

    /** Entraxe (mm) des montants quand le système appliqué n'en précise pas. */
    public const int ENTRAXE_DEFAUT = 600;

    public function __construct(private readonly RegistreTypesElements $types)
    {
    }

    /**
     * @return list<Avertissement>
     */
    public function controler(Projet $projet): array
    {
        $avertissements = [];
        $parametres = $projet->getParametres()['electricite'] ?? [];
        $prisesMin = \is_array($parametres) && is_numeric($parametres['prises_min'] ?? null) ? (int) $parametres['prises_min'] : self::PRISES_MIN;
        $lumieresMin = \is_array($parametres) && is_numeric($parametres['points_lumineux_min'] ?? null) ? (int) $parametres['points_lumineux_min'] : self::POINTS_LUMINEUX_MIN;

        foreach ($projet->getPieces() as $piece) {
            $electriques = $this->electriques($piece);
            if ([] === $electriques) {
                continue;
            }
            $prises = 0;
            $lumieres = 0;
            foreach ($electriques as $equipement) {
                $famille = $this->types->possede($equipement->getType()) ? $this->types->get($equipement->getType())->famille : null;
                $prises += TypeElement::FAMILLE_PRISE === $famille ? 1 : 0;
                $lumieres += TypeElement::FAMILLE_LUMINAIRE === $famille ? 1 : 0;
                array_push($avertissements, ...$this->position($equipement));
            }
            if ($prises < $prisesMin) {
                $avertissements[] = new Avertissement('electrical_min_sockets', ['room' => $piece->getNom(), 'count' => $prises, 'min' => $prisesMin]);
            }
            if ($lumieres < $lumieresMin) {
                $avertissements[] = new Avertissement('electrical_min_lights', ['room' => $piece->getNom(), 'count' => $lumieres, 'min' => $lumieresMin]);
            }
        }

        foreach ($projet->getReseaux() as $reseau) {
            $charge = $this->charge($projet, $reseau);
            $max = $reseau->getProtection() * self::TENSION_V;
            if ($charge > $max) {
                $avertissements[] = new Avertissement('electrical_circuit_overload', ['network' => $reseau->getNom(), 'load' => $charge, 'max' => $max, 'protection' => $reseau->getProtection()]);
            }
        }

        return $avertissements;
    }

    /**
     * Somme des puissances (W) des équipements d'un circuit.
     */
    public function charge(Projet $projet, Reseau $reseau): int
    {
        $total = 0;
        foreach ($projet->getPieces() as $piece) {
            foreach ($piece->getEquipements() as $equipement) {
                if ($equipement->getReseau() === $reseau) {
                    $total += $equipement->getPuissanceW() ?? 0;
                }
            }
        }

        return $total;
    }

    /**
     * Contrôles de position d'un équipement sur sa surface.
     *
     * @return list<Avertissement>
     */
    public function position(Equipement $equipement): array
    {
        $surface = $equipement->getSurface();
        $nom = $equipement->getNom() ?? $equipement->getType();
        $point = new Point2D($equipement->getU(), $equipement->getV());
        if (!$surface->getContour()->polygone()->contient($point)) {
            return [new Avertissement('electrical_outside_surface', ['element' => $nom, 'surface' => $surface->getNom()])];
        }

        $avertissements = [];
        foreach ($surface->getOuvertures() as $ouverture) {
            $dx = max($ouverture->getU() - $equipement->getU(), 0, $equipement->getU() - ($ouverture->getU() + $ouverture->getLargeur()));
            $dy = max($ouverture->getV() - $equipement->getV(), 0, $equipement->getV() - ($ouverture->getV() + $ouverture->getHauteur()));
            if (0 === $dx && 0 === $dy) {
                $avertissements[] = new Avertissement('electrical_on_opening', ['element' => $nom, 'surface' => $surface->getNom()]);
            } elseif (hypot($dx, $dy) < self::DISTANCE_MINI_OUVERTURE) {
                $avertissements[] = new Avertissement('electrical_near_opening', ['element' => $nom, 'distance' => (int) round(hypot($dx, $dy)), 'min' => self::DISTANCE_MINI_OUVERTURE]);
            }
        }
        if ($this->surMontant($equipement)) {
            $avertissements[] = new Avertissement('electrical_on_stud', ['element' => $nom, 'surface' => $surface->getNom()]);
        }

        return $avertissements;
    }

    /**
     * Entraxe (mm) des montants de l'ossature du système actif de la surface (ou de sa cloison), null sans ossature.
     */
    public function entraxeOssature(Surface $surface): ?int
    {
        if (!\in_array($surface->getType(), [TypeSurface::Mur, TypeSurface::CloisonFace], true)) {
            return null;
        }
        $systemes = $surface->getCloison()?->getSystemes() ?? $surface->getSystemes();
        foreach ($systemes as $systeme) {
            if (!$systeme->isActif()) {
                continue;
            }
            foreach ($systeme->getCouches() as $couche) {
                if ('ossature' === $couche->getRole()) {
                    return self::entraxe($systeme);
                }
            }
        }

        return null;
    }

    private static function entraxe(SystemeApplique $systeme): int
    {
        $entraxe = $systeme->getParametres()['entraxe'] ?? null;

        return is_numeric($entraxe) && (int) $entraxe > 0 ? (int) $entraxe : self::ENTRAXE_DEFAUT;
    }

    private function surMontant(Equipement $equipement): bool
    {
        $surface = $equipement->getSurface();
        $entraxe = $this->entraxeOssature($surface);
        if (null === $entraxe) {
            return false;
        }
        $geometrie = $surface->geometrie();
        $contact = self::DEMI_LARGEUR_MONTANT + self::RAYON_BOITE;
        $u = $equipement->getU();
        $v = $equipement->getV();
        $montants = $surface->coupes(Direction::Verticale, $entraxe);
        $boite = $geometrie->polygone()->boiteEnglobante();
        // Montant final et jambages des ouvertures, en plus des montants à l'entraxe.
        foreach ($montants as $montant) {
            if (abs($montant->debut->x - $u) < $contact && $v >= min($montant->debut->y, $montant->fin->y) - self::RAYON_BOITE && $v <= max($montant->debut->y, $montant->fin->y) + self::RAYON_BOITE) {
                return true;
            }
        }
        $supplementaires = [[$boite->maxX, $boite->minY, $boite->maxY]];
        foreach ($surface->getOuvertures() as $ouverture) {
            $bas = $ouverture->getV();
            $haut = $ouverture->getV() + $ouverture->getHauteur();
            $supplementaires[] = [$ouverture->getU(), $bas, $haut];
            $supplementaires[] = [$ouverture->getU() + $ouverture->getLargeur(), $bas, $haut];
        }
        foreach ($supplementaires as [$c, $bas, $haut]) {
            if (abs($c - $u) < $contact && $v >= $bas - self::RAYON_BOITE && $v <= $haut + self::RAYON_BOITE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Equipement>
     */
    private function electriques(Piece $piece): array
    {
        return array_values(array_filter($piece->getEquipements()->toArray(), static fn (Equipement $e): bool => FamilleEquipement::Electrique === $e->getFamille()));
    }
}
