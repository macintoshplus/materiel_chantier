<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\EmplacementCouche;
use App\Calcul\Systeme\RegistreSystemes;
use App\Entity\Cloison;
use App\Entity\CoucheAppliquee;
use App\Entity\Materiau;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\CoteCloison;
use App\Enum\TypeSurface;
use App\Form\Dto\CoucheSaisie;
use App\Form\Dto\SystemeSaisie;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création, modification et suppression des systèmes appliqués à une surface ou à une cloison depuis l'écran de
 * saisie. La première variante d'un support est activée d'office ; modifier la variante active d'une cloison
 * recalcule son épaisseur (même chemin que l'activation).
 */
final class SystemeEditionService
{
    public function __construct(
        private readonly RegistreSystemes $registre,
        private readonly EntityManagerInterface $em,
        private readonly ServiceVariantes $variantes,
        private readonly SpecificationsEmplacement $specifications,
        private readonly SpecificationSerialiseur $serialiseur,
    ) {
    }

    /**
     * Définitions du registre applicables au support (un mur, un pan ou une face de cloison).
     *
     * @return list<DefinitionSysteme>
     */
    public function definitionsCompatibles(Surface|Cloison $support): array
    {
        $type = $support instanceof Cloison ? TypeSurface::CloisonFace : $support->getType();

        return array_values(array_filter(
            $this->registre->toutes(),
            static fn (DefinitionSysteme $d): bool => \in_array($type, $d->typesSurface(), true),
        ));
    }

    /**
     * Saisie initiale : paramètres vides (le moteur applique ses valeurs par défaut) et le nombre minimal de couches
     * de chaque emplacement (deux parements, un par face, pour une cloison), non épinglées.
     */
    public function initiale(DefinitionSysteme $definition, Surface|Cloison $support): SystemeSaisie
    {
        $saisie = new SystemeSaisie();
        $saisie->variante = 'V'.($support->getSystemes()->count() + 1);
        foreach ($definition->parametres() as $parametre) {
            $saisie->parametres[$parametre->nom] = null;
        }
        foreach ($definition->emplacements() as $emplacement) {
            $saisie->couches[$emplacement->code] = [];
            // Une cloison a deux faces : un parement par face d'emblée (un parement manquant sur une face est une erreur de calcul).
            $parFace = $support instanceof Cloison && 'parement' === $emplacement->role;
            $nombre = $parFace ? min($emplacement->max, max($emplacement->min, 2)) : $emplacement->min;
            for ($i = 0; $i < $nombre; ++$i) {
                $couche = new CoucheSaisie();
                if ($parFace) {
                    $couche->face = 0 === $i ? CoteCloison::A->value : CoteCloison::B->value;
                }
                $saisie->couches[$emplacement->code][] = $couche;
            }
        }

        return $saisie;
    }

    public function depuis(SystemeApplique $systeme): SystemeSaisie
    {
        $definition = $this->registre->get($systeme->getDefinition());
        $saisie = new SystemeSaisie();
        $saisie->variante = $systeme->getVariante();
        foreach ($definition->parametres() as $parametre) {
            $saisie->parametres[$parametre->nom] = $systeme->getParametres()[$parametre->nom] ?? null;
        }
        foreach ($definition->emplacements() as $emplacement) {
            $saisie->couches[$emplacement->code] = [];
        }
        foreach ($systeme->getCouches() as $couche) {
            if (!isset($saisie->couches[$couche->getEmplacement()])) {
                continue;
            }
            $ligne = new CoucheSaisie();
            $ligne->materiau = $couche->getMateriau()?->getId();
            $ligne->orientation = $couche->getOrientation();
            $ligne->position = $couche->getPosition();
            $ligne->face = $couche->getFace()?->value;
            $ligne->decalage = $couche->getDecalage()['u'] ?? null;
            $ligne->epaisseurOverride = $couche->getEpaisseurOverride();
            $saisie->couches[$couche->getEmplacement()][] = $ligne;
        }

        return $saisie;
    }

    /**
     * Articles proposés pour épingler chaque emplacement (libellé => identifiant) : ceux du catalogue qui satisfont
     * la spécification, plus les articles déjà épinglés dans la saisie (même devenus inactifs).
     *
     * @return array<string, array<string, int>>
     */
    public function articlesParEmplacement(DefinitionSysteme $definition, SystemeSaisie $saisie): array
    {
        $choix = [];
        foreach ($definition->emplacements() as $emplacement) {
            $articles = $this->specifications->candidats($emplacement);
            $ids = array_map(static fn (Materiau $m): ?int => $m->getId(), $articles);
            foreach ($saisie->couches[$emplacement->code] ?? [] as $ligne) {
                $article = null !== $ligne->materiau && !\in_array($ligne->materiau, $ids, true) ? $this->em->find(Materiau::class, $ligne->materiau) : null;
                if ($article instanceof Materiau) {
                    $articles[] = $article;
                    $ids[] = $article->getId();
                }
            }
            $choix[$emplacement->code] = [];
            foreach ($articles as $article) {
                $choix[$emplacement->code][$article->getReference().' - '.$article->getLibelle()] = (int) $article->getId();
            }
        }

        return $choix;
    }

    public function creer(Surface|Cloison $support, DefinitionSysteme $definition, SystemeSaisie $saisie): SystemeApplique
    {
        $premier = $support->getSystemes()->isEmpty();
        $systeme = $support instanceof Surface
            ? new SystemeApplique($definition->code(), $support)
            : new SystemeApplique($definition->code(), null, $support);
        $this->remplir($systeme, $definition, $saisie);
        $this->em->persist($systeme);
        $this->em->flush();
        if ($premier) {
            $this->variantes->activer($systeme);
        }

        return $systeme;
    }

    public function modifier(SystemeApplique $systeme, SystemeSaisie $saisie): void
    {
        $this->remplir($systeme, $this->registre->get($systeme->getDefinition()), $saisie);
        $this->em->flush();
        if ($systeme->isActif()) {
            $this->variantes->activer($systeme);
        }
    }

    /**
     * Supprime un système ; retourne vrai si c'était la variante active et qu'il en reste d'autres (aucune n'est alors active).
     */
    public function supprimer(SystemeApplique $systeme): bool
    {
        $support = $systeme->getSurface() ?? $systeme->getCloison() ?? throw new \LogicException('The system has no support.');
        $support->getSystemes()->removeElement($systeme);
        $this->em->remove($systeme);
        $this->em->flush();

        return $systeme->isActif() && !$support->getSystemes()->isEmpty();
    }

    private function remplir(SystemeApplique $systeme, DefinitionSysteme $definition, SystemeSaisie $saisie): void
    {
        $systeme->setVariante(trim($saisie->variante ?? ''));
        $parametres = [];
        foreach ($definition->parametres() as $schema) {
            $valeur = $saisie->parametres[$schema->nom] ?? null;
            if (null === $valeur || '' === $valeur || (false === $valeur && 'bool' === $schema->type)) {
                continue;
            }
            $parametres[$schema->nom] = $valeur;
        }
        $systeme->setParametres($parametres);

        $systeme->getCouches()->clear();
        $ordre = 0;
        foreach ($definition->emplacements() as $emplacement) {
            foreach ($saisie->couches[$emplacement->code] ?? [] as $ligne) {
                $this->ajouterCouche($systeme, $emplacement, $ligne, $ordre++);
            }
        }
    }

    private function ajouterCouche(SystemeApplique $systeme, EmplacementCouche $emplacement, CoucheSaisie $ligne, int $ordre): void
    {
        $couche = new CoucheAppliquee($systeme, $ordre, $emplacement->role, $emplacement->code);
        $couche->setSpecification($this->serialiseur->vers($this->specifications->pour($emplacement)));
        $couche->setMateriau(null === $ligne->materiau ? null : $this->em->find(Materiau::class, $ligne->materiau));
        $couche->setOrientation($ligne->orientation ?? $emplacement->orientations[0] ?? 0);
        $couche->setPosition($ligne->position ?? $this->positionUnique($emplacement));
        $couche->setFace(null === $ligne->face ? null : CoteCloison::tryFrom($ligne->face));
        $couche->setDecalage(null === $ligne->decalage ? null : ['u' => $ligne->decalage]);
        $couche->setEpaisseurOverride($ligne->epaisseurOverride);
    }

    private function positionUnique(EmplacementCouche $emplacement): ?string
    {
        return 1 === \count($emplacement->positions) ? $emplacement->positions[0]->value : null;
    }
}
