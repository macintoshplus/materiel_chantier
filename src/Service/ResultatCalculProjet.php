<?php

declare(strict_types=1);

namespace App\Service;

use App\Geometrie\Avertissement;

/**
 * Agrégation d'un projet : un groupe d'achats par étendue, plus les avertissements et erreurs de calcul des supports
 * (dédoublonnés) et ceux de la substitution.
 */
final readonly class ResultatCalculProjet
{
    /**
     * @param list<GroupeAchats>  $groupes
     * @param list<Avertissement> $avertissements
     * @param list<Avertissement> $erreurs
     */
    public function __construct(
        public PorteeAgregation $portee,
        public array $groupes,
        public array $avertissements = [],
        public array $erreurs = [],
    ) {
    }
}
