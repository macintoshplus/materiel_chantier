<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

use App\Entity\Chiffrage;
use App\Entity\LigneChiffrage;
use App\Enum\TypeLigneChiffrage;

/**
 * Contenu d'une liste de courses d'après les lignes figées d'un chiffrage (jamais le catalogue) : sections (une par
 * pièce ou par fournisseur) découpées en groupes (catégorie ou pièce). Les mêmes données servent avec et sans prix ;
 * seul l'affichage des colonnes de prix change.
 */
final class ListeCourses
{
    /**
     * @return list<SectionExport>
     */
    public function sections(Chiffrage $chiffrage, PorteeExport $portee): array
    {
        return match ($portee->type) {
            TypeExport::Piece => $this->parPiece($chiffrage, $portee),
            TypeExport::Fournisseur => $this->parFournisseur($chiffrage, $portee),
            TypeExport::Projet => $this->projet($chiffrage, $portee),
        };
    }

    public function total(Chiffrage $chiffrage, PorteeExport $portee): int
    {
        return array_sum(array_map(static fn (SectionExport $s): int => $s->total(), $this->sections($chiffrage, $portee)));
    }

    /**
     * @param list<SectionExport> $sections
     */
    public function estPartiel(array $sections): bool
    {
        foreach ($sections as $section) {
            if ($section->aUnPrixManquant()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<SectionExport>
     */
    private function parPiece(Chiffrage $chiffrage, PorteeExport $portee): array
    {
        $parts = [];
        foreach ($chiffrage->lignesDeType(TypeLigneChiffrage::Repartition) as $ligne) {
            $piece = (string) $ligne->getPieceNom();
            if (null === $portee->cible || $piece === $portee->cible) {
                $parts[$piece][] = $ligne;
            }
        }
        ksort($parts);
        $sections = [];
        foreach ($parts as $piece => $lignes) {
            $sections[] = new SectionExport((string) $piece, null, $this->groupes($lignes, Regroupement::Categorie));
        }

        return $sections;
    }

    /**
     * @return list<SectionExport>
     */
    private function parFournisseur(Chiffrage $chiffrage, PorteeExport $portee): array
    {
        $type = null === $portee->piece ? TypeLigneChiffrage::Article : TypeLigneChiffrage::Repartition;
        $parFournisseur = [];
        $adresses = [];
        foreach ($chiffrage->lignesDeType($type) as $ligne) {
            $nom = $ligne->getFournisseurNom() ?? '';
            if ((null !== $portee->cible && $nom !== $portee->cible) || (null !== $portee->piece && $ligne->getPieceNom() !== $portee->piece)) {
                continue;
            }
            $parFournisseur[$nom][] = $ligne;
            $adresses[$nom] = $ligne->getFournisseurAdresse();
        }
        ksort($parFournisseur);
        $livraisons = [];
        foreach ($chiffrage->lignesDeType(TypeLigneChiffrage::Livraison) as $ligne) {
            $livraisons[(string) $ligne->getFournisseurNom()] = $ligne->getTotalCentimes();
        }
        $sections = [];
        foreach ($parFournisseur as $nom => $lignes) {
            $sections[] = new SectionExport(
                '' === $nom ? null : (string) $nom,
                $adresses[$nom] ?? null,
                $this->groupes($lignes, $portee->regroupement),
                null === $portee->piece && isset($livraisons[$nom]) ? [$nom => $livraisons[$nom]] : [],
            );
        }

        return $sections;
    }

    /**
     * @return list<SectionExport>
     */
    private function projet(Chiffrage $chiffrage, PorteeExport $portee): array
    {
        $type = Regroupement::Piece === $portee->regroupement ? TypeLigneChiffrage::Repartition : TypeLigneChiffrage::Article;
        $livraisons = [];
        foreach ($chiffrage->lignesDeType(TypeLigneChiffrage::Livraison) as $ligne) {
            $livraisons[(string) $ligne->getFournisseurNom()] = $ligne->getTotalCentimes();
        }

        return [new SectionExport(null, null, $this->groupes($chiffrage->lignesDeType($type), $portee->regroupement), $livraisons)];
    }

    /**
     * @param list<LigneChiffrage> $lignes
     *
     * @return list<GroupeExport>
     */
    private function groupes(array $lignes, Regroupement $regroupement): array
    {
        $groupes = [];
        foreach ($lignes as $ligne) {
            $cle = Regroupement::Piece === $regroupement ? (string) $ligne->getPieceNom() : (string) $ligne->getCategorie();
            $groupes[$cle][] = $this->item($ligne);
        }
        ksort($groupes);

        return array_map(
            static fn (array $items, string|int $titre): GroupeExport => new GroupeExport((string) $titre, Regroupement::Categorie === $regroupement, $items),
            $groupes,
            array_keys($groupes),
        );
    }

    private function item(LigneChiffrage $ligne): ItemExport
    {
        $part = TypeLigneChiffrage::Repartition === $ligne->getType();
        $dimensions = implode(' x ', array_map(
            static fn (int $mm): string => (string) $mm,
            array_filter([$ligne->getLongueur(), $ligne->getLargeur(), $ligne->getEpaisseur(), $ligne->getHauteur()], static fn (?int $v): bool => null !== $v),
        ));

        return new ItemExport(
            (string) $ligne->getMateriauReference(),
            $ligne->getReferenceFournisseur(),
            (string) $ligne->getMateriauLibelle(),
            $dimensions,
            $part ? $ligne->getQuantiteBesoin() : $ligne->getQuantiteAchetee(),
            $part ? null : $ligne->getNombreLots(),
            $ligne->getPrixLotCentimes(),
            $ligne->getTotalCentimes(),
            $ligne->isPrixManquant(),
            $ligne->getReferenceInitiale(),
        );
    }
}
