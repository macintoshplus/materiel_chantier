<?php

declare(strict_types=1);

namespace App\Form\Catalogue;

use App\Model\Catalogue\PlaquePlatre;
use App\Model\Catalogue\Rail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<PlaquePlatre> */
class CataloguePlaquePlatreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('longueur', NumberType::class, [
                'label' => 'Longueur (m)',
                'scale' => 2,
            ])
            ->add('largeur', NumberType::class, [
                'label' => 'Largeur (m)',
                'scale' => 2,
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix du lot',
                'scale' => 2,
                'empty_data' => 0.00,
            ])
            ->add('uniteParLot', IntegerType::class, [
                'label' => 'Nombre d\'unité par lot',
                'empty_data' => 1,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaquePlatre::class
        ]);
    }
}
