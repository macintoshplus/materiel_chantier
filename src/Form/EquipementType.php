<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Reseau;
use App\Enum\FamilleEquipement;
use App\Form\Dto\EquipementSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Équipement d'une famille quelconque : les types viennent du registre de la famille (option `types`, libellé de
 * traduction indexé par code) et les réseaux proposés sont ceux de la même famille.
 *
 * @extends AbstractType<EquipementSaisie>
 */
final class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $types */
        $types = $options['types'];
        /** @var list<Reseau> $reseaux */
        $reseaux = $options['reseaux'];
        $builder
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => array_flip($types), 'choice_translation_domain' => 'messages', 'placeholder' => 'Choose a type'])
            ->add('nom', TextType::class, ['label' => 'Name', 'required' => false])
            ->add('u', IntegerType::class, ['label' => 'Position u (mm)'])
            ->add('v', IntegerType::class, ['label' => 'Position v (mm)'])
            ->add('reseau', ChoiceType::class, [
                'label' => 'Circuit',
                'required' => false,
                'placeholder' => 'None',
                'choices' => $reseaux,
                'choice_label' => static fn (Reseau $r): string => $r->getNom(),
                'choice_value' => static fn (?Reseau $r): string => null === $r ? '' : (string) $r->getId(),
                'translation_domain' => 'messages',
                'choice_translation_domain' => false,
            ]);
        if (FamilleEquipement::Electrique === $options['famille']) {
            $builder->add('puissanceW', IntegerType::class, ['label' => 'Power (W)', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => EquipementSaisie::class, 'translation_domain' => 'messages', 'reseaux' => []])
            ->setRequired(['famille', 'types'])
            ->setAllowedTypes('famille', FamilleEquipement::class)
            ->setAllowedTypes('types', 'array')
            ->setAllowedTypes('reseaux', 'array');
    }
}
