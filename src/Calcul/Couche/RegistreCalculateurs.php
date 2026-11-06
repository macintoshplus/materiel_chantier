<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

final class RegistreCalculateurs
{
    /** @var array<string, CalculateurCouche> */
    private array $calculateurs = [];

    /**
     * @param iterable<CalculateurCouche> $calculateurs
     */
    public function __construct(iterable $calculateurs)
    {
        foreach ($calculateurs as $calculateur) {
            $this->calculateurs[$calculateur->code()] = $calculateur;
        }
        ksort($this->calculateurs);
    }

    public function possede(string $code): bool
    {
        return isset($this->calculateurs[$code]);
    }

    public function get(string $code): CalculateurCouche
    {
        return $this->calculateurs[$code] ?? throw new \InvalidArgumentException(\sprintf('Unknown layer calculator "%s".', $code));
    }

    /**
     * @return array<string, CalculateurCouche>
     */
    public function tous(): array
    {
        return $this->calculateurs;
    }
}
