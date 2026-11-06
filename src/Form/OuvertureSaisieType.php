<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TypePose;
use App\Form\Dto\OuvertureSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<OuvertureSaisie>
 */
final class OuvertureSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('nom', TextType::class, ['label' => 'Name', 'required' => false])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => ['Window' => 'fenetre', 'Door' => 'porte']])
            ->add('largeur', IntegerType::class, ['label' => 'Width (mm)'])
            ->add('hauteur', IntegerType::class, ['label' => 'Height (mm)'])
            ->add('u', IntegerType::class, ['label' => 'Position u (mm)'])
            ->add('v', IntegerType::class, ['label' => 'Position v (mm)'])
            ->add('typePose', EnumType::class, [
                'label' => 'Pose mode',
                'class' => TypePose::class,
                'choice_label' => static fn (TypePose $p): string => 'pose_mode.'.$p->value,
            ])
            ->add('refoncement', IntegerType::class, ['label' => 'Recess depth (mm)', 'required' => false, 'empty_data' => '0']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OuvertureSaisie::class, 'label' => false]);
    }
}
