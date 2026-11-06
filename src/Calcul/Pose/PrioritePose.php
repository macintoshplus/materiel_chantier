<?php

declare(strict_types=1);

namespace App\Calcul\Pose;

use App\Geometrie\Avertissement;

/**
 * Priorité des paramètres de pose (valeurs entières, mm) :
 * 1. surcharge de l'utilisateur, acceptée avec un avertissement si elle dépasse une limite d'article ;
 * 2. sinon `min(défaut du système, limites des articles)` : une limite plus large que le défaut ne le remplace jamais ;
 * 3. sans défaut : le minimum des limites ; sans aucune donnée : erreur explicite.
 */
final class PrioritePose
{
    /**
     * @param list<int> $limites limites des articles (la plus contraignante gagne)
     */
    public static function resoudre(string $nom, ?int $surcharge, ?int $defaut, array $limites): ValeurPose
    {
        $limite = [] === $limites ? null : min($limites);
        if (null !== $surcharge) {
            $avertissements = [];
            if (null !== $limite && $surcharge > $limite) {
                $avertissements[] = new Avertissement('pose_override_exceeds_limit', ['parameter' => $nom, 'value' => $surcharge, 'limit' => $limite]);
            }

            return new ValeurPose($nom, $surcharge, SourceValeur::Utilisateur, $avertissements);
        }
        if (null !== $defaut) {
            if (null !== $limite && $limite < $defaut) {
                return new ValeurPose($nom, $limite, SourceValeur::Article);
            }

            return new ValeurPose($nom, $defaut, SourceValeur::Systeme);
        }
        if (null !== $limite) {
            return new ValeurPose($nom, $limite, SourceValeur::Article);
        }

        return new ValeurPose($nom, null, null, [], new Avertissement('pose_parameter_unknown', ['parameter' => $nom]));
    }
}
