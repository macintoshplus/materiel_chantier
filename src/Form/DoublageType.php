<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:22
 */

declare(strict_types=1);


namespace App\Form;

use App\Model\Mur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoublageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('largeur', NumberType::class, [
                'label' => 'Largeur du mur (m)',
                'scale' => 2,
            ])
            ->add('hauteur', NumberType::class, [
                'label' => 'Hauteur du mur (m)',
                'scale' => 2,
            ])
            ->add('ecartement', IntegerType::class, [
                'label' => 'Écartement doublage / mur (mm)',
            ])
            ->add('ecartDebut', IntegerType::class, [
                'label' => 'Écartement doublage / mur gauche (mm)',
                'help' => 'Mettre 0 si le doublage est collé au mur adjacent',
            ])
            ->add('ecartFin', IntegerType::class, [
                'label' => 'Écartement doublage / mur droit (mm)',
                'help' => 'Mettre 0 si le doublage est collé au mur adjacent',
            ])
            ->add('typeStructure', ChoiceType::class, ['choices'=>[
                'Doublage' => Mur::TYPE_DOUBLAGE,
                'Cloison' => Mur::TYPE_CLOISON,
            ]])
            ->add('ouvertures', CollectionType::class, [
                'label' => 'Fenêtres / portes',
                'entry_type' => OuvertureType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'error_bubbling' => false,
            ])
            ->add('catalogue', CatalogueType::class, [
                'label' => 'Catalogue',
                'error_bubbling' => false,
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mur::class,
        ]);
    }
}
