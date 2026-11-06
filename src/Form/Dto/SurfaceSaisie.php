<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Surface;
use App\Geometrie\TypeContrainte;
use Symfony\Component\Validator\Constraints as Assert;

final class SurfaceSaisie
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $nom = '';

    /** @var list<ContrainteSaisie> */
    #[Assert\Valid]
    public array $sommets = [];

    /** @var list<OuvertureSaisie> */
    #[Assert\Valid]
    public array $ouvertures = [];

    public static function depuis(Surface $surface): self
    {
        $saisie = new self();
        $saisie->nom = $surface->getNom();
        foreach ($surface->getContraintesSommets() as $contrainte) {
            if (TypeContrainte::Distance === $contrainte->type || TypeContrainte::Angle === $contrainte->type) {
                $saisie->sommets[] = ContrainteSaisie::depuis($contrainte);
            }
        }
        foreach ($surface->getOuvertures() as $ouverture) {
            $saisie->ouvertures[] = OuvertureSaisie::depuis($ouverture);
        }

        return $saisie;
    }
}
