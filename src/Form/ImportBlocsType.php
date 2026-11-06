<?php

declare(strict_types=1);

namespace App\Form;

use App\Import\RoleImport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Rôle de chaque bloc détecté (porte, fenêtre, équipement ou ignoré) : champs `bloc_<index>`, libellé = nom du bloc.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ImportBlocsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, string> $blocs */
        $blocs = $options['blocs'];
        foreach ($blocs as $index => $libelle) {
            $builder->add('bloc_'.$index, ChoiceType::class, [
                'label' => $libelle,
                'translation_domain' => false,
                'choices' => array_combine(array_map(static fn (RoleImport $r): string => $r->value, RoleImport::pourBloc()), RoleImport::pourBloc()),
                'choice_label' => static fn (RoleImport $r): string => $r->libelle(),
                'choice_translation_domain' => 'messages',
                'choice_value' => static fn (mixed $r): string => $r instanceof RoleImport ? $r->value : '',
            ]);
        }
        $builder
            ->add('appliquer', SubmitType::class, ['label' => 'Update the preview', 'attr' => ['class' => 'btn btn-outline-secondary']])
            ->add('creer', SubmitType::class, ['label' => 'Create the room', 'attr' => ['class' => 'btn btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages', 'blocs' => []]);
        $resolver->setAllowedTypes('blocs', 'array');
    }
}
