<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

use App\Calcul\Couche\TypeBesoin;

/**
 * Quantités annexes d'une installation : boîtes, mécanismes, plaques et connecteurs (par élément), gaines et câbles
 * (par cheminement), fixations de gaine (un collier par pas de gaine). Les catégories sont celles du catalogue
 * `electrique_*`, pour que les besoins rejoignent la nomenclature du projet.
 */
final class QuantitesAnnexes
{
    public const int PAS_FIXATION_DEFAUT_MM = 300;

    /**
     * @param list<ElementReseau>       $elements tous les éléments électriques du projet (ou de la pièce)
     * @param list<ResultatCheminement> $cheminements
     *
     * @return list<BesoinElectrique>
     */
    public function calculer(array $elements, array $cheminements, int $pasFixationMm = self::PAS_FIXATION_DEFAUT_MM): array
    {
        /** @var array<string, BesoinElectrique> $besoins */
        $besoins = [];
        $ajouter = static function (string $cle, string $categorie, array $criteres, TypeBesoin $type, int $quantite) use (&$besoins): void {
            if ($quantite <= 0) {
                return;
            }
            ksort($criteres);
            $id = $categorie.'|'.json_encode($criteres, \JSON_THROW_ON_ERROR).'|'.$type->value;
            $existant = $besoins[$id] ?? null;
            $besoins[$id] = new BesoinElectrique($cle, $categorie, $criteres, $type, ($existant->quantite ?? 0) + $quantite);
        };

        $entrants = [];
        foreach ($cheminements as $cheminement) {
            foreach ($cheminement->troncons as $troncon) {
                $entrants[$troncon->enfant] = $troncon->conducteurs();
            }
        }

        foreach ($elements as $element) {
            $type = $element->type;
            if (null !== $type->boite) {
                $criteres = $type->boite->criteres;
                if ('electrique_boite' === $type->boite->categorie && !isset($criteres['type_boite'])) {
                    $criteres['type_boite'] = $element->typeBoite;
                }
                $ajouter('box', $type->boite->categorie, $criteres, TypeBesoin::Nombre, 1);
            }
            if (null !== $type->mecanisme) {
                $ajouter('mechanism', $type->mecanisme->categorie, $type->mecanisme->criteres, TypeBesoin::Nombre, 1);
            }
            if (null !== $type->plaque) {
                $ajouter('plate', $type->plaque->categorie, $type->plaque->criteres, TypeBesoin::Nombre, 1);
            }
            if ($type->derivation && null !== $type->connecteur) {
                $ajouter('connector', $type->connecteur->categorie, $type->connecteur->criteres, TypeBesoin::Nombre, $entrants[$element->cle] ?? 0);
            }
        }

        $gaineTotale = 0;
        foreach ($cheminements as $cheminement) {
            foreach ($cheminement->gaines as $diametre => $longueur) {
                $ajouter('conduit', 'electrique_gaine', ['diametre' => $diametre], TypeBesoin::Longueur, $longueur);
                $gaineTotale += $longueur;
            }
            foreach ($cheminement->cables as $cable) {
                $ajouter('cable', 'electrique_cable', ['section' => Section::texte($cable['section']), 'nb_conducteurs' => $cable['conducteurs']], TypeBesoin::Longueur, $cable['longueur']);
            }
        }
        if ($pasFixationMm > 0) {
            $ajouter('fixing', 'electrique_connecteur', ['type' => 'fixation'], TypeBesoin::Nombre, intdiv($gaineTotale + $pasFixationMm - 1, $pasFixationMm));
        }

        return array_values($besoins);
    }
}
