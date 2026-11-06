<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

/**
 * Demande de résolution d'un article chez un fournisseur. Sans spécification résolue ou sans autorisation, aucun
 * équivalent n'est cherché ; `autresFournisseurs` est vide pour une stratégie à fournisseur unique.
 */
final readonly class DemandeOffre
{
    /**
     * @param list<int> $autresFournisseurs
     */
    public function __construct(
        public MaterielLisible $epingle,
        public ?MaterielSpecification $specification,
        public int $fournisseur,
        public bool $equivalentsAutorises = true,
        public array $autresFournisseurs = [],
    ) {
    }

    public function chez(int $fournisseur): self
    {
        return new self($this->epingle, $this->specification, $fournisseur, $this->equivalentsAutorises, []);
    }
}
