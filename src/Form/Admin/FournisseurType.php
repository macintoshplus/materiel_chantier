<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Fournisseur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Fournisseur>
 */
final class FournisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('adresseLigne1', TextType::class, ['label' => 'Address line 1', 'required' => false])
            ->add('adresseLigne2', TextType::class, ['label' => 'Address line 2', 'required' => false])
            ->add('codePostal', TextType::class, ['label' => 'Postal code', 'required' => false])
            ->add('ville', TextType::class, ['label' => 'City', 'required' => false])
            ->add('pays', TextType::class, ['label' => 'Country', 'required' => false])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'input' => 'string', 'scale' => 7])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'input' => 'string', 'scale' => 7])
            ->add('siteWeb', TextType::class, ['label' => 'Website', 'required' => false])
            ->add('telephone', TextType::class, ['label' => 'Phone', 'required' => false])
            ->add('fraisLivraisonTtc', NumberType::class, ['label' => 'Delivery fee (incl. VAT)', 'required' => false, 'input' => 'string', 'scale' => 2])
            ->add('actif', CheckboxType::class, ['label' => 'Active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Fournisseur::class, 'translation_domain' => 'messages']);
    }
}
