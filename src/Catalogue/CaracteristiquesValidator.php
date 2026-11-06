<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Valide le JSON `caracteristiques` contre le schéma d'une catégorie : clés connues, types, choix,
 * bornes, clés obligatoires, doublons de lignes d'une liste.
 */
final class CaracteristiquesValidator
{
    /**
     * @param array<array-key, mixed> $caracteristiques
     *
     * @return list<ErreurCaracteristique>
     */
    public function valider(CategorieMateriau $categorie, array $caracteristiques): array
    {
        $erreurs = [];
        $champs = [];
        foreach ($categorie->champs() as $champ) {
            $champs[$champ->cle] = $champ;
        }

        $this->parcourir($caracteristiques, '', $champs, $erreurs);

        foreach ($champs as $cle => $champ) {
            if ($champ->obligatoire && null === $this->lire($caracteristiques, $cle)) {
                $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::REQUIS, $cle);
            }
        }

        return $erreurs;
    }

    /**
     * @param array<array-key, mixed>              $donnees
     * @param array<string, ChampCaracteristique>  $champs
     * @param list<ErreurCaracteristique>          $erreurs
     */
    private function parcourir(array $donnees, string $prefixe, array $champs, array &$erreurs): void
    {
        foreach ($donnees as $cle => $valeur) {
            $chemin = $prefixe . $cle;
            if (isset($champs[$chemin])) {
                $this->validerValeur($champs[$chemin], $valeur, $chemin, $erreurs);

                continue;
            }
            if (\is_array($valeur) && $this->estGroupe($chemin, $champs)) {
                $this->parcourir($valeur, $chemin . '.', $champs, $erreurs);

                continue;
            }
            $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::CLE_INCONNUE, $chemin);
        }
    }

    /**
     * @param array<string, ChampCaracteristique> $champs
     */
    private function estGroupe(string $chemin, array $champs): bool
    {
        foreach ($champs as $cle => $_) {
            if (str_starts_with($cle, $chemin . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ErreurCaracteristique> $erreurs
     */
    private function validerValeur(ChampCaracteristique $champ, mixed $valeur, string $chemin, array &$erreurs): void
    {
        if (null === $valeur) {
            return; // l'absence est traitée par le contrôle des champs obligatoires
        }

        $typeOk = match ($champ->type) {
            TypeChamp::Entier => \is_int($valeur),
            TypeChamp::Decimal => \is_int($valeur) || \is_float($valeur),
            TypeChamp::Texte => \is_string($valeur),
            TypeChamp::Choix => \is_string($valeur),
            TypeChamp::Booleen => \is_bool($valeur),
            TypeChamp::Liste => \is_array($valeur) && array_is_list($valeur),
        };
        if (!$typeOk) {
            $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::TYPE_INVALIDE, $chemin, ['type' => $champ->type->value]);

            return;
        }

        if (TypeChamp::Choix === $champ->type && !\in_array($valeur, $champ->choix, true)) {
            $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::CHOIX_INVALIDE, $chemin, ['choices' => implode(', ', $champ->choix)]);

            return;
        }

        if (\is_int($valeur) || \is_float($valeur)) {
            if ((null !== $champ->min && $valeur < $champ->min) || (null !== $champ->max && $valeur > $champ->max)) {
                $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::HORS_BORNES, $chemin, [
                    'min' => $champ->min ?? '-',
                    'max' => $champ->max ?? '-',
                ]);
            }
        }

        if (TypeChamp::Liste === $champ->type && \is_array($valeur)) {
            $this->validerListe($champ, $valeur, $chemin, $erreurs);
        }
    }

    /**
     * @param array<array-key, mixed>     $lignes
     * @param list<ErreurCaracteristique> $erreurs
     */
    private function validerListe(ChampCaracteristique $champ, array $lignes, string $chemin, array &$erreurs): void
    {
        $sous = [];
        foreach ($champ->sousChamps as $s) {
            $sous[$s->cle] = $s;
        }

        $vues = [];
        foreach ($lignes as $i => $ligne) {
            $base = $chemin . '[' . $i . ']';
            if (!\is_array($ligne)) {
                $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::TYPE_INVALIDE, $base, ['type' => 'object']);

                continue;
            }
            foreach ($ligne as $cle => $valeur) {
                if (!isset($sous[$cle])) {
                    $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::CLE_INCONNUE, $base . '.' . $cle);

                    continue;
                }
                $this->validerValeur($sous[$cle], $valeur, $base . '.' . $cle, $erreurs);
            }
            foreach ($sous as $cle => $s) {
                if ($s->obligatoire && null === ($ligne[$cle] ?? null)) {
                    $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::REQUIS, $base . '.' . $cle);
                }
            }

            if ([] !== $champ->cleUnique) {
                $identite = json_encode(array_map(static fn (string $k) => $ligne[$k] ?? null, $champ->cleUnique), \JSON_THROW_ON_ERROR);
                if (isset($vues[$identite])) {
                    $erreurs[] = new ErreurCaracteristique(ErreurCaracteristique::DOUBLON, $base, ['key' => implode(', ', $champ->cleUnique)]);
                }
                $vues[$identite] = true;
            }
        }
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
}
