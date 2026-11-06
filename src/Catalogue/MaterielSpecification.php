<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Ce qu'on cherche dans le catalogue : un rôle, une catégorie et des critères.
 */
final readonly class MaterielSpecification
{
    /** Colonnes de `Materiau` pouvant servir de critère. */
    public const array COLONNES = ['longueur', 'largeur', 'epaisseur', 'hauteur', 'poids', 'unites_par_lot', 'unite_vente'];

    /**
     * @param list<Critere> $criteres
     */
    private function __construct(
        public string $role,
        public CategorieMateriau $categorie,
        public array $criteres,
    ) {
    }

    /**
     * @param list<Critere> $criteres
     *
     * @throws \InvalidArgumentException si un critère porte sur une clé inconnue du schéma de la catégorie
     */
    public static function creer(string $role, CategorieMateriau $categorie, array $criteres): self
    {
        $connues = self::COLONNES;
        foreach ($categorie->champs() as $champ) {
            $connues[] = $champ->cle;
        }
        foreach ($criteres as $critere) {
            if (!\in_array($critere->cle, $connues, true)) {
                throw new \InvalidArgumentException(\sprintf('Unknown criterion key "%s" for category "%s".', $critere->cle, $categorie->code()));
            }
        }

        return new self($role, $categorie, $criteres);
    }

    public function estResolue(): bool
    {
        foreach ($this->criteres as $critere) {
            if (null !== $critere->referenceContexte()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, scalar> $contexte
     */
    public function resoudre(array $contexte): self
    {
        $criteres = [];
        foreach ($this->criteres as $critere) {
            $nom = $critere->referenceContexte();
            if (null === $nom) {
                $criteres[] = $critere;

                continue;
            }
            if (!\array_key_exists($nom, $contexte)) {
                throw new \InvalidArgumentException(\sprintf('Missing context value "%s".', $nom));
            }
            $criteres[] = new Critere($critere->cle, $critere->operateur, $contexte[$nom]);
        }

        return new self($this->role, $this->categorie, $criteres);
    }

    public function estSatisfaitePar(MaterielLisible $materiel): bool
    {
        if (!$this->estResolue()) {
            throw new \LogicException('The specification still references the calculation context; call resoudre() first.');
        }
        if ($materiel->categorie() !== $this->categorie->code()) {
            return false;
        }
        foreach ($this->criteres as $critere) {
            if (!$critere->operateur->evaluer($materiel->valeur($critere->cle), $critere->valeur)) {
                return false;
            }
        }

        return true;
    }
}
