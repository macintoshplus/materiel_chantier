<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Dto\CloisonSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CloisonSaisie>
 */
final class CloisonSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $murs */
        $murs = $options['murs'];
        /** @var array<string, string> $ancrages */
        $ancrages = $options['ancrages'];
        $builder
            ->add('nom', TextType::class, ['label' => 'Name', 'required' => false])
            ->add('debutX', IntegerType::class, ['label' => 'Start X (mm)', 'required' => false])
            ->add('debutY', IntegerType::class, ['label' => 'Start Y (mm)', 'required' => false])
            ->add('finX', IntegerType::class, ['label' => 'End X (mm)', 'required' => false])
            ->add('finY', IntegerType::class, ['label' => 'End Y (mm)', 'required' => false])
            ->add('longueur', IntegerType::class, ['label' => 'Length (mm)', 'required' => false])
            ->add('angle', NumberType::class, ['label' => 'Angle (deg)', 'required' => false, 'scale' => 1, 'html5' => false])
            ->add('murReference', ChoiceType::class, ['label' => 'Reference wall', 'required' => false, 'placeholder' => 'None', 'choices' => $murs])
            ->add('decalage', IntegerType::class, ['label' => 'Offset from the reference wall (mm)', 'required' => false])
            ->add('ancrageDebut', ChoiceType::class, ['label' => 'Start anchor', 'choices' => $ancrages])
            ->add('ancrageFin', ChoiceType::class, ['label' => 'End anchor', 'choices' => $ancrages])
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
        $resolver->setDefaults(['data_class' => CloisonSaisie::class, 'translation_domain' => 'messages', 'murs' => [], 'ancrages' => []]);
        $resolver->setAllowedTypes('murs', 'array');
        $resolver->setAllowedTypes('ancrages', 'array');
    }
}
