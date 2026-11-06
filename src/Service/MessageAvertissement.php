<?php

declare(strict_types=1);

namespace App\Service;

use App\Geometrie\Avertissement;
use App\Geometrie\MessagesGeometrie;
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

        return new TranslatableMessage(MessagesGeometrie::message($avertissement->code), $parametres, 'validators');
    }
}
