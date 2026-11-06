<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Translation\TranslatableMessage;

final class RapportImport
{
    public int $articlesCrees = 0;
    public int $articlesExistants = 0;
    public int $prixDefinis = 0;
    public int $prixInchanges = 0;

    /** @var list<TranslatableMessage> */
    public array $erreurs = [];

    public function erreur(int $ligne, string $message, string $detail = ''): void
    {
        $this->erreurs[] = new TranslatableMessage('Line {line}: ' . $message, ['{line}' => $ligne, '{detail}' => $detail]);
    }
}
