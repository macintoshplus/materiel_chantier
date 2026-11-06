<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Electricite\RegistreTypesElements;
use App\Enum\FamilleEquipement;

/**
 * Registre des types d'éléments par famille d'équipement : les écrans d'équipements et de réseaux ne connaissent que
 * cette classe. L'électricité vient de `RegistreTypesElements` ; les autres familles de la configuration
 * (`app.reseaux.types`, famille => code => libellé), donc sans code ni migration pour en ajouter.
 */
final class TypesEquipement
{
    /**
     * @param array<string, array<string, string>> $autres types des familles non électriques
     */
    public function __construct(private readonly RegistreTypesElements $electricite, private readonly array $autres = [])
    {
    }

    /**
     * Libellés (clés de traduction) indexés par code de type.
     *
     * @return array<string, string>
     */
    public function types(FamilleEquipement $famille): array
    {
        if (FamilleEquipement::Electrique === $famille) {
            return array_map(static fn ($t): string => $t->libelle, $this->electricite->tous());
        }

        return $this->autres[$famille->value] ?? [];
    }

    public function possede(FamilleEquipement $famille, string $code): bool
    {
        return isset($this->types($famille)[$code]);
    }

    /**
     * Libellé d'un type (le code lui-même s'il est inconnu).
     */
    public function libelle(FamilleEquipement $famille, string $code): string
    {
        return $this->types($famille)[$code] ?? $code;
    }

    /**
     * Hauteur de pose par défaut (mm) d'un type, 0 quand la famille n'en définit pas.
     */
    public function hauteurDefaut(FamilleEquipement $famille, string $code): int
    {
        return FamilleEquipement::Electrique === $famille && $this->electricite->possede($code) ? $this->electricite->get($code)->hauteurDefautMm : 0;
    }
}
