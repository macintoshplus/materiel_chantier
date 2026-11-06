<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Equipement;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Enum\FamilleEquipement;
use App\Form\Dto\ReseauSaisie;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création, modification et suppression d'un réseau (circuit) : paramètres, appartenance des équipements, point de
 * départ et liaisons (stockés dans `Reseau.parametres`).
 */
final class ReseauEditionService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Équipements d'un projet dans une famille, dans l'ordre de leurs pièces.
     *
     * @return list<Equipement>
     */
    public function equipementsDuProjet(Projet $projet, FamilleEquipement $famille): array
    {
        $liste = [];
        foreach ($projet->getPieces() as $piece) {
            foreach ($piece->getEquipements() as $equipement) {
                if ($equipement->getFamille() === $famille) {
                    $liste[] = $equipement;
                }
            }
        }

        return $liste;
    }

    public function enregistrer(Projet $projet, FamilleEquipement $famille, ?Reseau $reseau, ReseauSaisie $saisie): Reseau
    {
        $reseau ??= new Reseau($projet, $saisie->type);
        $reseau->setNom($saisie->nom);
        $reseau->setType($saisie->type);
        $reseau->setSection(number_format((float) $saisie->section, 2, '.', ''));
        $reseau->setProtection((int) $saisie->protection);

        $parametres = $reseau->getParametres();
        if (null === $saisie->reservePct) {
            unset($parametres['reserve_pct']);
        } else {
            $parametres['reserve_pct'] = $saisie->reservePct;
        }
        $reseau->setParametres($parametres);

        $this->em->persist($reseau);
        $this->em->flush();

        foreach ($this->equipementsDuProjet($projet, $famille) as $equipement) {
            $membre = \in_array($equipement, $saisie->equipements, true);
            if ($membre && $equipement->getReseau() !== $reseau) {
                $ancien = $equipement->getReseau();
                if (null !== $ancien && null !== $equipement->getId()) {
                    EquipementEditionService::retirerLiaisons($ancien, $equipement->getId());
                }
                $equipement->setReseau($reseau);
            } elseif (!$membre && $equipement->getReseau() === $reseau) {
                $equipement->setReseau(null);
            }
        }

        $reseau->definirDepart(null === $saisie->depart ? null : $saisie->depart->getId());
        $reseau->setParametres([...$reseau->getParametres(), 'liaisons' => []]);
        foreach ($saisie->liaisons as $liaison) {
            if (null !== $liaison->equipement && null !== $liaison->parent && null !== $liaison->equipement->getId() && null !== $liaison->parent->getId()) {
                $reseau->relier($liaison->equipement->getId(), $liaison->parent->getId(), $liaison->conducteurs);
            }
        }
        $this->em->flush();

        return $reseau;
    }

    public function supprimer(Reseau $reseau): void
    {
        foreach ($reseau->getEquipements() as $equipement) {
            $equipement->setReseau(null);
        }
        $this->em->remove($reseau);
        $this->em->flush();
    }
}
