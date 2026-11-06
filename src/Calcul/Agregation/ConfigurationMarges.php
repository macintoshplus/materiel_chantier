<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

/**
 * Marges (centièmes de pourcent) imposées par le chiffrage ou par le projet, par référence de matériau ou par
 * catégorie. Un matériau l'emporte sur sa catégorie au sein d'un même niveau.
 */
final readonly class ConfigurationMarges
{
    /**
     * @param array<string, int> $chiffrageMateriaux
     * @param array<string, int> $chiffrageCategories
     * @param array<string, int> $projetMateriaux
     * @param array<string, int> $projetCategories
     */
    public function __construct(
        public array $chiffrageMateriaux = [],
        public array $chiffrageCategories = [],
        public array $projetMateriaux = [],
        public array $projetCategories = [],
        public int $defautGlobal = 0,
    ) {
    }

    /**
     * Lit `Projet.parametres['marges']` (`materiaux` : référence → %, `categories` : code → %) ; une valeur qui n'est
     * pas un pourcentage valide est ignorée. `$chiffrage` a la même forme et prime sur le projet.
     *
     * @param array<string, mixed> $parametresProjet
     * @param array<string, mixed> $chiffrage
     */
    public static function depuis(array $parametresProjet, array $chiffrage = []): self
    {
        $projet = \is_array($parametresProjet['marges'] ?? null) ? $parametresProjet['marges'] : [];

        return new self(
            self::lire($chiffrage['materiaux'] ?? null),
            self::lire($chiffrage['categories'] ?? null),
            self::lire($projet['materiaux'] ?? null),
            self::lire($projet['categories'] ?? null),
        );
    }

    /**
     * @return array<string, int>
     */
    private static function lire(mixed $table): array
    {
        $lues = [];
        if (!\is_array($table)) {
            return $lues;
        }
        foreach ($table as $cle => $valeur) {
            $centiemes = Marge::centiemes($valeur);
            if (null !== $centiemes) {
                $lues[(string) $cle] = $centiemes;
            }
        }

        return $lues;
    }
}
