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
                'constraints'=>[
                    new Range(min: 0)
                ]
            ])
            ->add('hauteur', NumberType::class, [
                'label' => 'Hauteur (m)',
                'scale' => 2,
                'constraints'=>[
                    new Range(min: 0)
                ]
            ])
            ->add('distance_gauche', NumberType::class, [
                'label' => 'Distance depuis le bord gauche (m)',
                'scale' => 2,
                'constraints'=>[
                    new Range(min: 0)
                ]
            ])
            ->add('elevation', NumberType::class, [
                'label' => 'Élévation (m)',
                'scale' => 2,
                'help' => 'Toujours 0 pour une porte',
                'constraints'=>[
                    new Range(min: 0)
                ]
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'error_bubbling' => false,
        ]);
    }
}
