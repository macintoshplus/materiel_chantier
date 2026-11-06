<?php

declare(strict_types=1);

namespace App\Calcul\Pose;

use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\ParametreSysteme;
use App\Catalogue\MaterielLisible;
use App\Catalogue\Vue\PlaqueView;
use App\Geometrie\Avertissement;

/**
 * Phase de planification des paramètres de pose : entraxe, pas de suspente, pas de vis de chaque peau et
 * pénétration de vis. Priorité de `PrioritePose` : surcharge de l'utilisateur (présente dans `parametres`) >
 * min(défaut du système, limites des articles). N'agit que sur les paramètres que la définition déclare, donc
 * pas sur les systèmes qui n'en déclarent aucun (cloison, doublage).
 */
final class PlanificateurPose
{
    public const int PENETRATION_DEFAUT = 10;

    /**
     * @param list<CoucheDecrite>              $couches
     * @param array<int, MaterielLisible|null> $materiaux matériau retenu par ordre de couche
     * @param array<string, scalar|null>       $parametres surcharges de l'utilisateur
     *
     * @return array{ContexteCouches, list<Avertissement>, list<Avertissement>} contexte, avertissements, erreurs
     */
    public function planifier(DefinitionSysteme $definition, array $couches, array $materiaux, array $parametres, ContexteCouches $contexte): array
    {
        $defauts = [];
        foreach ($definition->parametres() as $p) {
            $defauts[$p->nom] = $p;
        }
        if (!isset($defauts['entraxe'])) {
            return [$contexte, [], []];
        }

        $avertissements = [];
        $erreurs = [];
        $plaques = [];
        $suspentes = [];
        foreach ($couches as $couche) {
            $m = $materiaux[$couche->ordre] ?? $couche->materiau;
            if (null === $m) {
                continue;
            }
            if ('plaque_platre' === $m->categorie()) {
                $plaques[$couche->ordre] = $m;
            } elseif ('suspente' === $m->categorie()) {
                $suspentes[] = $m;
            }
        }

        $entraxe = PrioritePose::resoudre(
            'entraxe',
            $this->surcharge($parametres, 'entraxe'),
            $this->defaut($defauts, 'entraxe'),
            $this->limites($plaques, 'pose.entraxe_max'),
        );
        $this->consigner($entraxe, $avertissements, $erreurs);

        $pasSuspente = null;
        if (isset($defauts['pas_suspente'])) {
            $r = PrioritePose::resoudre('pas_suspente', $this->surcharge($parametres, 'pas_suspente'), $this->defaut($defauts, 'pas_suspente'), $this->limites($suspentes, 'pas'));
            $this->consigner($r, $avertissements, $erreurs);
            $pasSuspente = $r->valeur;
        }

        $penetration = self::PENETRATION_DEFAUT;
        if (isset($defauts['penetration_vis'])) {
            $r = PrioritePose::resoudre('penetration_vis', $this->surcharge($parametres, 'penetration_vis'), $this->defaut($defauts, 'penetration_vis'), []);
            $this->consigner($r, $avertissements, $erreurs);
            $penetration = $r->valeur ?? self::PENETRATION_DEFAUT;
        }

        $pasVis = [];
        $ordres = array_keys($plaques);
        foreach ($ordres as $rang => $ordre) {
            $pose = (new PlaqueView($plaques[$ordre]))->pose();
            $premiere = 0 === $rang;
            $derniere = $rang === \count($ordres) - 1;
            [$nom, $article] = match (true) {
                $premiere && $derniere => ['pas_vis_peau_unique', $pose->pasVisPeauUnique],
                $premiere => ['pas_vis_premiere_peau', $pose->pasVisPremierePeau],
                default => ['pas_vis_derniere_peau', $pose->pasVisDernierePeau],
            };
            $r = PrioritePose::resoudre($nom, $this->surcharge($parametres, $nom), $this->defaut($defauts, $nom), [$article]);
            $this->consigner($r, $avertissements, $erreurs);
            if (null !== $r->valeur) {
                $pasVis[$ordre] = $r->valeur;
            }
        }

        return [$contexte->avecPose($entraxe->valeur, $pasSuspente, $pasVis, $penetration), $avertissements, $erreurs];
    }

    /**
     * @param array<string, scalar|null> $parametres
     */
    private function surcharge(array $parametres, string $nom): ?int
    {
        $v = $parametres[$nom] ?? null;

        return \is_int($v) ? $v : null;
    }

    /**
     * @param array<string, ParametreSysteme> $defauts
     */
    private function defaut(array $defauts, string $nom): ?int
    {
        $d = ($defauts[$nom] ?? null)?->defaut;

        return \is_int($d) ? $d : null;
    }

    /**
     * @param iterable<MaterielLisible> $articles
     *
     * @return list<int>
     */
    private function limites(iterable $articles, string $chemin): array
    {
        $limites = [];
        foreach ($articles as $article) {
            $v = $article->valeur($chemin);
            if (\is_int($v) && $v > 0) {
                $limites[] = $v;
            }
        }

        return $limites;
    }

    /**
     * @param list<Avertissement> $avertissements
     * @param list<Avertissement> $erreurs
     */
    private function consigner(ValeurPose $valeur, array &$avertissements, array &$erreurs): void
    {
        array_push($avertissements, ...$valeur->avertissements);
        if (null !== $valeur->erreur) {
            $erreurs[] = $valeur->erreur;
        }
    }
}
