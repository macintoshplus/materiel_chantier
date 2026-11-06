<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Dto\SommetSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SommetSaisie>
 */
final class SommetSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('x', IntegerType::class, ['label' => 'X (mm)'])
            ->add('y', IntegerType::class, ['label' => 'Y (mm)'])
            ->add('supprimer', CheckboxType::class, ['label' => 'Remove this vertex', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SommetSaisie::class, 'label' => false]);
    }
}
