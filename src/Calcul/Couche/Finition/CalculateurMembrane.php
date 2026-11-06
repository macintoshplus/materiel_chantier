<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Finition;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Calcul\Couche\TypeBesoin;
use App\Geometrie\Avertissement;

/**
 * Membrane (pare-vapeur, frein-vapeur) sur la surface isolée, de R nul. Rouleaux = `ceil(surface × (1 + recouvrement) /
 * surface utile)`, ce qui revient à `ceil(surface / ((largeur − recouvrement) × longueur))` ; le recouvrement vient de
 * l'article (`recouvrement_mm`, 100 mm par défaut). L'adhésif (mm) couvre les jonctions de lés (lés verticaux) et la
 * périphérie. La continuité (raccords, passages de gaines) n'est pas contrôlée : elle relève de la marge de sécurité.
 */
final class CalculateurMembrane implements CalculateurCouche
{
    public const int RECOUVREMENT_DEFAUT = 100;

    public function code(): string
    {
        return 'membrane.rouleaux';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The membrane layer has no material.');
        $largeur = $materiau->valeur('largeur');
        $longueur = $materiau->valeur('longueur');
        if (!\is_int($largeur) || !\is_int($longueur) || $largeur <= 0 || $longueur <= 0) {
            return new ResultatCouche([], $contexte, [], [new Avertissement('membrane_roll_missing', ['order' => $couche->couche->ordre])]);
        }
        $recouvrement = $materiau->valeur('recouvrement_mm');
        $recouvrement = \is_int($recouvrement) && $recouvrement >= 0 && $recouvrement < $largeur ? $recouvrement : self::RECOUVREMENT_DEFAUT;
        $utile = ($largeur - $recouvrement) * $longueur;

        $surface = $couche->surface->surfaceNette();
        $boite = $couche->surface->boite();
        $les = max(1, (int) ceil($boite->largeur() / ($largeur - $recouvrement)));
        $adhesif = ($les - 1) * $boite->hauteur() + $couche->surface->polygone()->perimetre();

        return new ResultatCouche([
            LigneBesoin::nombre('membrane_'.$couche->couche->ordre, $couche->emplacement->code, 'membrane', (int) ceil($surface / $utile), ['recouvrement_mm' => $recouvrement], $materiau),
            new LigneBesoin('adhesif_membrane_'.$couche->couche->ordre, $couche->emplacement->code, 'adhesif', TypeBesoin::Longueur, $adhesif),
        ], $contexte);
    }
}
