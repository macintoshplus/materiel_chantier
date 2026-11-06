<?php

declare(strict_types=1);

namespace App\Constraints;

use App\Catalogue\CaracteristiquesValidator;
use App\Catalogue\ErreurCaracteristique;
use App\Catalogue\RegistreCategories;
use App\Entity\Materiau;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CaracteristiquesValidesValidator extends ConstraintValidator
{
    private const array MESSAGES = [
        ErreurCaracteristique::REQUIS => 'The characteristic "{{ path }}" is required.',
        ErreurCaracteristique::CLE_INCONNUE => 'The characteristic "{{ path }}" is not defined for this category.',
        ErreurCaracteristique::TYPE_INVALIDE => 'The characteristic "{{ path }}" must be of type {{ type }}.',
        ErreurCaracteristique::CHOIX_INVALIDE => 'The characteristic "{{ path }}" must be one of: {{ choices }}.',
        ErreurCaracteristique::HORS_BORNES => 'The characteristic "{{ path }}" is out of range (min {{ min }}, max {{ max }}).',
        ErreurCaracteristique::DOUBLON => 'The line "{{ path }}" duplicates another one (same {{ key }}).',
    ];

    public function __construct(
        private readonly RegistreCategories $categories,
        private readonly CaracteristiquesValidator $validateur,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CaracteristiquesValides) {
            throw new UnexpectedTypeException($constraint, CaracteristiquesValides::class);
        }
        if (!$value instanceof Materiau) {
            throw new UnexpectedTypeException($value, Materiau::class);
        }

        if (!$this->categories->possede($value->getCategorie())) {
            $this->context->buildViolation('Unknown category "{{ category }}".')
                ->setParameter('{{ category }}', $value->getCategorie())
                ->atPath('categorie')
                ->addViolation();

            return;
        }

        $erreurs = $this->validateur->valider($this->categories->get($value->getCategorie()), $value->getCaracteristiques());
        foreach ($erreurs as $erreur) {
            $parametres = ['{{ path }}' => $erreur->chemin];
            foreach ($erreur->parametres as $cle => $valeur) {
                $parametres['{{ ' . $cle . ' }}'] = (string) $valeur;
            }
            $this->context->buildViolation(self::MESSAGES[$erreur->code])
                ->setParameters($parametres)
                ->atPath('caracteristiques')
                ->addViolation();
        }
    }
}
