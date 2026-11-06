<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\MessagesCalcul;
use App\Geometrie\Avertissement;
use App\Geometrie\MessagesGeometrie;
use App\Import\MessagesImport;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Transforme un avertissement ou une erreur de la bibliothèque géométrique (code + paramètres) en message traduisible
 * (domaine `validators`, clé anglaise, paramètres `{{ nom }}`).
 */
final class MessageAvertissement
{
    public static function depuis(Avertissement $avertissement): TranslatableMessage
    {
        $parametres = [];
        foreach ($avertissement->params as $nom => $valeur) {
            $parametres['{{ '.$nom.' }}'] = (string) $valeur;
        }

        $message = match (true) {
            MessagesImport::existe($avertissement->code) => MessagesImport::message($avertissement->code),
            MessagesCalcul::existe($avertissement->code) => MessagesCalcul::message($avertissement->code),
            default => MessagesGeometrie::message($avertissement->code),
        };

        return new TranslatableMessage(
            $message,
            $parametres,
            'validators',
        );
    }
}
