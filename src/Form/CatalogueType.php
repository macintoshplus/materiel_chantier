<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Catalogue\CatalogueAppuisIntermediaireType;
use App\Form\Catalogue\CatalogueBandeAJoinArmeeType;
use App\Form\Catalogue\CatalogueBandeAJoinPlatType;
use App\Form\Catalogue\CatalogueMontantType;
use App\Form\Catalogue\CataloguePlaquePlatreType;
use App\Form\Catalogue\CatalogueRailType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
class CatalogueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('montant', CatalogueMontantType::class)
            ->add('rail', CatalogueRailType::class)
            ->add('appuisIntermediaire', CatalogueAppuisIntermediaireType::class)
            ->add('bandeAJoinPlat', CatalogueBandeAJoinPlatType::class)
            ->add('bandeAJoinArmee', CatalogueBandeAJoinArmeeType::class)
            ->add('plaquePlatre', CataloguePlaquePlatreType::class)
        ;


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
