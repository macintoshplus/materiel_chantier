<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Table unique code -> message anglais (domaine de traduction `validators`, paramètres `{{ nom }}`).
 * Les erreurs et avertissements de la bibliothèque ne portent qu'un code et des paramètres ; la traduction
 * française est dans `translations/validators.fr.xlf`.
 */
final class MessagesGeometrie
{
    private const MESSAGES = [
        'polygon_not_simple' => 'The outline crosses itself or has no area.',
        'constraint_duplicate_vertex' => 'Vertex "{{ vertex }}" is defined by more than one constraint.',
        'constraint_unknown_reference' => 'Vertex "{{ vertex }}" refers to the unknown vertex "{{ reference }}".',
        'constraint_invalid_value' => 'The constraint of vertex "{{ vertex }}" has an invalid value.',
        'constraint_inconsistent' => 'The distance between "{{ from }}" and "{{ to }}" is {{ actual }} mm instead of {{ expected }} mm.',
        'constraint_too_few_vertices' => 'A surface needs at least 3 vertices.',
        'constraint_degenerate' => 'The constraints give an outline that crosses itself or has no area.',
        'constraint_malformed' => 'The constraint of vertex "{{ vertex }}" is malformed.',
        'ceiling_unsupported_floor' => 'A double-slope ceiling needs a rectangular floor aligned with the axes.',
        'ceiling_invalid_heights' => 'The ridge must be higher than the eaves, and both must be positive.',
        'junction_length_mismatch' => 'The joined edges of "{{ a }}" and "{{ b }}" have different lengths ({{ lengthA }} mm and {{ lengthB }} mm).',
        'opening_outside_surface' => 'The opening "{{ opening }}" is outside the surface.',
        'opening_overlap' => 'The openings "{{ opening }}" and "{{ other }}" overlap.',
        'opening_too_close_to_edge' => 'The opening "{{ opening }}" is {{ distance }} mm from an edge (minimum {{ minimum }} mm).',
        'opening_straddles_split' => 'The opening "{{ opening }}" straddles the new vertex.',
        'partition_degenerate' => 'The partition has no length or no thickness.',
        'partition_no_ceiling' => 'There is no ceiling above the partition.',
        'partition_end_outside_room' => 'An end of the partition is outside the room.',
        'partition_anchor_changed' => 'The anchoring of the partition "{{ partition }}" ({{ end }} end) was recomputed.',
        'partition_anchor_lost' => 'The partition "{{ partition }}" is no longer anchored at its {{ end }} end: it is now free.',
        'partition_anchor_released' => 'The partition "{{ partition }}" was anchored on the deleted partition: it is now free.',
        'partition_invalid_after_change' => 'The partition "{{ partition }}" is no longer valid after the change.',
        'vertex_min_count' => 'A floor needs at least 3 vertices.',
        'vertex_unknown' => 'This vertex does not exist.',
        'split_position_invalid' => 'The new vertex must lie inside the edge, away from its ends.',
        'floor_orientation_changed' => 'The new floor outline crosses itself or is clockwise.',
        'ceiling_not_updated' => 'The ceiling panes are not updated: check them.',
        'wall_shape_unchanged' => 'Only the width of the wall "{{ wall }}" was updated: check its other constraints.',
        'merge_names_differ' => 'The walls "{{ first }}" and "{{ second }}" have different names: the first name is kept.',
        'opening_moved' => 'The opening "{{ opening }}" was moved to the merged wall.',
        'partition_anchor_affected' => 'The partition "{{ partition }}" is anchored on the modified element.',
        'edit_one_vertex_at_a_time' => 'Change only one vertex at a time: move, add or remove one vertex, then save again.',
        'anchor_target_unknown' => 'The chosen anchoring target does not exist.',
        'partition_input_missing' => 'Give the end point, or the length and the angle.',
        'partition_released_by_deletion' => 'The partition "{{ partition }}" was freed.',
    ];

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
