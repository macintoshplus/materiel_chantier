<?php

declare(strict_types=1);

namespace App\Form;

use App\Import\ModeContour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contour retenu (parmi les contours sélectionnables), mode de lecture (nu intérieur, axe ou nu extérieur des murs),
 * épaisseur des murs, hauteur sous plafond et nom de la pièce.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ImportContourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, int> $contours */
        $contours = $options['contours'];
        $builder
            ->add('contour', ChoiceType::class, [
                'label' => 'Outline',
                'expanded' => true,
                'choices' => $contours,
                'choice_translation_domain' => false,
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'The outline follows',
                'choices' => [
                    'the inside face of the walls' => ModeContour::Interieur->value,
                    'the wall axis' => ModeContour::Axe->value,
                    'the outside face of the walls' => ModeContour::Exterieur->value,
                ],
                'help' => 'The room is created inside: the outline is moved inward by half the thickness (axis) or the whole thickness (outside face).',
            ])
            ->add('epaisseur', IntegerType::class, ['label' => 'Wall thickness (mm)', 'constraints' => [new Assert\PositiveOrZero()]])
            ->add('hauteur', IntegerType::class, ['label' => 'Ceiling height (mm)', 'constraints' => [new Assert\Positive()]])
            ->add('nom', TextType::class, ['label' => 'Name', 'required' => false, 'constraints' => [new Assert\Length(max: 255)]])
            ->add('appliquer', SubmitType::class, ['label' => 'Apply', 'attr' => ['class' => 'btn btn-outline-secondary']])
            ->add('continuer', SubmitType::class, ['label' => 'Continue', 'attr' => ['class' => 'btn btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages', 'contours' => []]);
        $resolver->setAllowedTypes('contours', 'array');
    }
}
