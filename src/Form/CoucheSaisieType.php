<?php

declare(strict_types=1);

namespace App\Form;

use App\Calcul\Couche\PositionCouche;
use App\Calcul\Systeme\EmplacementCouche;
use App\Enum\CoteCloison;
use App\Form\Dto\CoucheSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une couche d'un emplacement : article (liste filtrée, ou non épinglé), puis seulement les propriétés que
 * l'emplacement autorise (orientation et position s'il y a un choix, décalage d'un parement, face d'une cloison).
 *
 * @extends AbstractType<CoucheSaisie>
 */
final class CoucheSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var EmplacementCouche $emplacement */
        $emplacement = $options['emplacement'];
        /** @var array<string, int> $materiaux */
        $materiaux = $options['materiaux'];

        $builder->add('materiau', ChoiceType::class, [
            'label' => 'Material',
            'choices' => $materiaux,
            'placeholder' => 'Not pinned (chosen from the specification)',
            'required' => false,
        ]);
        if (\count($emplacement->orientations) > 1) {
            $builder->add('orientation', ChoiceType::class, [
                'label' => 'Orientation (degrees)',
                'choices' => array_combine(array_map(strval(...), $emplacement->orientations), $emplacement->orientations),
                'placeholder' => false,
                'required' => true,
            ]);
        }
        if (\count($emplacement->positions) > 1) {
            $builder->add('position', ChoiceType::class, [
                'label' => 'Position',
                'choices' => array_combine(
                    array_map(static fn (PositionCouche $p): string => 'layer_position.'.$p->value, $emplacement->positions),
                    array_map(static fn (PositionCouche $p): string => $p->value, $emplacement->positions),
                ),
                'placeholder' => false,
                'required' => true,
            ]);
        }
        if (\in_array(PositionCouche::SurOssature, $emplacement->positions, true)) {
            $builder->add('decalage', IntegerType::class, ['label' => 'Offset along u (mm)', 'required' => false]);
        }
        if ($options['cloison']) {
            $builder->add('face', ChoiceType::class, [
                'label' => 'Face',
                'choices' => ['Side A' => CoteCloison::A->value, 'Side B' => CoteCloison::B->value],
                'placeholder' => 'Both faces (common)',
                'required' => false,
            ]);
        }
        $builder->add('epaisseurOverride', IntegerType::class, ['label' => 'Thickness override (mm)', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CoucheSaisie::class, 'label' => false, 'cloison' => false]);
        $resolver->setRequired(['emplacement', 'materiaux']);
        $resolver->setAllowedTypes('emplacement', EmplacementCouche::class);
        $resolver->setAllowedTypes('materiaux', 'array');
        $resolver->setAllowedTypes('cloison', 'bool');
    }
}
