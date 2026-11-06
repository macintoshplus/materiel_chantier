<?php

declare(strict_types=1);

namespace App\Form\Projet;

use App\Entity\Projet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Projet>
 */
final class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Name'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('adresseChantier', TextType::class, ['label' => 'Site address', 'required' => false])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'input' => 'string', 'scale' => 7])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'input' => 'string', 'scale' => 7]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Projet::class, 'translation_domain' => 'messages']);
    }
}
