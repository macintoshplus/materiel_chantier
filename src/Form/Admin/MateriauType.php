<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Catalogue\CategorieMateriau;
use App\Entity\Materiau;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Materiau>
 */
final class MateriauType extends AbstractType
{
    public function __construct(private readonly MateriauFormBuilder $caracteristiques)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categorie = $options['categorie'];
        \assert($categorie instanceof CategorieMateriau);

        $builder
            ->add('reference', TextType::class, ['label' => 'Reference'])
            ->add('libelle', TextType::class, ['label' => 'Label'])
            ->add('uniteVente', ChoiceType::class, ['label' => 'Sales unit', 'choices' => [
                'piece' => 'piece', 'linear meter' => 'ml', 'square meter' => 'm2', 'roll' => 'rouleau', 'box' => 'boite', 'kilogram' => 'kg',
            ]])
            ->add('unitesParLot', IntegerType::class, ['label' => 'Units per lot'])
            ->add('longueur', IntegerType::class, ['label' => 'Length (mm)', 'required' => false])
            ->add('largeur', IntegerType::class, ['label' => 'Width (mm)', 'required' => false])
            ->add('epaisseur', IntegerType::class, ['label' => 'Thickness (mm)', 'required' => false])
            ->add('hauteur', IntegerType::class, ['label' => 'Height (mm)', 'required' => false])
            ->add('poids', NumberType::class, ['label' => 'Weight (kg per unit)', 'required' => false, 'input' => 'string', 'scale' => 3])
            ->add('margeSecuritePct', NumberType::class, ['label' => 'Safety margin (%)', 'required' => false, 'input' => 'string', 'scale' => 2])
            ->add('actif', CheckboxType::class, ['label' => 'Active', 'required' => false]);

        $this->caracteristiques->ajouter($builder, $categorie);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Materiau::class, 'translation_domain' => 'messages']);
        $resolver->setRequired('categorie');
        $resolver->setAllowedTypes('categorie', CategorieMateriau::class);
    }
}
