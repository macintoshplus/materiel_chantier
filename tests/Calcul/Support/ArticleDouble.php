<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Support;

use App\Catalogue\MaterielLisible;

/**
 * Article de test sans base de données.
 */
final readonly class ArticleDouble implements MaterielLisible
{
    /**
     * @param array<string, mixed> $valeurs clés pointées à plat (ex. `pose.entraxe_max`)
     */
    public function __construct(private string $categorie, private array $valeurs = [])
    {
    }

    public function categorie(): string
    {
        return $this->categorie;
    }

    public function valeur(string $chemin): mixed
    {
        return $this->valeurs[$chemin] ?? null;
    }

    public static function isolant(float $lambda, int $epaisseur, int $largeur = 600, int $longueur = 1200): self
    {
        return new self('isolant', ['lambda' => $lambda, 'epaisseur' => $epaisseur, 'largeur' => $largeur, 'longueur' => $longueur]);
    }

    public static function plaque(int $epaisseur = 13): self
    {
        return new self('plaque_platre', [
            'epaisseur' => $epaisseur, 'largeur' => 1200, 'longueur' => 2500,
            'pose.entraxe_max' => 600, 'pose.pas_vis_premiere_peau' => 600,
            'pose.pas_vis_derniere_peau' => 300, 'pose.pas_vis_peau_unique' => 250,
        ]);
    }

    public static function profil(int $serie, int $entraxe = 600): self
    {
        return new self('profil_ossature', ['serie' => $serie, 'pose.entraxe_defaut' => $entraxe]);
    }

    public static function attache(): self
    {
        return new self('appui_fixation');
    }
}
