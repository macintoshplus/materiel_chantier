<?php

declare(strict_types=1);

namespace App\Tests\Service\Caracterisation;

use App\Model\Chantier\PlaquePlatre as Plaque;
use App\Service\OssatureCloisonCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige le comportement actuel du calcul d'ossature en cloison.
 * Valeurs attendues calculées à la main (voir commentaires), pas copiées de la sortie du code.
 */
final class OssatureCloisonCaracterisationTest extends TestCase
{
    use ScenariosTrait;

    public function testMurPleinRailsMontantsEtPlaques(): void
    {
        $r = (new OssatureCloisonCalculator())->calculer($this->murPlein(), $this->montant(), $this->plaque());

        // Rails bas et haut sur toute la largeur.
        self::assertCount(2, $r['rails']);
        self::assertSame('bas', $r['rails'][0]->id);
        self::assertSame(0, $r['rails'][0]->y);
        self::assertSame(3000, $r['rails'][0]->longueur);
        self::assertSame('haut', $r['rails'][1]->id);
        self::assertSame(2500, $r['rails'][1]->y);

        // Montants à 0 ; 0,6 ; 1,2 ; 1,8 ; 2,4 ; 3,0 puis le montant final à 3,0 (doublon actuel) : 7.
        self::assertCount(7, $r['montants']);
        $xs = array_map(static fn ($m) => $m->x, $r['montants']);
        self::assertSame([0, 600, 1200, 1800, 2400, 3000, 3000], $xs);
        foreach ($r['montants'] as $m) {
            self::assertSame(2500, $m->hauteur);
            self::assertTrue($m->appuis);
        }
        self::assertSame('Montant final', $r['montants'][6]->id);

        // ceil(3 / 1,2) = 3 colonnes, 1 ligne : 1,2 + 1,2 + 0,6 de large, 2,5 de haut.
        self::assertCount(3, $r['plaque_platres']);
        self::assertSame([], $r['contour_ouvrant']);
        $largeurs = array_map(static fn (Plaque $p) => $p->largeur, $r['plaque_platres']);
        self::assertSame(1200, $largeurs[0]);
        self::assertSame(1200, $largeurs[1]);
        self::assertSame(600, $largeurs[2]);
        // Joint droit entrant sauf pour la plaque coupée ; joint bas entrant sauf première colonne.
        self::assertSame(Plaque::TYPE_JOINT_ENTRANT, $r['plaque_platres'][0]->typeJointDroit);
        self::assertSame(Plaque::TYPE_JOINT_ENTRANT, $r['plaque_platres'][1]->typeJointDroit);
        self::assertSame(Plaque::TYPE_JOINT_AUCUN, $r['plaque_platres'][2]->typeJointDroit);
        self::assertSame(Plaque::TYPE_JOINT_AUCUN, $r['plaque_platres'][0]->typeJointBas);
        self::assertSame(Plaque::TYPE_JOINT_ENTRANT, $r['plaque_platres'][2]->typeJointBas);
        // Plaque pleine hauteur : joint haut entrant ; jamais de joint gauche sur une seule ligne.
        self::assertSame(Plaque::TYPE_JOINT_ENTRANT, $r['plaque_platres'][0]->typeJointHaut);
        self::assertSame(Plaque::TYPE_JOINT_AUCUN, $r['plaque_platres'][0]->typeJointGauche);
    }

    public function testMurAvecOuverturesRails(): void
    {
        $r = (new OssatureCloisonCalculator())->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque());

        // Zone à doubler : 7 - 2 x 0,048 = 6,904. Rails bas, haut, haut+bas de fenêtre, haut de porte.
        self::assertCount(5, $r['rails']);
        self::assertSame(48, $r['rails'][0]->x);
        self::assertSame(6904, $r['rails'][0]->longueur);
        self::assertSame('ouverture_0_haut', $r['rails'][2]->id);
        self::assertSame(2000, $r['rails'][2]->y);
        self::assertSame(1000, $r['rails'][2]->longueur);
        self::assertTrue($r['rails'][2]->surOuverture);
        self::assertSame('ouverture_0_bas', $r['rails'][3]->id);
        self::assertSame(800, $r['rails'][3]->y);
        self::assertSame('ouverture_1_haut', $r['rails'][4]->id);
        self::assertSame(2050, $r['rails'][4]->y);
        self::assertSame(920, $r['rails'][4]->longueur);
    }

    public function testMurAvecOuverturesMontants(): void
    {
        $r = (new OssatureCloisonCalculator())->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque());

        // Positions 0,048 + k x 0,6 jusqu'à 6,904 : 12 positions.
        // Fenêtre (1 à 2) couvre 1,248 et 1,848 ; porte (3 à 3,92) couvre 3,048 et 3,648.
        // 8 montants pleins + (2 x 2) pour la fenêtre + 2 pour la porte + final + 4 montants d'ouverture = 19.
        self::assertCount(19, $r['montants']);

        $parId = [];
        foreach ($r['montants'] as $m) {
            $parId[$m->id][] = $m;
        }
        self::assertCount(8, $parId['Montant']);
        self::assertCount(4, $parId['Au dessus d\'une ouverture']);
        self::assertCount(2, $parId['sous une fenêtre']);
        self::assertCount(1, $parId['Montant final']);
        self::assertCount(2, $parId['montant gauche ouverture']);
        self::assertCount(2, $parId['montant droit ouverture']);

        // Au-dessus de la fenêtre : de 2,0 à 2,5 (0,5) ; de la porte : de 2,05 à 2,5 (0,45).
        $hauteurs = array_map(static fn ($m) => $m->hauteur, $parId['Au dessus d\'une ouverture']);
        sort($hauteurs);
        self::assertSame([450, 450, 500, 500], $hauteurs);
        foreach ($parId['sous une fenêtre'] as $m) {
            self::assertSame(0, $m->y);
            self::assertSame(800, $m->hauteur);
            self::assertFalse($m->appuis);
        }
        // Montant final contre le mur de droite : 7 - 0,048.
        self::assertSame(6952, $parId['Montant final'][0]->x);
        // Montants d'ouverture : bords de la fenêtre (1,0 et 2,0) et de la porte (3,0 et 3,92).
        $gauche = array_map(static fn ($m) => $m->x, $parId['montant gauche ouverture']);
        $droit = array_map(static fn ($m) => $m->x, $parId['montant droit ouverture']);
        self::assertSame([1000, 3000], $gauche);
        self::assertSame([2000, 3920], $droit);
    }

    public function testMurAvecOuverturesPlaquesEtContours(): void
    {
        $r = (new OssatureCloisonCalculator())->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque());

        // Pose en tunnel : retours gauche, droit, haut (+ bas pour la fenêtre) = 4 + 3.
        self::assertCount(7, $r['contour_ouvrant']);
        foreach ($r['contour_ouvrant'] as $c) {
            self::assertSame(500, $c->largeur);
        }
        $ids = array_map(static fn (Plaque $c) => $c->id, $r['contour_ouvrant']);
        self::assertContains('countour ouvrant 0 bas', $ids);
        self::assertNotContains('countour ouvrant 1 bas', $ids);
        // Retour gauche de la fenêtre : de 0,8 sur 1,2 de haut, joint sortant à gauche.
        $gaucheFenetre = $r['contour_ouvrant'][0];
        self::assertSame(1000, $gaucheFenetre->x);
        self::assertSame(800, $gaucheFenetre->y);
        self::assertSame(1200, $gaucheFenetre->longeur);
        self::assertSame(Plaque::TYPE_JOINT_SORTANT, $gaucheFenetre->typeJointGauche);
        self::assertSame(Plaque::TYPE_JOINT_ENTRANT, $gaucheFenetre->typeJointBas);
        // Retour gauche de la porte : pas de joint bas (pas de retour sous une porte).
        self::assertSame(Plaque::TYPE_JOINT_AUCUN, $r['contour_ouvrant'][4]->typeJointBas);

        // ceil(6,904 / 1,2) = 6 plaques de grand mur (la liste contient d'abord les 7 retours), dernière large de 0,904.
        self::assertCount(13, $r['plaque_platres']);
        $derniere = $r['plaque_platres'][12];
        self::assertSame(904, $derniere->largeur);
        self::assertSame(6000, $derniere->x);
    }

    public function testRetoursEnApplique(): void
    {
        $mur = $this->murPlein();
        $mur->addOuverture([
            'type' => 'porte', 'largeur' => 900, 'hauteur' => 2000, 'distance_gauche' => 1000, 'elevation' => 0,
            'type_pose' => 'applique', 'type_contour' => 'aucun', 'refoncement_ouvrant' => 0,
        ]);
        $r = (new OssatureCloisonCalculator())->calculer($mur, $this->montant(), $this->plaque());

        // Pas de retour en applique ; les rails de l'ouverture existent quand même (haut seulement pour une porte).
        self::assertSame([], $r['contour_ouvrant']);
        self::assertCount(3, $r['rails']);
    }
}
