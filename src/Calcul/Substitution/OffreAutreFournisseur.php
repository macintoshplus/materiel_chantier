<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

/**
 * Applique les maillons internes (exact puis équivalent) chez chacun des autres fournisseurs de la demande, dans
 * l'ordre donné. Ne fait rien pour une stratégie à fournisseur unique (liste vide).
 */
final readonly class OffreAutreFournisseur implements MaillonOffre
{
    /**
     * @param list<MaillonOffre> $maillons
     */
    public function __construct(private array $maillons)
    {
    }

    public function resoudre(DemandeOffre $demande): ?Offre
    {
        foreach ($demande->autresFournisseurs as $fournisseur) {
            $autre = $demande->chez($fournisseur);
            foreach ($this->maillons as $maillon) {
                $offre = $maillon->resoudre($autre);
                if (null !== $offre) {
                    return $offre->avecNature(NatureOffre::Exacte === $offre->nature ? NatureOffre::ExacteAutreFournisseur : NatureOffre::EquivalenteAutreFournisseur);
                }
            }
        }

        return null;
    }
}
