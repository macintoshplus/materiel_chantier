<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Table code -> message anglais des erreurs et avertissements d'import (domaine de traduction `validators`,
 * paramètres `{{ nom }}`). La traduction française est dans `translations/validators.fr.xlf`.
 */
final class MessagesImport
{
    private const MESSAGES = [
        'import_file_too_large' => 'The file is too large ({{ size }} MB, maximum {{ max }} MB).',
        'import_file_unreadable' => 'The file could not be read.',
        'import_upload_limit' => 'The file exceeds the upload limit of the server ({{ max }}).',
        'import_file_missing' => 'Choose a file to upload.',
        'import_file_extension' => 'The file must have the extension .{{ extension }}.',
        'import_dxf_binary' => 'This is a binary DXF file, which is not supported. Export the plan again as an ASCII (text) DXF file.',
        'import_dxf_corrupt' => 'The DXF file is corrupted or incomplete (line {{ line }}).',
        'import_dxf_too_many_entities' => 'The DXF file contains too many entities (maximum {{ max }}).',
        'import_curved_walls' => 'Curved walls are not supported (outline on layer "{{ layer }}").',
        'import_curve_ignored' => '{{ count }} {{ type }} entity(ies) on layer "{{ layer }}" are not part of an outline: ignored.',
        'import_open_chain' => 'A chain of {{ count }} line(s) on layer "{{ layer }}" does not close: ignored.',
        'import_chain_gap' => 'The outline on layer "{{ layer }}" was closed over a gap of up to {{ gap }} mm.',
        'import_unit_guessed' => 'The drawing does not state its unit: {{ unit }} was assumed. Check it.',
        'import_ignored_entities' => '{{ count }} {{ type }} entity(ies) ignored.',
        'import_no_contour' => 'No usable closed outline was found. Check the unit and the layers.',
        'import_contour_unknown' => 'The chosen outline does not exist.',
        'import_offset_invalid' => 'Moving the outline inward by the wall thickness gives an invalid outline.',
        'import_height_invalid' => 'The ceiling height must be positive.',
        'import_block_not_on_wall' => 'The {{ role }} block "{{ block }}" is not on a wall: no opening created.',
        'import_block_too_wide' => 'The {{ role }} block "{{ block }}" is wider than its wall: no opening created.',
        'import_equipment_kept' => 'Equipment "{{ block }}" (layer "{{ layer }}") kept in the report only.',
        'import_opening_created' => '{{ count }} opening(s) created from blocks.',
        'import_csv_invalid_lines' => 'Invalid CSV lines: {{ lines }}. Nothing was imported.',
        'import_csv_empty' => 'The CSV file contains no data.',
        'import_closure_gap' => 'The outline does not close: gap of {{ gap }} mm (automatic closing up to {{ threshold }} mm).',
        'import_closure_adjusted' => 'The closure gap of {{ gap }} mm was spread over the sides.',
        'import_side_invalid' => 'Row {{ number }}: the side must be positive and the angle between 1 and 359 degrees.',
        'import_session_expired' => 'The import session has expired: upload the file again.',
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
