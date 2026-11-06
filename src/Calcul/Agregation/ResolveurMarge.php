<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Catalogue\MaterielLisible;
use App\Catalogue\RegistreCategories;

/**
 * Marge de sécurité d'un matériau : chiffrage > projet > matériau > catégorie > défaut global. Dans le chiffrage et le
 * projet, la référence du matériau prime sur sa catégorie.
 */
final readonly class ResolveurMarge
{
    public function __construct(
        private RegistreCategories $categories,
        private ConfigurationMarges $configuration = new ConfigurationMarges(),
    ) {
    }

    public function resoudre(MaterielLisible $article): MargeRetenue
    {
        $reference = $article->valeur('reference');
        $reference = \is_string($reference) && '' !== $reference ? $reference : null;
        $categorie = $article->categorie();
        $c = $this->configuration;

        $retenue = $this->dans($c->chiffrageMateriaux, $reference) ?? $this->dans($c->chiffrageCategories, $categorie);
        if (null !== $retenue) {
            return new MargeRetenue($retenue, SourceMarge::Chiffrage);
        }
        $retenue = $this->dans($c->projetMateriaux, $reference) ?? $this->dans($c->projetCategories, $categorie);
        if (null !== $retenue) {
            return new MargeRetenue($retenue, SourceMarge::Projet);
        }
        $propre = Marge::centiemes($article->valeur('marge_securite_pct'));
        if (null !== $propre) {
            return new MargeRetenue($propre, SourceMarge::Materiau);
        }
        if ($this->categories->possede($categorie)) {
            $defaut = Marge::centiemes($this->categories->get($categorie)->margeDefautPct());
            if (null !== $defaut) {
                return new MargeRetenue($defaut, SourceMarge::Categorie);
            }
        }

        return new MargeRetenue($c->defautGlobal, SourceMarge::Globale);
    }

    /**
     * @param array<string, int> $table
     */
    private function dans(array $table, ?string $cle): ?int
    {
        return null !== $cle ? ($table[$cle] ?? null) : null;
    }
}
