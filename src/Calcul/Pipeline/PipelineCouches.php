<?php

declare(strict_types=1);

namespace App\Calcul\Pipeline;

use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\ResolveurMateriau;
use App\Calcul\Pose\PlanificateurPose;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Catalogue\MaterielLisible;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;

/**
 * Valide, planifie (résolution des matériaux, épaisseurs prévues) puis calcule les couches dans l'ordre.
 * Le moteur ne connaît aucun système : il ne lit que la définition et les calculateurs enregistrés.
 */
final readonly class PipelineCouches
{
    /** Catégories dont l'épaisseur compte dans l'épaisseur isolée / de parement prévue. */
    private const string CATEGORIE_ISOLANT = 'isolant';
    private const string CATEGORIE_PAREMENT = 'plaque_platre';

    public function __construct(
        private RegistreCalculateurs $calculateurs,
        private ResolveurMateriau $resolveur,
        private ValidateurSysteme $validateur,
        private PlanificateurPose $pose = new PlanificateurPose(),
    ) {
    }

    /**
     * @param list<CoucheDecrite>        $couches
     * @param array<string, scalar|null> $parametres
     */
    public function executer(DefinitionSysteme $definition, SurfaceCalculable $surface, array $couches, array $parametres = [], Direction $directionOssature = Direction::Verticale): ResultatPipeline
    {
        usort($couches, static fn (CoucheDecrite $a, CoucheDecrite $b): int => $a->ordre <=> $b->ordre);
        $contexte = new ContexteCouches(directionOssature: $directionOssature);

        $erreurs = $this->validateur->valider($definition, $surface, $couches, $parametres);
        if ([] !== $erreurs) {
            return new ResultatPipeline([], $contexte, [], $erreurs);
        }

        $emplacements = [];
        foreach ($definition->emplacements() as $e) {
            $emplacements[$e->code] = $e;
        }

        // Planification : d'abord les couches indépendantes du contexte, puis celles qui le référencent.
        /** @var array<int, MaterielLisible|null> $materiaux */
        $materiaux = [];
        $avertissements = [];
        $dependantes = [];
        foreach ($couches as $couche) {
            if (null !== $couche->materiau) {
                $materiaux[$couche->ordre] = $couche->materiau;
            }
            if (null === $couche->specification) {
                continue;
            }
            if (!$couche->specification->estResolue()) {
                $dependantes[] = $couche;

                continue;
            }
            $materiaux[$couche->ordre] = $this->retenir($couche, [], $avertissements, $erreurs);
        }

        [$isole, $parement] = $this->epaisseursPrevues($couches, $materiaux);
        $contexte = $contexte->avecPlanification($isole, $isole, $parement);
        $reference = ['profondeur_cavite' => $isole, 'epaisseur_isolee' => $isole, 'epaisseur_parement' => $parement];

        foreach ($dependantes as $couche) {
            $materiaux[$couche->ordre] = $this->retenir($couche, $reference, $avertissements, $erreurs);
        }
        if ([] !== $erreurs) {
            return new ResultatPipeline([], $contexte, $avertissements, $erreurs, $materiaux);
        }

        [$contexte, $avertissementsPose, $erreursPose] = $this->pose->planifier($definition, $couches, $materiaux, $parametres, $contexte);
        array_push($avertissements, ...$avertissementsPose);
        if ([] !== $erreursPose) {
            return new ResultatPipeline([], $contexte, $avertissements, $erreursPose, $materiaux);
        }

        // Calcul.
        $lignes = [];
        foreach ($couches as $couche) {
            $emplacement = $emplacements[$couche->emplacement];
            $materiau = $materiaux[$couche->ordre] ?? null;
            $contexteCouche = new CoucheContexte($couche, $emplacement, $materiau, $surface, $parametres);
            $resultat = $this->calculateurs->get($emplacement->calculateur)->calculer($contexteCouche, $contexte);
            array_push($lignes, ...$resultat->lignes);
            array_push($avertissements, ...$resultat->avertissements);
            if ([] !== $resultat->erreurs) {
                return new ResultatPipeline([], $resultat->contexte, $avertissements, $resultat->erreurs, $materiaux);
            }
            $contexte = $resultat->contexte->avecEpaisseurCumulee($resultat->contexte->epaisseurCumulee + ($contexteCouche->epaisseur() ?? 0));
        }

        return new ResultatPipeline($lignes, $contexte, $avertissements, [], $materiaux);
    }

    /**
     * @param array<string, scalar>  $reference
     * @param list<Avertissement>    $avertissements
     * @param list<Avertissement>    $erreurs
     */
    private function retenir(CoucheDecrite $couche, array $reference, array &$avertissements, array &$erreurs): ?MaterielLisible
    {
        $specification = $couche->specification;
        if (null !== $couche->materiau) {
            if (null !== $specification) {
                $resolue = $specification->estResolue() ? $specification : $specification->resoudre($reference);
                if (!$resolue->estSatisfaitePar($couche->materiau)) {
                    $avertissements[] = new Avertissement('pinned_material_mismatch', ['order' => $couche->ordre, 'role' => $couche->role]);
                }
            }

            return $couche->materiau;
        }
        if (null === $specification) {
            return null;
        }
        $materiau = $this->resolveur->resoudre($specification, $reference);
        if (null === $materiau) {
            $erreurs[] = new Avertissement('layer_no_candidate', ['order' => $couche->ordre, 'role' => $couche->role]);
        }

        return $materiau;
    }

    /**
     * @param list<CoucheDecrite>             $couches
     * @param array<int, MaterielLisible|null> $materiaux
     *
     * @return array{int, int} épaisseur isolée prévue, épaisseur de parement prévue
     */
    private function epaisseursPrevues(array $couches, array $materiaux): array
    {
        $isole = 0;
        $parement = 0;
        foreach ($couches as $couche) {
            $materiau = $materiaux[$couche->ordre] ?? $couche->materiau;
            if (null === $materiau) {
                continue;
            }
            $e = $couche->epaisseurOverride ?? $materiau->valeur('epaisseur');
            if (!\is_int($e)) {
                continue;
            }
            if (self::CATEGORIE_ISOLANT === $materiau->categorie()) {
                $isole += $e;
            } elseif (self::CATEGORIE_PAREMENT === $materiau->categorie()) {
                $parement += $e;
            }
        }

        return [$isole, $parement];
    }
}
