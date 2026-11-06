<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 23:09
 */

declare(strict_types=1);

namespace App\Service;

use App\Model\Catalogue\ContientPrixInterface;
use App\Model\Catalogue\LongueurInterface;
use App\Model\Chantier\Appuis;
use App\Model\Chantier\Montant;
use App\Model\Chantier\PlaquePlatre;
use App\Model\Chantier\Rail;
use App\Model\Mur;

final class MateriauxCalculator
{
    /**
     * @param list<Appuis> $appuis
     * @param list<Montant> $montants
     * @param list<Rail> $rails
     * @param list<PlaquePlatre> $plaquePlatres
     * @param ContientPrixInterface $appuisIntermediaire
     * @param array<string, ContientPrixInterface&LongueurInterface> $longueursStandard
     *        ex: ['montant' => $montantCatalogue, 'rail' => $railCatalogue]
     * @return array<int|string, array{type: string, categorie: string, longueur_unitaire: int, elements: int, longueur_totale: int, barres_necessaires: int, longueur_achetee: int, nombre_lot: int, prix_total: float|int, chute: int, taux_perte: float|int}>
     */
    public function calculer(
        array $appuis,
        array $montants,
        array $rails,
        array $plaquePlatres,
        array $longueursStandard,
        ContientPrixInterface $appuisIntermediaire,
    ): array {
        $groupes = [];

        // --- Montants ---
        foreach ($montants as $montant) {
            $this->ajouterElement(
                $groupes,
                'montant',
                $montant->hauteur
            );
        }

        // --- Rails ---
        foreach ($rails as $rail) {
            $this->ajouterElement(
                $groupes,
                'rail',
                $rail->longueur + ($rail->surOuverture ? 200 : 0) //Ajout de 20cm pour les rails des ouvertures pour la fixation.
            );
        }

        // --- Optimisation ---
        $resultats = [];

        if (\count($appuis) > 0) {
            $nbAppuis = count($appuis);
            $nblots = (int) ceil($nbAppuis / $appuisIntermediaire->getUniteParLot());
            $chute = ($appuisIntermediaire->getUniteParLot() * $nblots) - $nbAppuis;
            $tauxPerte = $chute > 0 ? ($chute / ($appuisIntermediaire->getUniteParLot() * $nblots)) * 100 : 0;
            $resultats[] = [
                'type' => 'appuis-intermediaire',
                'categorie' => 'appui intermédiaire',
                'longueur_unitaire' => 0,
                'elements' => $nbAppuis,
                'longueur_totale' => 0,
                'barres_necessaires' => $nbAppuis,
                'longueur_achetee' => 0,
                'nombre_lot' => $nblots,
                'prix_total' => $nblots * $appuisIntermediaire->getPrix(),
                'chute' => $chute,
                'taux_perte' => $tauxPerte
            ];
        }

        if (\count($plaquePlatres) > 0) {
            foreach ($plaquePlatres as $plaquePlatre) {
                $this->ajouterElement(
                    $groupes,
                    'plaque-platre',
                    $plaquePlatre->longeur,
                );
                if ($plaquePlatre->typeJointDroit !== PlaquePlatre::TYPE_JOINT_AUCUN) {
                    $this->ajouterElement(
                        $groupes,
                        'joint-' . ($plaquePlatre->typeJointDroit === PlaquePlatre::TYPE_JOINT_SORTANT ? 'armee' : 'plat'),
                        $plaquePlatre->longeur,
                    );
                }
                if ($plaquePlatre->typeJointGauche !== PlaquePlatre::TYPE_JOINT_AUCUN) {
                    $this->ajouterElement(
                        $groupes,
                        'joint-' . ($plaquePlatre->typeJointGauche === PlaquePlatre::TYPE_JOINT_SORTANT ? 'armee' : 'plat'),
                        $plaquePlatre->longeur,
                    );
                }
                if ($plaquePlatre->typeJointHaut !== PlaquePlatre::TYPE_JOINT_AUCUN) {
                    $this->ajouterElement(
                        $groupes,
                        'joint-' . ($plaquePlatre->typeJointHaut === PlaquePlatre::TYPE_JOINT_SORTANT ? 'armee' : 'plat'),
                        $plaquePlatre->largeur,
                    );
                }
                if ($plaquePlatre->typeJointBas !== PlaquePlatre::TYPE_JOINT_AUCUN) {
                    $this->ajouterElement(
                        $groupes,
                        'joint-' . ($plaquePlatre->typeJointBas === PlaquePlatre::TYPE_JOINT_SORTANT ? 'armee' : 'plat'),
                        $plaquePlatre->largeur,
                    );
                }

            }
        }

        foreach ($groupes as $type => $groupe) {
            $materiel = $longueursStandard[$type]
                ?? throw new \LogicException("Longueur standard manquante pour $type");

            $longueurTotale = array_sum($groupe['longueurs']);

            $barres = $this->optimiserBarres(
                $groupe['longueurs'],
                $materiel->getLongueur()
            );

            $longueurAchetee = $barres * $materiel->getLongueur();
            $chute = $longueurAchetee - $longueurTotale;
            $tauxPerte = $longueurAchetee > 0
                ? ($chute / $longueurAchetee) * 100
                : 0;

            $nbLots = (int) ceil($barres / $materiel->getUniteParLot());
            $prixTotal = $nbLots * $materiel->getPrix();

            $resultats[$type] = [
                'type' => $type,
                'categorie' => $type,
                'longueur_unitaire' => $materiel->getLongueur(),
                'elements' => count($groupe['longueurs']),
                'longueur_totale' => $longueurTotale,
                'barres_necessaires' => $barres,
                'longueur_achetee' => $longueurAchetee,
                'nombre_lot' => $nbLots,
                'prix_total' => round($prixTotal, 2),
                'chute' => $chute,
                'taux_perte' => round($tauxPerte, 1),
            ];
        }

        return $resultats;
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, array{longueurs: list<int>}> $groupes
     */
    private function ajouterElement(
        array &$groupes,
        string $categorie,
        int $longueur
    ): void {

        $groupes[$categorie]['longueurs'][] = $longueur;
    }

    /**
     * Algorithme simple :
     * - on trie par longueur décroissante
     * - on remplit chaque barre séquentiellement
     */
    /**
     * @param list<int> $longueurs
     */
    private function optimiserBarres(array $longueurs, int $barreStandard): int
    {
        rsort($longueurs);

        $barres = [];

        foreach ($longueurs as $longueur) {
            $placee = false;

            foreach ($barres as &$reste) {
                if ($longueur <= $reste) {
                    $reste -= $longueur;
                    $placee = true;
                    break;
                }
            }

            if (!$placee) {
                if ($longueur > $barreStandard) {
                    $nbBarres = intdiv($longueur, $barreStandard);
                    $reste = $longueur - ($nbBarres * $barreStandard);
                    for ($i = 0; $i < $nbBarres; $i++) {
                        $barres[] = 0;
                    }
                    if ($reste > 0) {
                        $longueur = $reste;
                    } else {
                        continue;
                    }
                }

                $barres[] = $barreStandard - $longueur;
            }
        }

        return count($barres);
    }
}
