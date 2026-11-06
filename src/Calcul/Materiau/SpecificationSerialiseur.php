<?php

declare(strict_types=1);

namespace App\Calcul\Materiau;

use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;

/**
 * Passage d'une spécification au JSON stocké sur `CoucheAppliquee` : {role, categorie, criteres[{cle, operateur, valeur}]}.
 */
final readonly class SpecificationSerialiseur
{
    public function __construct(private RegistreCategories $categories)
    {
    }

    /**
     * @return array{role: string, categorie: string, criteres: list<array{cle: string, operateur: string, valeur: mixed}>}
     */
    public function vers(MaterielSpecification $specification): array
    {
        $criteres = [];
        foreach ($specification->criteres as $critere) {
            $criteres[] = ['cle' => $critere->cle, 'operateur' => $critere->operateur->value, 'valeur' => $critere->valeur];
        }

        return ['role' => $specification->role, 'categorie' => $specification->categorie->code(), 'criteres' => $criteres];
    }

    /**
     * @param array<array-key, mixed> $donnees
     */
    public function depuis(array $donnees): MaterielSpecification
    {
        $role = $donnees['role'] ?? null;
        $categorie = $donnees['categorie'] ?? null;
        $brutes = $donnees['criteres'] ?? [];
        if (!\is_string($role) || !\is_string($categorie) || !\is_array($brutes)) {
            throw new \InvalidArgumentException('Invalid specification data.');
        }
        $criteres = [];
        foreach ($brutes as $ligne) {
            if (!\is_array($ligne) || !\is_string($ligne['cle'] ?? null) || !\is_string($ligne['operateur'] ?? null)) {
                throw new \InvalidArgumentException('Invalid criterion data.');
            }
            $valeur = $ligne['valeur'] ?? null;
            if (\is_array($valeur)) {
                $liste = [];
                foreach ($valeur as $v) {
                    if (!\is_scalar($v)) {
                        throw new \InvalidArgumentException('Invalid criterion value.');
                    }
                    $liste[] = $v;
                }
                $valeur = $liste;
            } elseif (!\is_scalar($valeur)) {
                throw new \InvalidArgumentException('Invalid criterion value.');
            }
            $criteres[] = new Critere($ligne['cle'], Operateur::from($ligne['operateur']), $valeur);
        }

        return MaterielSpecification::creer($role, $this->categories->get($categorie), $criteres);
    }
}
