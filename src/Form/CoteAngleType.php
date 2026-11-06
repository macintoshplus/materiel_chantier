<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une ligne de la saisie « côtés et angles » : côté (mm) et angle intérieur à la fin du côté (degrés).
 *
 * @extends AbstractType<array{cote: float|null, angle: float|null}>
 */
final class CoteAngleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cote', NumberType::class, ['label' => 'Side (mm)', 'required' => false, 'scale' => 1, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0]])
            ->add('angle', NumberType::class, ['label' => 'Angle at the end of the side (degrees)', 'required' => false, 'scale' => 2, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 1, 'max' => 359]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false]);
    }
}
