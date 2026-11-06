<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Cloison;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'une cloison (mm, degrés). Les champs « longueur », « angle » et « décalage » ne s'appliquent que s'ils
 * diffèrent du tracé courant ; à la création, la fin se déduit de la longueur et de l'angle si elle est absente.
 */
final class CloisonSaisie
{
    public const AUTO = 'auto';
    public const LIBRE = 'libre';

    #[Assert\Length(max: 255)]
    public ?string $nom = null;

    public ?int $debutX = null;

    public ?int $debutY = null;

    public ?int $finX = null;

    public ?int $finY = null;

    #[Assert\Positive]
    public ?int $longueur = null;

    public ?float $angle = null;

    /** Identifiant du mur de référence du décalage. */
    public ?string $murReference = null;

    #[Assert\PositiveOrZero]
    public ?int $decalage = null;

    /** `auto`, `libre`, `mur:ID` ou `cloison:ID`. */
    public string $ancrageDebut = self::AUTO;

    public string $ancrageFin = self::AUTO;

    /** @var list<OuvertureSaisie> */
    #[Assert\Valid]
    public array $ouvertures = [];

    public static function depuis(Cloison $cloison): self
    {
        $trace = $cloison->getTrace();
        $saisie = new self();
        $saisie->nom = $cloison->getNom();
        $saisie->debutX = $trace->debut->x;
        $saisie->debutY = $trace->debut->y;
        $saisie->finX = $trace->fin->x;
        $saisie->finY = $trace->fin->y;
        $saisie->longueur = $trace->longueur();
        $saisie->angle = round($trace->angleDegres(), 1);
        foreach ($cloison->getOuvertures() as $ouverture) {
            $saisie->ouvertures[] = OuvertureSaisie::depuis($ouverture);
        }
        foreach (['ancrageDebut' => $cloison->getParametres()['ancrages_forces']['debut'] ?? false, 'ancrageFin' => $cloison->getParametres()['ancrages_forces']['fin'] ?? false] as $propriete => $force) {
            if (!$force) {
                continue;
            }
            $ancrage = 'ancrageDebut' === $propriete ? $cloison->getAncrageDebut() : $cloison->getAncrageFin();
            $saisie->{$propriete} = 'libre' === $ancrage['type'] || null === $ancrage['cible'] ? self::LIBRE : $ancrage['type'].':'.$ancrage['cible'];
        }

        return $saisie;
    }
}
