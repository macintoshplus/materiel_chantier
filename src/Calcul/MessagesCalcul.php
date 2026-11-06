<?php

declare(strict_types=1);

namespace App\Calcul;

/**
 * Table code -> message anglais des erreurs et avertissements du moteur de couches (domaine `validators`,
 * paramètres `{{ nom }}`). La traduction française est dans `translations/validators.fr.xlf`.
 */
final class MessagesCalcul
{
    private const MESSAGES = [
        'system_surface_type_unsupported' => 'The system "{{ system }}" cannot be applied to a surface of type "{{ type }}".',
        'system_parameter_invalid' => 'The parameter "{{ parameter }}" is unknown or has an invalid value.',
        'layer_slot_unknown' => 'The layer {{ order }} uses the unknown slot "{{ slot }}".',
        'layer_role_mismatch' => 'The layer {{ order }} has the role "{{ role }}" but its slot expects "{{ expected }}".',
        'layer_position_not_allowed' => 'The position of the layer {{ order }} is not allowed in the slot "{{ slot }}".',
        'layer_orientation_not_allowed' => 'The orientation {{ orientation }} of the layer {{ order }} is not allowed.',
        'crossed_layer_between_framing' => 'The layer {{ order }} is crossed (90 degrees) and cannot be placed between the framing.',
        'layer_calculator_unknown' => 'The layer {{ order }} needs the unknown calculator "{{ calculator }}".',
        'layer_without_material' => 'The layer {{ order }} has neither a material nor a specification.',
        'layer_cardinality' => 'The slot "{{ slot }}" has {{ count }} layer(s), expected between {{ min }} and {{ max }}.',
        'layer_no_candidate' => 'No catalogue material satisfies the specification of the layer {{ order }} ({{ role }}).',
        'pinned_material_mismatch' => 'The pinned material of the layer {{ order }} ({{ role }}) does not satisfy its specification.',
        'layer_joints_too_close' => 'The joints of this skin are {{ actual }} mm from those of the previous one (minimum {{ minimum }} mm).',
        'partition_faces_missing' => 'The partition needs its two faces to be calculated.',
        'framing_free_end' => 'The {{ end }} end of the partition is free: only the end stud is planned.',
        'framing_height_exceeded' => 'No stud span of the profile ({{ profile }} mm) covers the height of {{ height }} mm.',
        'framing_rail_missing' => 'No catalogue profile of family "{{ family }}" and series {{ series }} found for the perimeter rails.',
        'insulation_deeper_than_framing' => 'The insulation ({{ cavity }} mm) is deeper than the framing profile ({{ profile }} mm).',
        'pose_override_exceeds_limit' => 'The value {{ value }} imposed for "{{ parameter }}" exceeds the limit of {{ limit }} of an article.',
        'pose_parameter_unknown' => 'No default and no article limit for the parameter "{{ parameter }}".',
        'framing_orientation_intermediate' => 'The framing orientation is intermediate ({{ orientation }}): the least favourable span is used.',
        'framing_unreinforced_stud_too_long' => 'A non-reinforced stud of {{ length }} mm exceeds the simple stud maximum of {{ maximum }} mm.',
        'framing_configuration_off_table' => 'The configuration {{ renfort }} at {{ spacing }} mm is not in the manufacturer table.',
        'screw_no_candidate' => 'No catalogue screw fits a skin thickness of {{ thickness }} mm with {{ penetration }} mm of penetration.',
        'ceiling_perimeter_profile_missing' => 'No catalogue profile of family "lisse" found for the ceiling perimeter.',
    ];

    public static function existe(string $code): bool
    {
        return isset(self::MESSAGES[$code]);
    }

    public static function message(string $code): string
    {
        return self::MESSAGES[$code] ?? $code;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::MESSAGES);
    }
}
