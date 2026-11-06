<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Un critère porte sur une colonne ou une clé du JSON. La valeur peut être une référence au contexte
 * de calcul, de la forme `{contexte.nom}`, résolue avant le filtrage.
 */
final readonly class Critere
{
    /**
     * @param scalar|list<scalar> $valeur
     */
    public function __construct(
        public string $cle,
        public Operateur $operateur,
        public int|float|string|bool|array $valeur,
    ) {
    }

    public function referenceContexte(): ?string
    {
        if (\is_string($this->valeur) && 1 === preg_match('/^\{contexte\.([A-Za-z0-9_]+)\}$/', $this->valeur, $m)) {
            return $m[1];
        }

        return null;
    }
}
