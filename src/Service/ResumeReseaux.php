<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Couche\TypeBesoin;
use App\Calcul\Electricite\QuantitesAnnexes;
use App\Catalogue\RegistreCategories;
use App\Entity\Projet;
use App\Enum\FamilleEquipement;
use App\Geometrie\Avertissement;

/**
 * Résumé des réseaux d'une famille pour l'écran des circuits : cheminement (`ElectriciteProjet`), quantités annexes
 * par réseau (`QuantitesAnnexes`), charge et messages. Seule l'électricité est calculée pour l'instant.
 */
final class ResumeReseaux
{
    public function __construct(
        private readonly ElectriciteProjet $electricite,
        private readonly ControleElectricite $controle,
        private readonly ReseauEditionService $edition,
        private readonly RegistreCategories $categories,
    ) {
    }

    /**
     * @return array{reseaux: list<ResumeReseau>, avertissements: list<Avertissement>, erreurs: list<Avertissement>}
     */
    public function pour(Projet $projet, FamilleEquipement $famille): array
    {
        $calculee = FamilleEquipement::Electrique === $famille;
        $resultat = $calculee ? $this->electricite->analyser($projet) : null;
        $parId = [];
        foreach ($this->edition->equipementsDuProjet($projet, $famille) as $equipement) {
            if (null !== $equipement->getId()) {
                $parId[$equipement->getId()] = $equipement;
            }
        }

        $resumes = [];
        $propres = [];
        foreach ($projet->getReseaux() as $reseau) {
            if ($reseau->getFamille() !== $famille) {
                continue;
            }
            $cle = spl_object_id($reseau);
            $cheminement = $resultat?->cheminementParReseau[$cle] ?? null;
            $quantites = [];
            if (null !== $cheminement) {
                foreach ((new QuantitesAnnexes())->calculer($resultat->elementsParReseau[$cle] ?? [], [$cheminement]) as $besoin) {
                    $criteres = [];
                    foreach ($besoin->criteres as $nom => $valeur) {
                        $criteres[] = $nom.' = '.$valeur;
                    }
                    $quantites[] = [
                        'categorie' => $this->categories->possede($besoin->categorie) ? $this->categories->get($besoin->categorie)->libelle() : $besoin->categorie,
                        'criteres' => implode(', ', $criteres),
                        'quantite' => $besoin->quantite,
                        'longueur' => TypeBesoin::Longueur === $besoin->type,
                    ];
                }
            }
            $avertissements = [];
            $erreurs = [];
            if (null !== $cheminement) {
                $avertissements = $cheminement->avertissements;
                $erreurs = $cheminement->erreurs;
            }
            array_push($propres, ...$avertissements, ...$erreurs);
            $depart = null === $reseau->getDepart() ? null : ($parId[$reseau->getDepart()] ?? null);
            $resumes[] = new ResumeReseau(
                $reseau,
                array_values($reseau->getEquipements()->toArray()),
                $depart,
                $this->controle->charge($projet, $reseau),
                $reseau->getProtection() * ControleElectricite::TENSION_V,
                $cheminement,
                $quantites,
                $avertissements,
                $erreurs,
            );
        }

        // Messages du projet qui ne sont pas déjà affichés sous un réseau.
        $sansPropres = static fn (array $liste): array => array_values(array_filter($liste, static fn (Avertissement $a): bool => !\in_array($a, $propres, false)));

        return ['reseaux' => $resumes, 'avertissements' => $sansPropres($resultat->avertissements ?? []), 'erreurs' => $sansPropres($resultat->erreurs ?? [])];
    }
}
