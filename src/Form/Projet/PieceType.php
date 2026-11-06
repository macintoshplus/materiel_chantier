<?php

declare(strict_types=1);

namespace App\Form\Projet;

use App\Entity\Piece;
use App\Enum\SourceMesures;
use App\Form\Type\FormeSolType;
use App\Form\Type\MetreType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Piece>
 */
final class PieceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('hauteurSousPlafond', MetreType::class, ['label' => 'Ceiling height (m)', 'required' => false])
            ->add('formeSol', FormeSolType::class, ['label' => 'Floor outline (x;y in m, one vertex per line)', 'required' => false])
            ->add('sourceMesures', EnumType::class, [
                'label' => 'Measurement source',
                'class' => SourceMesures::class,
                'choice_label' => static fn (SourceMesures $s): string => 'measurement_source.'.$s->value,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Piece::class, 'translation_domain' => 'messages']);
    }
}
