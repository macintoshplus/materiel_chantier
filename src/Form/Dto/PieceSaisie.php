<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Piece;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Éditeur de pièce : nom, hauteur par défaut et sommets du sol. Une seule opération sur les sommets par
 * enregistrement : déplacer un sommet, en ajouter un (arête + distance) ou en supprimer un.
 */
final class PieceSaisie
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $nom = '';

    #[Assert\Positive]
    public ?int $hauteurSousPlafond = null;

    /** @var list<SommetSaisie> */
    #[Assert\Valid]
    public array $sommets = [];

    /** Identifiant du sommet de début de l'arête à scinder. */
    public ?string $ajoutArete = null;

    #[Assert\Positive]
    public ?int $ajoutDistance = null;

    public static function depuis(Piece $piece): self
    {
        $saisie = new self();
        $saisie->nom = $piece->getNom();
        $saisie->hauteurSousPlafond = $piece->getHauteurSousPlafond();
        $sol = $piece->getSol();
        if (null !== $sol) {
            foreach ($sol->getContour()->sommets as $sommet) {
                $s = new SommetSaisie();
                $s->id = $sommet->id;
                $s->x = $sommet->point->x;
                $s->y = $sommet->point->y;
                $saisie->sommets[] = $s;
            }
        }

        return $saisie;
    }
}
