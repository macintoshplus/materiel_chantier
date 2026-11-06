<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Dto\PieceSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Éditeur de pièce (création et édition) : nom, hauteur, sommets du sol.
 *
 * @extends AbstractType<PieceSaisie>
 */
final class PieceSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $aretes identifiants de sommets de début d'arête */
        $aretes = $options['aretes'];
        $choix = [];
        foreach ($aretes as $i => $id) {
            $choix[sprintf('Edge %d', $i + 1)] = $id;
        }
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('hauteurSousPlafond', IntegerType::class, ['label' => 'Default ceiling height (mm)', 'required' => false])
            ->add('sommets', CollectionType::class, [
                'label' => 'Floor vertices (mm)',
                'entry_type' => SommetSaisieType::class,
                'allow_add' => false,
                'allow_delete' => false,
            ])
            ->add('ajoutArete', ChoiceType::class, [
                'label' => 'Add a vertex on the edge',
                'required' => false,
                'placeholder' => 'None',
                'choices' => $choix,
            ])
            ->add('ajoutDistance', IntegerType::class, ['label' => 'Distance from the edge start (mm)', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PieceSaisie::class, 'translation_domain' => 'messages', 'aretes' => []]);
        $resolver->setAllowedTypes('aretes', 'array');
    }
}
