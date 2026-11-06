<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie « côtés et angles » : nom, hauteur, fermeture automatique et lignes dynamiques (ajout/retrait côté navigateur).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class CotesAnglesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('hauteur', IntegerType::class, ['label' => 'Ceiling height (mm)', 'constraints' => [new Assert\NotNull(), new Assert\Positive()]])
            ->add('fermerAuto', CheckboxType::class, ['label' => 'Close automatically when the gap is small', 'required' => false, 'help' => 'The gap is spread over the sides when it is under 20 mm.'])
            ->add('lignes', CollectionType::class, [
                'label' => 'Sides, counter-clockwise',
                'entry_type' => CoteAngleType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'prototype_data' => ['cote' => null, 'angle' => 90.0],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages']);
    }
}
