<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Rôle d'un calque ou d'un bloc : contour de pièce, porte, fenêtre, équipement (rapport seulement), ignoré.
 */
enum RoleImport: string
{
    case Contour = 'contour';
    case Porte = 'porte';
    case Fenetre = 'fenetre';
    case Equipement = 'equipement';
    case Ignorer = 'ignorer';

    /**
     * Rôle probable d'un calque d'après son nom (nommage non standardisé : l'utilisateur corrige).
     */
    public static function devinerCalque(string $nom): self
    {
        $n = strtoupper($nom);

        return match (true) {
            1 === preg_match('/DOOR|PORTE|PORT\b|A-DOOR/', $n) => self::Porte,
            1 === preg_match('/WIND|FENETRE|FENÊTRE|FEN\b|GLAZ|VITR/', $n) => self::Fenetre,
            1 === preg_match('/ELEC|PRISE|SOCKET|LIGHT|LUM|PLUMB|PLOMB|HVAC|VENT|FURN|MEUBLE|EQUIP/', $n) => self::Equipement,
            1 === preg_match('/DIM|TEXT|ANNO|HATCH|GRID|AXE|DEFPOINTS|COTE|TXT/', $n) => self::Ignorer,
            default => self::Contour,
        };
    }

    /**
     * Rôle probable d'un bloc d'après son nom ; null si le nom ne dit rien.
     */
    public static function devinerBloc(string $nom): ?self
    {
        $n = strtoupper($nom);

        return match (true) {
            1 === preg_match('/DOOR|PORTE|\bDR\b|^D[-_ ]?\d/', $n) => self::Porte,
            1 === preg_match('/WIND|FENETRE|FENÊTRE|\bWIN\b|^W[-_ ]?\d|FEN\b/', $n) => self::Fenetre,
            default => null,
        };
    }

    /**
     * Libellé anglais (clé de traduction).
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Contour => 'Room outline',
            self::Porte => 'Door',
            self::Fenetre => 'Window',
            self::Equipement => 'Equipment',
            self::Ignorer => 'Ignore',
        };
    }

    /**
     * Rôles proposables pour un bloc.
     *
     * @return list<self>
     */
    public static function pourBloc(): array
    {
        return [self::Porte, self::Fenetre, self::Equipement, self::Ignorer];
    }
}
