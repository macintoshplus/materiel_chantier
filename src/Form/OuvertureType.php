<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:23
 */

declare(strict_types=1);


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<array<string, mixed>> */
class OuvertureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Fenêtre' => 'fenetre',
                    'Porte' => 'porte',
                ],
            ])
            ->add('largeur', NumberType::class, [
                'label' => 'Largeur (m)',
                'scale' => 2,
                'constraints' => [
                    new Range(min: 0)
                ]
            ])
            ->add('hauteur', NumberType::class, [
                'label' => 'Hauteur (m)',
                'scale' => 2,
                'constraints' => [
                    new Range(min: 0)
                ]
            ])
            ->add('distance_gauche', NumberType::class, [
                'label' => 'Distance depuis le bord gauche (m)',
                'scale' => 2,
                'constraints' => [
                    new Range(min: 0)
                ]
            ])
            ->add('elevation', NumberType::class, [
                'label' => 'Élévation (m)',
                'scale' => 2,
                'help' => 'Toujours 0 pour une porte',
                'constraints' => [
                    new Range(min: 0)
                ]
            ])
            ->add('type_pose', ChoiceType::class, [
                'choices' => [
                    'Applique' => 'applique',
                    'Tunel' => 'tunel',
                ],
                'help' => 'La pose en applique ne demande aucun retour pour le type de contour. un ouvrant posé sur le bord extérieur du mur est considéré en "tunel".'
            ])
            ->add('type_contour', ChoiceType::class, [
                'choices' => [
                    'Aucun retour' => 'aucun',
                    'Retour en plaque de platre' => 'plaque-platre',
                    'Retour en autre matière sans joint' => 'autre',
                ]
            ])
            ->add('refoncement_ouvrant', NumberType::class, [
                'label' => 'Renfoncement (m)',
                'scale' => 2,
                'help' => 'Pour une pose en applique, mettre 0. Sinon, c\'est la distance entre la fenêtre et le bord du mur.',
                'constraints' => [
                    new Range(min: 0)
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'error_bubbling' => false,
        ]);
    }
}
