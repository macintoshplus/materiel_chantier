<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie en mètres d'une valeur stockée en millimètres entiers (conversion m <-> mm à la frontière du formulaire).
 *
 * @extends AbstractType<int|null>
 */
final class MetreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $mm): ?float => \is_int($mm) || \is_float($mm) ? $mm / 1000 : null,
            static fn (mixed $m): ?int => \is_int($m) || \is_float($m) ? (int) round($m * 1000) : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['scale' => 3]);
    }

    public function getParent(): string
    {
        return NumberType::class;
    }
}
