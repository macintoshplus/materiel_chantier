<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Enum\TypeAncrage;
use App\Geometrie\Avertissement;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime une cloison (faces, ouvertures et système par cascade) et libère les cloisons
 * ancrées sur elle : leur ancrage devient `libre`.
 */
final readonly class SuppressionCloison
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return list<Cloison> cloisons dont un ancrage est devenu libre (à signaler à l'utilisateur)
     */
    public function supprimer(Cloison $cloison): array
    {
        $id = $cloison->getId();
        $piece = $cloison->getPiece();
        $liberees = [];
        foreach ($piece->getCloisons() as $autre) {
            if ($autre === $cloison) {
                continue;
            }
            $change = false;
            if ($this->estAncreeSur($autre->getAncrageDebut(), $id)) {
                $autre->setAncrageDebut(self::libre());
                $change = true;
            }
            if ($this->estAncreeSur($autre->getAncrageFin(), $id)) {
                $autre->setAncrageFin(self::libre());
                $change = true;
            }
            if ($change) {
                $liberees[] = $autre;
            }
        }
        $piece->getCloisons()->removeElement($cloison);
        $this->em->remove($cloison);
        $this->em->flush();

        return $liberees;
    }

    /**
     * Supprime la cloison et renvoie un avertissement typé par cloison devenue libre.
     *
     * @return list<Avertissement>
     */
    public function supprimerAvecAvertissements(Cloison $cloison): array
    {
        $piece = $cloison->getPiece();
        foreach ($piece->getJonctions()->toArray() as $jonction) {
            if ($jonction->getSurfaceA()->getCloison() === $cloison || $jonction->getSurfaceB()?->getCloison() === $cloison) {
                $piece->getJonctions()->removeElement($jonction);
            }
        }

        return array_map(
            static fn (Cloison $autre): Avertissement => new Avertissement('partition_anchor_released', ['partition' => $autre->getNom()]),
            $this->supprimer($cloison),
        );
    }

    /**
     * @param array{type: string, cible: int|null, position: int|null} $ancrage
     */
    private function estAncreeSur(array $ancrage, ?int $id): bool
    {
        return null !== $id && TypeAncrage::Cloison->value === $ancrage['type'] && $id === $ancrage['cible'];
    }

    /**
     * @return array{type: string, cible: int|null, position: int|null}
     */
    private static function libre(): array
    {
        return ['type' => TypeAncrage::Libre->value, 'cible' => null, 'position' => null];
    }
}
