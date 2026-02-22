<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CatalogueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('montant', CatalogueMontantType::class);
        $builder->add('rail', CatalogueRailType::class);
        $builder->add('appuisIntermediaire', CatalogueAppuisIntermediaireType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
