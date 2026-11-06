<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Erreur de validation du domaine : code + paramètres, `champ` désigne le champ fautif (ex. identifiant de sommet).
 */
final class ErreurGeometrique extends \DomainException
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(public readonly string $erreur, public readonly array $params = [], public readonly ?string $champ = null)
    {
        parent::__construct($erreur);
    }

    public function avertissement(): Avertissement
    {
        return new Avertissement($this->erreur, $this->params);
    }
}
