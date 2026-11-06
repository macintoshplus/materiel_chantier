<?php

declare(strict_types=1);

namespace App\Import\Dxf;

/**
 * Entité DXF neutre : type, paires (code de groupe, valeur) et sous-entités (VERTEX d'une POLYLINE, ATTRIB d'un INSERT).
 */
final class DxfEntite
{
    /** @var list<self> */
    private array $enfants = [];

    /**
     * @param list<array{int, string}> $groupes
     */
    public function __construct(public readonly string $type, public readonly array $groupes = [])
    {
    }

    public function ajouterEnfant(self $enfant): void
    {
        $this->enfants[] = $enfant;
    }

    /**
     * @return list<self>
     */
    public function enfants(): array
    {
        return $this->enfants;
    }

    public function calque(): string
    {
        return $this->valeur(8) ?? '0';
    }

    public function valeur(int $code): ?string
    {
        foreach ($this->groupes as [$c, $v]) {
            if ($c === $code) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function valeurs(int $code): array
    {
        $valeurs = [];
        foreach ($this->groupes as [$c, $v]) {
            if ($c === $code) {
                $valeurs[] = $v;
            }
        }

        return $valeurs;
    }

    public function nombre(int $code, float $defaut = 0.0): float
    {
        $v = $this->valeur($code);

        return null !== $v && is_numeric(trim($v)) ? (float) trim($v) : $defaut;
    }

    public function entier(int $code, int $defaut = 0): int
    {
        return (int) $this->nombre($code, (float) $defaut);
    }
}
