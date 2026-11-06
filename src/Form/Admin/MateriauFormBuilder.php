<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Catalogue\Aplatisseur;
use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Construit le sous-formulaire des caractéristiques à partir du schéma de la catégorie.
 */
final class MateriauFormBuilder
{
    /**
     * @param FormBuilderInterface<mixed> $parent
     */
    public function ajouter(FormBuilderInterface $parent, CategorieMateriau $categorie): void
    {
        $sous = $parent->create('caracteristiques', FormType::class, ['label' => false]);
        foreach ($categorie->champs() as $champ) {
            $sous->add(Aplatisseur::nomChamp($champ->cle), ...$this->definition($champ));
        }
        $sous->addModelTransformer(new CaracteristiquesTransformer($categorie));
        $parent->add($sous);
    }

    /**
     * @return array{0: class-string<FormTypeInterface<mixed>>, 1: array<string, mixed>}
     */
    private function definition(ChampCaracteristique $champ): array
    {
        $options = [
            'required' => $champ->obligatoire,
            'label' => new TranslatableMessage(ucfirst(str_replace(['.', '_'], ' ', $champ->cle))),
            'help' => null !== $champ->unite ? new TranslatableMessage('Unit: {unit}', ['{unit}' => $champ->unite]) : null,
        ];

        return match ($champ->type) {
            TypeChamp::Entier => [IntegerType::class, $options],
            TypeChamp::Decimal => [NumberType::class, $options],
            TypeChamp::Texte => [TextType::class, $options],
            TypeChamp::Booleen => [CheckboxType::class, $options],
            TypeChamp::Choix => [ChoiceType::class, $options + [
                'choices' => array_combine($champ->choix, $champ->choix),
                'placeholder' => '',
            ]],
            TypeChamp::Liste => [TextareaType::class, $options + [
                'attr' => ['rows' => 8, 'spellcheck' => 'false'],
                'help' => new TranslatableMessage('JSON list'),
            ]],
        };
    }
}
