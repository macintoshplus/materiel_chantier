<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Entity\Ouverture;
use App\Entity\Surface;
use App\Form\Dto\OuvertureSaisie;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronise les ouvertures d'une surface ou d'une cloison avec la saisie : celles qui ne sont plus saisies sont
 * supprimées (la suppression d'une ligne est explicite, validée par « Save »), les nouvelles reçoivent un nom par défaut.
 */
final readonly class OuvertureEditionService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param list<OuvertureSaisie> $saisies
     */
    public function synchroniser(Surface|Cloison $parent, array $saisies): void
    {
        $existantes = [];
        foreach ($parent->getOuvertures() as $ouverture) {
            $existantes[(string) $ouverture->getId()] = $ouverture;
        }
        $gardees = [];
        foreach ($saisies as $saisie) {
            $ouverture = null !== $saisie->id && isset($existantes[$saisie->id]) ? $existantes[$saisie->id] : null;
            if (null === $ouverture) {
                $ouverture = $parent instanceof Surface ? new Ouverture($parent) : new Ouverture(null, $parent);
                $this->em->persist($ouverture);
            }
            $gardees[spl_object_id($ouverture)] = true;
            $ouverture->setType($saisie->type);
            $ouverture->definirRectangle($saisie->u ?? 0, $saisie->v ?? 0, $saisie->largeur ?? 1, $saisie->hauteur ?? 1);
            $ouverture->setTypePose($saisie->typePose);
            $ouverture->setRefoncement($saisie->refoncement ?? 0);
            $nom = null === $saisie->nom ? '' : trim($saisie->nom);
            $ouverture->setNom('' === $nom ? self::nomParDefaut($parent, $ouverture) : $nom);
        }
        foreach ($existantes as $ouverture) {
            if (!isset($gardees[spl_object_id($ouverture)])) {
                $parent->retirerOuverture($ouverture);
                $this->em->remove($ouverture);
            }
        }
    }

    /**
     * « Window n » ou « Door n » : n est le premier entier libre parmi les ouvertures du même type.
     */
    private static function nomParDefaut(Surface|Cloison $parent, Ouverture $ouverture): string
    {
        $base = 'porte' === $ouverture->getType() ? 'Door' : 'Window';
        $pris = [];
        foreach ($parent->getOuvertures() as $autre) {
            if ($autre !== $ouverture) {
                $pris[$autre->getNom() ?? ''] = true;
            }
        }
        $n = 1;
        while (isset($pris[$base.' '.$n])) {
            ++$n;
        }

        return $base.' '.$n;
    }
}
