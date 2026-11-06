<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Equipement;
use App\Enum\TypeCircuit;
use App\Form\Dto\ReseauSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réseau (circuit) : options `equipements` (équipements du projet dans la famille du réseau), `libelle` (étiquette d'un
 * équipement) et `sections` (sections proposées).
 *
 * @extends AbstractType<ReseauSaisie>
 */
final class ReseauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $sections */
        $sections = $options['sections'];
        $choix = [
            'choices' => $options['equipements'],
            'choice_label' => $options['libelle'],
            'choice_value' => static fn (?Equipement $e): string => null === $e ? '' : (string) $e->getId(),
            'choice_translation_domain' => false,
        ];
        $builder
            ->add('nom', TextType::class, ['label' => 'Name', 'empty_data' => ''])
            ->add('type', EnumType::class, [
                'label' => 'Circuit type',
                'class' => TypeCircuit::class,
                'choice_label' => static fn (TypeCircuit $t): string => 'circuit_type.'.$t->value,
            ])
            ->add('section', ChoiceType::class, ['label' => 'Conductor section (mm2)', 'choices' => array_combine($sections, $sections), 'choice_translation_domain' => false])
            ->add('protection', IntegerType::class, ['label' => 'Protection (A)'])
            ->add('depart', ChoiceType::class, ['label' => 'Starting point (distribution board)', 'required' => false, 'placeholder' => 'None'] + $choix)
            ->add('equipements', ChoiceType::class, ['label' => 'Elements of the circuit', 'required' => false, 'multiple' => true, 'expanded' => true] + $choix)
            ->add('reservePct', IntegerType::class, ['label' => 'Cable reserve (%, empty = project value)', 'required' => false])
            ->add('liaisons', CollectionType::class, [
                'entry_type' => LiaisonSaisieType::class,
                'entry_options' => ['equipements' => $options['equipements'], 'libelle' => $options['libelle']],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'label' => 'Links',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => ReseauSaisie::class, 'translation_domain' => 'messages', 'equipements' => [], 'libelle' => null, 'sections' => ['1.5', '2.5', '4', '6', '10', '16']])
            ->setAllowedTypes('equipements', 'array')
            ->setAllowedTypes('sections', 'array');
    }
}
