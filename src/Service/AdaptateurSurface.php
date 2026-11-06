<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Surface\OuvertureCalculable;
use App\Calcul\Surface\SurfaceCalculable;
use App\Entity\Ouverture;
use App\Entity\Surface;
use App\Enum\TypePose;
use App\Model\Mur;

/**
 * Adaptateurs d'une `Surface` : vers la surface pure du moteur de couches, et vers le `Mur` des calculateurs
 * historiques (rectangle aligné sur les axes seulement).
 */
final class AdaptateurSurface
{
    public function versCalculable(Surface $surface): SurfaceCalculable
    {
        $ouvertures = [];
        foreach ($surface->getOuvertures() as $o) {
            $ouvertures[] = new OuvertureCalculable($o->getForme(), $o->getType(), $o->getTypePose(), $o->getTypeContour(), $o->getRefoncement());
        }

        return new SurfaceCalculable($surface->getType(), $surface->getPlan(), $surface->getContour(), $ouvertures);
    }

    /**
     * @param string $typeStructure `Mur::TYPE_DOUBLAGE` ou `Mur::TYPE_CLOISON`
     *
     * @throws \InvalidArgumentException si la surface n'est pas un rectangle aligné sur les axes
     */
    public function versMur(Surface $surface, string $typeStructure = Mur::TYPE_DOUBLAGE): Mur
    {
        $polygone = $surface->getContour()->polygone();
        if (!$polygone->estRectangleAxes()) {
            throw new \InvalidArgumentException('The legacy calculators only handle rectangular surfaces.');
        }
        $boite = $polygone->boiteEnglobante();

        $mur = new Mur();
        $mur->setLargeur($boite->largeur());
        $mur->setHauteur($boite->hauteur());
        $mur->setTypeStructure($typeStructure);
        $mur->setOuvertures(array_map(
            static fn (Ouverture $o): array => [
                'type' => $o->getType(),
                'largeur' => $o->getLargeur(),
                'hauteur' => $o->getHauteur(),
                'distance_gauche' => $o->getU() - $boite->minX,
                'elevation' => $o->getV() - $boite->minY,
                'type_pose' => TypePose::Tunnel === $o->getTypePose() ? 'tunel' : 'applique',
                'type_contour' => $o->getTypeContour() ?? '',
                'refoncement_ouvrant' => $o->getRefoncement(),
            ],
            array_values($surface->getOuvertures()->toArray()),
        ));

        return $mur;
    }
}
