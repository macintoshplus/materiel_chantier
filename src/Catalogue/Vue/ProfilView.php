<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

use App\Catalogue\MaterielLisible;

final readonly class ProfilView
{
    private Lecteur $lecteur;

    public function __construct(MaterielLisible $materiel)
    {
        if ('profil_ossature' !== $materiel->categorie()) {
            throw new \InvalidArgumentException('Material is not a framing profile.');
        }
        $this->lecteur = new Lecteur($materiel);
    }

    public function entraxeDefaut(): int
    {
        return $this->lecteur->entier('pose.entraxe_defaut');
    }

    /**
     * @return list<Portee>
     */
    public function portees(?Orientation $orientation = null): array
    {
        $portees = [];
        foreach ($this->lecteur->liste('pose.portees') as $ligne) {
            $o = Orientation::tryFrom(\is_string($ligne['orientation'] ?? null) ? $ligne['orientation'] : '')
                ?? throw CaracteristiqueManquante::pour('pose.portees.orientation');
            if (null !== $orientation && $o !== $orientation) {
                continue;
            }
            $accessoires = [];
            $brutes = \is_array($ligne['accessoires'] ?? null) ? $ligne['accessoires'] : [];
            foreach ($brutes as $a) {
                if (!\is_array($a) || !\is_string($a['specification'] ?? null)) {
                    throw CaracteristiqueManquante::pour('pose.portees.accessoires.specification');
                }
                $accessoires[] = new AccessoireLie(
                    $a['specification'],
                    \is_int($a['par_joint'] ?? null) ? $a['par_joint'] : null,
                    \is_int($a['pas_mm'] ?? null) ? $a['pas_mm'] : null,
                );
            }
            $portees[] = new Portee(
                Renfort::tryFrom(\is_string($ligne['renfort'] ?? null) ? $ligne['renfort'] : '') ?? throw CaracteristiqueManquante::pour('pose.portees.renfort'),
                $o,
                \is_int($ligne['entraxe'] ?? null) ? $ligne['entraxe'] : throw CaracteristiqueManquante::pour('pose.portees.entraxe'),
                \is_int($ligne['longueur_max'] ?? null) ? $ligne['longueur_max'] : throw CaracteristiqueManquante::pour('pose.portees.longueur_max'),
                \is_int($ligne['decalage_joints_min_mm'] ?? null) ? $ligne['decalage_joints_min_mm'] : null,
                \is_int($ligne['pas_vis_assemblage_mm'] ?? null) ? $ligne['pas_vis_assemblage_mm'] : null,
                $accessoires,
            );
        }

        return $portees;
    }
}
