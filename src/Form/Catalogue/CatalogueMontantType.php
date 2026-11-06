<?php

declare(strict_types=1);

namespace App\Form\Catalogue;

use App\Model\Catalogue\Montant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use App\Form\Type\MetreType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Montant> */
class CatalogueMontantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('longueur', MetreType::class, [
                'label' => 'Longueur (m)',
                'scale' => 3,
            ])
            ->add('largeur', MetreType::class, [
                'label' => 'Largeur (m)',
                'scale' => 3,
            ])
            ->add('entraxe', MetreType::class, [
                'label' => 'Entraxe (m)',
                'scale' => 3,
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
            'data_class' => Montant::class
        ]);
    }
}
