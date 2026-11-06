<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Passage entre le JSON imbriqué `caracteristiques` et un tableau plat (clé pointée avec `.` remplacé par `__`),
 * utilisé par le formulaire dynamique. Les listes sont présentées en JSON.
 */
final class Aplatisseur
{
    /**
     * @param array<array-key, mixed> $caracteristiques
     *
     * @return array<string, mixed>
     */
    public function aplatir(CategorieMateriau $categorie, array $caracteristiques): array
    {
        $plat = [];
        foreach ($categorie->champs() as $champ) {
            $valeur = $this->lire($caracteristiques, $champ->cle);
            if (TypeChamp::Liste === $champ->type && null !== $valeur) {
                $valeur = json_encode($valeur, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
            }
            $plat[self::nomChamp($champ->cle)] = $valeur;
        }

        return $plat;
    }

    /**
     * @param array<array-key, mixed> $plat
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException si une liste n'est pas du JSON valide
     */
    public function imbriquer(CategorieMateriau $categorie, array $plat): array
    {
        $resultat = [];
        foreach ($categorie->champs() as $champ) {
            $valeur = $plat[self::nomChamp($champ->cle)] ?? null;
            if (null === $valeur || '' === $valeur) {
                continue;
            }
            if (TypeChamp::Liste === $champ->type && \is_string($valeur)) {
                $valeur = json_decode($valeur, true, 512, \JSON_THROW_ON_ERROR);
            }
            $this->ecrire($resultat, $champ->cle, $valeur);
        }

        return $resultat;
    }

    public static function nomChamp(string $cle): string
    {
        return str_replace('.', '__', $cle);
    }

    /**
     * @param array<array-key, mixed> $donnees
     */
    private function lire(array $donnees, string $chemin): mixed
    {
        $courant = $donnees;
        foreach (explode('.', $chemin) as $segment) {
            if (!\is_array($courant) || !\array_key_exists($segment, $courant)) {
                return null;
            }
            $courant = $courant[$segment];
        }

        return $courant;
    }

    /**
     * @param array<array-key, mixed> $cible
     */
    private function ecrire(array &$cible, string $chemin, mixed $valeur): void
    {
        $segments = explode('.', $chemin);
        $dernier = array_pop($segments);
        $courant = &$cible;
        foreach ($segments as $segment) {
            if (!isset($courant[$segment]) || !\is_array($courant[$segment])) {
                $courant[$segment] = [];
            }
            $courant = &$courant[$segment];
        }
        $courant[$dernier] = $valeur;
    }
}
