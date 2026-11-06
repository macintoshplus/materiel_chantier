<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Equipement;
use App\Form\Dto\LiaisonSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LiaisonSaisie>
 */
final class LiaisonSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $champ = [
            'choices' => $options['equipements'],
            'choice_label' => $options['libelle'],
            'choice_value' => static fn (?Equipement $e): string => null === $e ? '' : (string) $e->getId(),
            'choice_translation_domain' => false,
            'placeholder' => 'Choose an element',
        ];
        $builder
            ->add('equipement', ChoiceType::class, ['label' => 'Element'] + $champ)
            ->add('parent', ChoiceType::class, ['label' => 'Fed from'] + $champ)
            ->add('conducteurs', IntegerType::class, ['label' => 'Active conductors (earth excluded, empty = automatic, 3 = two-way link)', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => LiaisonSaisie::class, 'label' => false, 'translation_domain' => 'messages', 'equipements' => [], 'libelle' => null])
            ->setAllowedTypes('equipements', 'array');
    }
}
