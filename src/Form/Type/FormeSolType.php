<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Geometrie\FormeSol;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie du polygone au sol : un sommet « x;y » (mètres) par ligne, stocké en mm entiers anti-horaire.
 *
 * @extends AbstractType<list<array{x: int, y: int}>>
 */
final class FormeSolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            self::versVue(...),
            static function (mixed $texte): array {
                try {
                    return FormeSol::depuisTexte(\is_string($texte) ? $texte : '');
                } catch (\InvalidArgumentException $e) {
                    $erreur = new TransformationFailedException($e->getMessage());
                    $erreur->setInvalidMessage($e->getMessage());
                    throw $erreur;
                }
            },
        ));
    }

    /**
     * @param list<array{x: int, y: int}>|null $points
     */
    private static function versVue(?array $points): string
    {
        return null !== $points ? FormeSol::versTexte($points) : '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['rows' => 6, 'placeholder' => "0;0\n4,5;0\n4,5;3\n0;3"],
            'invalid_message' => 'The floor outline is not valid.',
        ]);
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
