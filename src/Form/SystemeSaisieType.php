<?php

declare(strict_types=1);

namespace App\Form;

use App\Calcul\Cadre\ZoneRenfort;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\ParametreSysteme;
use App\Catalogue\Vue\Renfort;
use App\Enum\TypeAncrage;
use App\Form\Dto\SystemeSaisie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Saisie d'un système appliqué : variante, un champ par paramètre du schéma de la définition, et une liste de
 * couches par emplacement (ajout et retrait dans les bornes min..max de l'emplacement).
 *
 * @extends AbstractType<SystemeSaisie>
 */
final class SystemeSaisieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DefinitionSysteme $definition */
        $definition = $options['definition'];
        /** @var array<string, array<string, int>> $materiaux */
        $materiaux = $options['materiaux'];

        $builder->add('variante', TextType::class, ['label' => 'Variant name']);

        $parametres = $builder->create('parametres', FormType::class, ['label' => false]);
        foreach ($definition->parametres() as $parametre) {
            $parametres->add($parametre->nom, ...$this->champ($parametre));
        }
        $builder->add($parametres);

        $couches = $builder->create('couches', FormType::class, ['label' => false]);
        foreach ($definition->emplacements() as $emplacement) {
            $couches->add($emplacement->code, CollectionType::class, [
                'label' => 'layer_slot.'.$emplacement->code,
                'entry_type' => CoucheSaisieType::class,
                'entry_options' => ['emplacement' => $emplacement, 'materiaux' => $materiaux[$emplacement->code] ?? [], 'cloison' => $options['cloison']],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'constraints' => [new Count(min: max(0, $emplacement->min), max: max(0, $emplacement->max))],
            ]);
        }
        $builder->add($couches);
    }

    /**
     * @return array{0: class-string<FormTypeInterface<mixed>>, 1: array<string, mixed>}
     */
    private function champ(ParametreSysteme $parametre): array
    {
        $label = 'system_parameter.'.$parametre->nom;
        $aide = null === $parametre->defaut || '' === $parametre->defaut ? 'Empty: engine default' : 'Empty: default value ({defaut})';
        $options = ['label' => $label, 'required' => false, 'help' => $aide, 'help_translation_parameters' => ['defaut' => \is_bool($parametre->defaut) ? ($parametre->defaut ? 'true' : 'false') : (string) $parametre->defaut]];
        $choix = $this->choix($parametre->nom);
        if (null !== $choix) {
            return [ChoiceType::class, $options + ['choices' => $choix, 'placeholder' => 'Default']];
        }

        return match ($parametre->type) {
            'int' => [IntegerType::class, $options],
            'bool' => [CheckboxType::class, $options],
            default => [TextType::class, $options],
        };
    }

    /**
     * @return array<string, string>|null
     */
    private function choix(string $nom): ?array
    {
        $valeurs = match ($nom) {
            'type_profil_peripherique' => ['rail', 'lisse'],
            'renfort' => array_map(static fn (Renfort $r): string => $r->value, Renfort::cases()),
            'renfort_zone' => array_map(static fn (ZoneRenfort $z): string => $z->value, ZoneRenfort::cases()),
            'extremite_debut', 'extremite_fin' => array_map(static fn (TypeAncrage $t): string => $t->value, TypeAncrage::cases()),
            default => null,
        };
        if (null === $valeurs) {
            return null;
        }

        return array_combine(array_map(static fn (string $v): string => 'system_choice.'.$nom.'.'.$v, $valeurs), $valeurs);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SystemeSaisie::class, 'translation_domain' => 'messages', 'cloison' => false]);
        $resolver->setRequired(['definition', 'materiaux']);
        $resolver->setAllowedTypes('definition', DefinitionSysteme::class);
        $resolver->setAllowedTypes('materiaux', 'array');
        $resolver->setAllowedTypes('cloison', 'bool');
    }
}
