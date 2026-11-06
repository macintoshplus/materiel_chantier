<?php

declare(strict_types=1);

namespace App\Calcul\Pipeline;

use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Geometrie\Avertissement;

/**
 * Première étape du pipeline : cohérence des couches avec la définition du système. Les erreurs sont bloquantes.
 */
final readonly class ValidateurSysteme
{
    public function __construct(private RegistreCalculateurs $calculateurs)
    {
    }

    /**
     * @param list<CoucheDecrite>        $couches
     * @param array<string, mixed>       $parametres
     *
     * @return list<Avertissement>
     */
    public function valider(DefinitionSysteme $definition, SurfaceCalculable $surface, array $couches, array $parametres): array
    {
        $erreurs = [];

        if (!\in_array($surface->type, $definition->typesSurface(), true)) {
            $erreurs[] = new Avertissement('system_surface_type_unsupported', ['system' => $definition->code(), 'type' => $surface->type->value]);
        }

        foreach ($parametres as $nom => $valeur) {
            $schema = null;
            foreach ($definition->parametres() as $p) {
                if ($p->nom === $nom) {
                    $schema = $p;
                }
            }
            if (null === $schema || !$schema->accepte($valeur)) {
                $erreurs[] = new Avertissement('system_parameter_invalid', ['parameter' => $nom]);
            }
        }

        $emplacements = [];
        foreach ($definition->emplacements() as $e) {
            $emplacements[$e->code] = $e;
        }

        $nombre = [];
        foreach ($couches as $couche) {
            $emplacement = $emplacements[$couche->emplacement] ?? null;
            if (null === $emplacement) {
                $erreurs[] = new Avertissement('layer_slot_unknown', ['order' => $couche->ordre, 'slot' => $couche->emplacement]);

                continue;
            }
            $nombre[$emplacement->code] = ($nombre[$emplacement->code] ?? 0) + 1;

            if ($couche->role !== $emplacement->role) {
                $erreurs[] = new Avertissement('layer_role_mismatch', ['order' => $couche->ordre, 'role' => $couche->role, 'expected' => $emplacement->role]);
            }
            if (!$emplacement->autorisePosition($couche->position)) {
                $erreurs[] = new Avertissement('layer_position_not_allowed', ['order' => $couche->ordre, 'slot' => $emplacement->code]);
            }
            if (!\in_array($couche->orientation, $emplacement->orientations, true)) {
                $erreurs[] = new Avertissement('layer_orientation_not_allowed', ['order' => $couche->ordre, 'orientation' => $couche->orientation]);
            }
            if (90 === $couche->orientation % 180 && PositionCouche::EntreOssature === $couche->position) {
                $erreurs[] = new Avertissement('crossed_layer_between_framing', ['order' => $couche->ordre]);
            }
            if (!$this->calculateurs->possede($emplacement->calculateur)) {
                $erreurs[] = new Avertissement('layer_calculator_unknown', ['order' => $couche->ordre, 'calculator' => $emplacement->calculateur]);
            }
            if (null === $couche->materiau && null === $couche->specification) {
                $erreurs[] = new Avertissement('layer_without_material', ['order' => $couche->ordre]);
            }
        }

        foreach ($emplacements as $code => $emplacement) {
            $n = $nombre[$code] ?? 0;
            if ($n < $emplacement->min || $n > $emplacement->max) {
                $erreurs[] = new Avertissement('layer_cardinality', ['slot' => $code, 'count' => $n, 'min' => $emplacement->min, 'max' => $emplacement->max]);
            }
        }

        return $erreurs;
    }
}
