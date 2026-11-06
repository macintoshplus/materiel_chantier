<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Un sommet du sol (x, y en mm) dans le tableau de sommets.
 *
 * @extends AbstractType<array{x: int|null, y: int|null}>
 */
final class PointSolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('x', IntegerType::class, ['label' => 'X (mm)'])
            ->add('y', IntegerType::class, ['label' => 'Y (mm)']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false]);
    }
}
