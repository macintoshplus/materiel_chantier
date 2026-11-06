<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Calcul\Systeme\RegistreSystemes;
use App\Calcul\Cadre\RetourTunnel;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Pipeline\LigneSysteme;
use App\Calcul\Pipeline\ResultatSysteme;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\ParametreSysteme;
use App\Catalogue\Vue\ProfilView;
use App\Entity\Cloison;
use App\Entity\CoucheAppliquee;
use App\Geometrie\Avertissement;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\CoteCloison;
use App\Enum\TypeJonction;

/**
 * Calcule un système appliqué : entités vers descriptions pures de couches, puis pipeline. Pour un système posé
 * sur une cloison, chaque face est calculée avec ses couches (celles sans face valent pour les deux).
 */
final class CalculSysteme
{
    public function __construct(
        private readonly RegistreSystemes $systemes,
        private readonly PipelineCouches $pipeline,
        private readonly AdaptateurSurface $adaptateur,
        private readonly SpecificationSerialiseur $serialiseur,
    ) {
    }

    /**
     * @return array<string, ResultatPipeline> résultat par support (clé : nom de la surface)
     */
    public function calculer(SystemeApplique $systeme): array
    {
        $definition = $this->systemes->get($systeme->getDefinition());
        $resultats = [];
        $surface = $systeme->getSurface();
        $surfaces = $surface instanceof Surface ? [$surface] : ($systeme->getCloison()?->getFaces()->getValues() ?? []);
        foreach ($surfaces as $surface) {
            $resultats[$surface->getNom()] = $this->pipeline->executer(
                $definition,
                $this->adaptateur->versCalculable($surface),
                $this->decrire($systeme, $surface->getCote()),
                $this->parametresSurface($definition, $surface, $systeme->getParametres()),
            );
        }

        return $resultats;
    }

    /**
     * Calcule un système complet : pour une cloison, l'ossature et l'isolant (couches sans face) ne sont comptés
     * qu'une fois et les parements par face ; l'épaisseur est celle de l'ossature plus les parements des deux faces.
     */
    public function calculerSysteme(SystemeApplique $systeme): ResultatSysteme
    {
        $definition = $this->systemes->get($systeme->getDefinition());
        $surface = $systeme->getSurface();
        if ($surface instanceof Surface) {
            $resultat = $this->pipeline->executer($definition, $this->adaptateur->versCalculable($surface), $this->decrire($systeme), $this->parametresSurface($definition, $surface, $systeme->getParametres()));
            if (!$resultat->estValide()) {
                return new ResultatSysteme([], 0, null, $resultat->avertissements, $resultat->erreurs);
            }
            $lignes = array_map(static fn (LigneBesoin $l): LigneSysteme => new LigneSysteme(null, $l), $resultat->lignes);

            return new ResultatSysteme($lignes, $resultat->contexte->rCumuleMilli, $this->serie($resultat) + $resultat->contexte->epaisseurParementPrevue, $resultat->avertissements, [], $resultat->resistances);
        }

        $cloison = $systeme->getCloison() ?? throw new \LogicException('The system has no support.');
        $faces = [];
        foreach ($cloison->getFaces() as $face) {
            if (null !== $face->getCote()) {
                $faces[$face->getCote()->value] = $face;
            }
        }
        if (!isset($faces['A'], $faces['B'])) {
            return new ResultatSysteme([], 0, null, [], [new Avertissement('partition_faces_missing')]);
        }

        $parametres = $this->parametresCloison($definition, $cloison, $systeme->getParametres());
        $specifiques = [];
        foreach ($systeme->getCouches() as $couche) {
            if (null !== $couche->getFace()) {
                $specifiques[$couche->getEmplacement()] = true;
            }
        }

        $lignes = [];
        $avertissements = [];
        $resultats = [];
        $surfaces = [];
        foreach (['B', 'A'] as $cote) {
            $surfaces[$cote] = $this->adaptateur->versCalculableFace($faces[$cote]);
            $resultat = $resultats[$cote] = $this->pipeline->executer($definition, $surfaces[$cote], $this->decrire($systeme, CoteCloison::from($cote)), $parametres);
            if (!$resultat->estValide()) {
                return new ResultatSysteme([], 0, null, $resultat->avertissements, $resultat->erreurs);
            }
            array_push($avertissements, ...$resultat->avertissements);
            foreach ($resultat->lignes as $ligne) {
                $propre = isset($specifiques[$ligne->emplacement]);
                if ('A' === $cote && !$propre) {
                    continue;
                }
                $lignes[] = new LigneSysteme($propre ? $cote : null, $ligne);
            }
        }

        $epaisseur = $this->serie($resultats['B']) + $resultats['A']->contexte->epaisseurParementPrevue + $resultats['B']->contexte->epaisseurParementPrevue;
        $retour = $this->retourTunnel($surfaces['B'], $resultats['A'], $epaisseur);
        if (null !== $retour) {
            $lignes[] = new LigneSysteme('A', $retour);
        }

        $uniques = [];
        foreach ($avertissements as $a) {
            $uniques[json_encode([$a->code, $a->params], \JSON_THROW_ON_ERROR)] = $a;
        }

        return new ResultatSysteme($lignes, $resultats['B']->contexte->rCumuleMilli, $epaisseur, array_values($uniques), [], $resultats['B']->resistances);
    }

    /**
     * Surfaces calculables des deux faces d'une cloison, avec les ouvertures projetées (clé : `A` ou `B`).
     *
     * @return array<string, SurfaceCalculable>
     */
    public function surfacesCloison(Cloison $cloison): array
    {
        $surfaces = [];
        foreach ($cloison->getFaces() as $face) {
            if (null !== $face->getCote()) {
                $surfaces[$face->getCote()->value] = $this->adaptateur->versCalculableFace($face);
            }
        }

        return $surfaces;
    }

    /**
     * Retour de tunnel : surface de plaque (face A par défaut) couvrant l'épaisseur totale de la cloison.
     */
    private function retourTunnel(SurfaceCalculable $faceB, ResultatPipeline $faceA, int $epaisseur): ?LigneBesoin
    {
        $sol = $faceB->boite()->minY;
        $surface = 0;
        foreach ($faceB->ouvertures as $ouverture) {
            $surface += RetourTunnel::surface($ouverture, $epaisseur, $sol);
        }
        if (0 === $surface) {
            return null;
        }
        $materiau = null;
        foreach ($faceA->materiaux as $m) {
            if (null !== $m && 'plaque_platre' === $m->categorie()) {
                $materiau = $m;
                break;
            }
        }

        return LigneBesoin::surface('retour_tunnel', 'parement', 'plaque_platre', $surface, $materiau);
    }

    private function serie(ResultatPipeline $resultat): int
    {
        foreach ($resultat->materiaux as $m) {
            if (null !== $m && 'profil_ossature' === $m->categorie()) {
                return (new ProfilView($m))->profondeur();
            }
        }

        return 0;
    }

    /**
     * Arêtes ouvertes d'un plafond (sans lisse) déduites des jonctions avec un autre pan (faîtage, noue, jonction
     * à plat), sauf si elles sont imposées par le paramètre `aretes_ouvertes`. Format `a:b`, séparées par des virgules.
     *
     * @param array<string, scalar|null> $parametres
     *
     * @return array<string, scalar|null>
     */
    private function parametresSurface(DefinitionSysteme $definition, Surface $surface, array $parametres): array
    {
        $declares = array_map(static fn (ParametreSysteme $p): string => $p->nom, $definition->parametres());
        if (!\in_array('aretes_ouvertes', $declares, true) || isset($parametres['aretes_ouvertes'])) {
            return $parametres;
        }
        $ouvertes = [];
        foreach ($surface->getPiece()->getJonctions() as $jonction) {
            if (!\in_array($jonction->getType(), [TypeJonction::Faitage, TypeJonction::Noue, TypeJonction::Plat], true)) {
                continue;
            }
            if ($jonction->getSurfaceA() === $surface) {
                $ouvertes[] = $jonction->getSommetDebutA().':'.$jonction->getSommetFinA();
            } elseif ($jonction->getSurfaceB() === $surface && null !== $jonction->getSommetDebutB() && null !== $jonction->getSommetFinB()) {
                $ouvertes[] = $jonction->getSommetDebutB().':'.$jonction->getSommetFinB();
            }
        }
        $parametres['aretes_ouvertes'] = implode(',', $ouvertes);

        return $parametres;
    }

    /**
     * Extrémités déduites des ancrages de la cloison, sauf si elles sont imposées par les paramètres.
     *
     * @param array<string, scalar|null> $parametres
     *
     * @return array<string, scalar|null>
     */
    private function parametresCloison(DefinitionSysteme $definition, Cloison $cloison, array $parametres): array
    {
        $declares = array_map(static fn (ParametreSysteme $p): string => $p->nom, $definition->parametres());
        foreach (['extremite_debut' => $cloison->getAncrageDebut(), 'extremite_fin' => $cloison->getAncrageFin()] as $nom => $ancrage) {
            if (\in_array($nom, $declares, true) && !isset($parametres[$nom])) {
                $parametres[$nom] = $ancrage['type'];
            }
        }

        return $parametres;
    }

    /**
     * @return list<CoucheDecrite>
     */
    public function decrire(SystemeApplique $systeme, ?CoteCloison $cote = null): array
    {
        $couches = [];
        foreach ($systeme->getCouches() as $couche) {
            if (null !== $couche->getFace() && null !== $cote && $couche->getFace() !== $cote) {
                continue;
            }
            $couches[] = $this->vers($couche);
        }

        return $couches;
    }

    private function vers(CoucheAppliquee $couche): CoucheDecrite
    {
        $specification = $couche->getSpecification();

        return new CoucheDecrite(
            $couche->getOrdre(),
            $couche->getEmplacement(),
            $couche->getRole(),
            null !== $specification ? $this->serialiseur->depuis($specification) : null,
            $couche->getMateriau(),
            $couche->getOrientation() ?? 0,
            null !== $couche->getPosition() ? PositionCouche::from($couche->getPosition()) : null,
            $couche->getDecalage()['u'] ?? null,
            $couche->getEpaisseurOverride(),
            $couche->getFace()?->value,
        );
    }
}
