<?php

declare(strict_types=1);

namespace App\Form;

use App\Import\FormatCsv;
use App\Import\RoleImport;
use App\Import\UniteImport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Unité du fichier (modifiable sans le renvoyer), format du CSV et rôle de chaque calque d'un DXF. Les calques sont
 * les champs `calque_<n>` (le nom du calque est le libellé), dans l'ordre de l'option `calques`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ImportParametresType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $source */
        $source = $options['source'];
        /** @var list<string> $calques */
        $calques = $options['calques'];
        $unites = 'csv' === $source ? UniteImport::pourCsv() : UniteImport::cases();
        $builder->add('unite', ChoiceType::class, [
            'label' => 'Unit of the file',
            'choices' => array_combine(array_map(static fn (UniteImport $u): string => $u->value, $unites), $unites),
            'choice_label' => static fn (UniteImport $u): string => $u->libelle(),
            'choice_value' => static fn (mixed $u): string => $u instanceof UniteImport ? $u->value : '',
            'help' => 'Change the unit if the sizes look wrong: the file is not uploaded again.',
        ]);
        if ('csv' === $source) {
            $builder->add('format', ChoiceType::class, [
                'label' => 'Columns',
                'choices' => ['Detect from the header' => '', 'x ; y (points)' => FormatCsv::Points->value, 'side ; angle' => FormatCsv::CotesAngles->value],
            ]);
        }
        foreach ($calques as $i => $calque) {
            $builder->add('calque_'.$i, ChoiceType::class, [
                'label' => $calque,
                'translation_domain' => false,
                'choices' => array_combine(array_map(static fn (RoleImport $r): string => $r->value, RoleImport::cases()), RoleImport::cases()),
                'choice_label' => static fn (RoleImport $r): string => $r->libelle(),
                'choice_translation_domain' => 'messages',
                'choice_value' => static fn (mixed $r): string => $r instanceof RoleImport ? $r->value : '',
            ]);
        }
        $builder
            ->add('appliquer', SubmitType::class, ['label' => 'Apply', 'attr' => ['class' => 'btn btn-outline-secondary']])
            ->add('continuer', SubmitType::class, ['label' => 'Continue', 'attr' => ['class' => 'btn btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages', 'source' => 'dxf', 'calques' => []]);
        $resolver->setAllowedTypes('calques', 'array');
        $resolver->setAllowedTypes('source', 'string');
    }
}
