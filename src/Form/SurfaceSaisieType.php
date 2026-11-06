<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Dto\SurfaceSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SurfaceSaisie>
 */
final class SurfaceSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('sommets', CollectionType::class, [
                'entry_type' => ContrainteSaisieType::class,
                'allow_add' => false,
                'allow_delete' => false,
                'label' => 'Vertices',
            ])
            ->add('ouvertures', CollectionType::class, [
                'entry_type' => OuvertureSaisieType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'label' => 'Openings',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SurfaceSaisie::class, 'translation_domain' => 'messages']);
    }
}
