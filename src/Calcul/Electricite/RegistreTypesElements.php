<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Catalogue des types d'éléments électriques, construit depuis des données (`config/services.yaml`) : ajouter un type
 * n'exige ni code ni migration.
 */
final class RegistreTypesElements
{
    /** @var array<string, TypeElement> */
    private array $types = [];

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $code => $d) {
            $this->types[$code] = new TypeElement(
                $code,
                self::texte($d['libelle'] ?? $code),
                self::texte($d['famille'] ?? TypeElement::FAMILLE_CHARGE),
                self::entier($d['conducteurs'] ?? 2),
                (bool) ($d['terre'] ?? true),
                (bool) ($d['navette'] ?? false),
                self::entier($d['hauteur'] ?? 0),
                (bool) ($d['derivation'] ?? false),
                self::materiel($d['boite'] ?? null),
                self::materiel($d['mecanisme'] ?? null),
                self::materiel($d['plaque'] ?? null),
                self::materiel($d['connecteur'] ?? null),
            );
        }
    }

    public function possede(string $code): bool
    {
        return isset($this->types[$code]);
    }

    public function get(string $code): TypeElement
    {
        return $this->types[$code] ?? throw new \InvalidArgumentException(\sprintf('Unknown electrical element type "%s".', $code));
    }

    /**
     * @return array<string, TypeElement>
     */
    public function tous(): array
    {
        return $this->types;
    }

    private static function texte(mixed $valeur): string
    {
        return \is_scalar($valeur) ? (string) $valeur : '';
    }

    private static function entier(mixed $valeur): int
    {
        return is_numeric($valeur) ? (int) $valeur : 0;
    }

    private static function materiel(mixed $donnees): ?MaterielAssocie
    {
        if (!\is_array($donnees) || !isset($donnees['categorie']) || !\is_string($donnees['categorie'])) {
            return null;
        }
        $criteres = [];
        $brut = $donnees['criteres'] ?? [];
        if (\is_array($brut)) {
            foreach ($brut as $cle => $valeur) {
                if (\is_int($valeur) || \is_string($valeur)) {
                    $criteres[(string) $cle] = $valeur;
                }
            }
        }

        return new MaterielAssocie($donnees['categorie'], $criteres);
    }
}
