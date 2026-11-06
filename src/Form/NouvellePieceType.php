<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Dto\NouvellePiece;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<NouvellePiece>
 */
final class NouvellePieceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('hauteurSousPlafond', IntegerType::class, ['label' => 'Ceiling height (mm)'])
            ->add('mode', ChoiceType::class, [
                'label' => 'Input mode',
                'expanded' => true,
                'choices' => ['Quick rectangle' => NouvellePiece::RECTANGLE, 'Vertex table' => NouvellePiece::SOMMETS],
            ])
            ->add('largeur', IntegerType::class, ['label' => 'Width (mm)', 'required' => false])
            ->add('longueur', IntegerType::class, ['label' => 'Length (mm)', 'required' => false])
            ->add('formeSol', CollectionType::class, [
                'label' => 'Floor vertices (counter-clockwise, mm)',
                'entry_type' => PointSolType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NouvellePiece::class, 'translation_domain' => 'messages']);
    }
}
