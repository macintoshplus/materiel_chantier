<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Entity\Chiffrage;
use App\Enum\TypeLigneChiffrage;

/**
 * Écart par ligne (article, livraison) et sur le total entre deux chiffrages, par exemple avant et après un changement
 * de prix ou de variante : `ecart = B - A`.
 */
final class ComparaisonChiffrages
{
    /**
     * @return array{lignes: list<EcartLigne>, totalA: int, totalB: int, ecart: int}
     */
    public function comparer(Chiffrage $a, Chiffrage $b): array
    {
        $lignesA = $this->totaux($a);
        $lignesB = $this->totaux($b);
        $lignes = [];
        foreach ([...array_keys($lignesA), ...array_keys($lignesB)] as $cle) {
            $cle = (string) $cle;
            if (isset($lignes[$cle])) {
                continue;
            }
            $lignes[$cle] = new EcartLigne($cle, ($lignesA[$cle] ?? $lignesB[$cle])['libelle'], ($lignesA[$cle]['total'] ?? null), ($lignesB[$cle]['total'] ?? null));
        }

        return ['lignes' => array_values($lignes), 'totalA' => $a->getTotalTtcCentimes(), 'totalB' => $b->getTotalTtcCentimes(), 'ecart' => $b->getTotalTtcCentimes() - $a->getTotalTtcCentimes()];
    }

    /**
     * @return array<string, array{libelle: string, total: int}>
     */
    private function totaux(Chiffrage $chiffrage): array
    {
        $totaux = [];
        foreach ($chiffrage->getLignes() as $ligne) {
            if (TypeLigneChiffrage::Repartition === $ligne->getType()) {
                continue;
            }
            $livraison = TypeLigneChiffrage::Livraison === $ligne->getType();
            $cle = $livraison ? 'livraison|'.$ligne->getFournisseurNom() : (string) $ligne->getMateriauReference();
            $libelle = $livraison ? (string) $ligne->getFournisseurNom() : (string) $ligne->getMateriauLibelle();
            $totaux[$cle] = ['libelle' => $libelle, 'total' => ($totaux[$cle]['total'] ?? 0) + $ligne->getTotalCentimes()];
        }

        return $totaux;
    }
}
