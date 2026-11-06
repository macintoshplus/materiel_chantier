<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Fournisseur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<NouveauPrix>
 */
final class NouveauPrixType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fournisseur', EntityType::class, ['class' => Fournisseur::class, 'choice_label' => 'nom', 'label' => 'Supplier'])
            ->add('prixLotTtc', NumberType::class, ['label' => 'Lot price (incl. VAT)', 'input' => 'string', 'scale' => 2])
            ->add('unitesParLot', IntegerType::class, ['label' => 'Units per lot (blank: article default)', 'required' => false])
            ->add('referenceFournisseur', TextType::class, ['label' => 'Supplier reference', 'required' => false])
            ->add('dateDebut', DateTimeType::class, ['label' => 'Start date', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NouveauPrix::class, 'translation_domain' => 'messages']);
    }
}
