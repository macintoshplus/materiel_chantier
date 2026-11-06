<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Equipement;
use App\Entity\Piece;
use App\Entity\Reseau;
use App\Entity\Surface;
use App\Enum\FamilleEquipement;
use App\Form\Dto\EquipementSaisie;
use App\Geometrie\Avertissement;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création, modification et suppression d'un équipement (toutes familles). Les contrôles de position sont des
 * avertissements, jamais un blocage ; supprimer un équipement ou le sortir d'un réseau retire ses liaisons.
 */
final class EquipementEditionService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ControleElectricite $controle)
    {
    }

    /**
     * @return list<Avertissement> avertissements de position
     */
    public function enregistrer(Piece $piece, Surface $surface, FamilleEquipement $famille, ?Equipement $equipement, EquipementSaisie $saisie): array
    {
        $equipement ??= new Equipement($piece, $surface);
        $equipement->setFamille($famille);
        $equipement->setType($saisie->type ?? throw new \LogicException('The type is validated before saving.'));
        $equipement->setNom($saisie->nom);
        $equipement->placer((int) $saisie->u, (int) $saisie->v);

        $ancien = $equipement->getReseau();
        if (null !== $ancien && $ancien !== $saisie->reseau && null !== $equipement->getId()) {
            self::retirerLiaisons($ancien, (int) $equipement->getId());
        }
        $equipement->setReseau($saisie->reseau);

        $parametres = $equipement->getParametres();
        if (null === $saisie->puissanceW) {
            unset($parametres['puissance_w']);
        } else {
            $parametres['puissance_w'] = $saisie->puissanceW;
        }
        $equipement->setParametres($parametres);

        $this->em->persist($equipement);
        $this->em->flush();

        return FamilleEquipement::Electrique === $famille ? $this->controle->position($equipement) : [];
    }

    public function supprimer(Equipement $equipement): void
    {
        $id = $equipement->getId();
        if (null !== $id) {
            foreach ($equipement->getPiece()->getProjet()->getReseaux() as $reseau) {
                self::retirerLiaisons($reseau, $id);
                if ($reseau->getDepart() === $id) {
                    $reseau->definirDepart(null);
                }
            }
        }
        $equipement->setReseau(null);
        $equipement->getPiece()->retirerEquipement($equipement);
        $this->em->remove($equipement);
        $this->em->flush();
    }

    /**
     * Retire d'un réseau les liaisons qui touchent l'équipement ; les éléments qu'il alimentait perdent leur parent.
     */
    public static function retirerLiaisons(Reseau $reseau, int $equipementId): void
    {
        foreach ($reseau->getLiaisons() as $liaison) {
            if ($liaison['parent'] === $equipementId) {
                $reseau->delier($liaison['equipement']);
            }
        }
        $reseau->delier($equipementId);
    }
}
