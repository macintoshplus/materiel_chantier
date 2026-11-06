<?php

declare(strict_types=1);

namespace App\Import\Assistant;

/**
 * État de l'assistant d'import conservé en session (aucune entité brouillon) : source, unité choisie, rôles des
 * calques, contour retenu, options et rôles des blocs. Le fichier lui-même est dans un fichier temporaire.
 */
final class EtatAssistant
{
    /**
     * @param array<string, string> $calques calque => rôle
     * @param array<int, string>    $blocs   index du bloc => rôle
     */
    public function __construct(
        public string $source,
        public int $projetId,
        public string $nomFichier,
        public string $unite = 'mm',
        public bool $uniteDevinee = false,
        public bool $parametresConfirmes = false,
        public ?string $format = null,
        public array $calques = [],
        public ?int $contour = null,
        public bool $contourConfirme = false,
        public string $mode = 'interieur',
        public int $epaisseur = 100,
        public int $hauteur = 2500,
        public string $nom = '',
        public array $blocs = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function versTableau(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param array<mixed> $t
     */
    public static function depuisTableau(array $t): self
    {
        $etat = new self(
            \is_string($t['source'] ?? null) ? $t['source'] : 'dxf',
            \is_int($t['projetId'] ?? null) ? $t['projetId'] : 0,
            \is_string($t['nomFichier'] ?? null) ? $t['nomFichier'] : '',
        );
        $etat->unite = \is_string($t['unite'] ?? null) ? $t['unite'] : 'mm';
        $etat->uniteDevinee = true === ($t['uniteDevinee'] ?? false);
        $etat->parametresConfirmes = true === ($t['parametresConfirmes'] ?? false);
        $etat->format = \is_string($t['format'] ?? null) ? $t['format'] : null;
        $etat->contour = \is_int($t['contour'] ?? null) ? $t['contour'] : null;
        $etat->contourConfirme = true === ($t['contourConfirme'] ?? false);
        $etat->mode = \is_string($t['mode'] ?? null) ? $t['mode'] : 'interieur';
        $etat->epaisseur = \is_int($t['epaisseur'] ?? null) ? $t['epaisseur'] : 100;
        $etat->hauteur = \is_int($t['hauteur'] ?? null) ? $t['hauteur'] : 2500;
        $etat->nom = \is_string($t['nom'] ?? null) ? $t['nom'] : '';
        foreach (\is_array($t['calques'] ?? null) ? $t['calques'] : [] as $calque => $role) {
            if (\is_string($role)) {
                $etat->calques[(string) $calque] = $role;
            }
        }
        foreach (\is_array($t['blocs'] ?? null) ? $t['blocs'] : [] as $index => $role) {
            if (\is_string($role)) {
                $etat->blocs[(int) $index] = $role;
            }
        }

        return $etat;
    }
}
