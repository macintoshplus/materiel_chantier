<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\BesoinsProjet;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Electricite\BesoinElectrique;
use App\Calcul\Electricite\CheminementCalculator;
use App\Calcul\Electricite\ElementReseau;
use App\Calcul\Electricite\GaineDisponible;
use App\Calcul\Electricite\Liaison;
use App\Calcul\Electricite\PointCheminement;
use App\Calcul\Electricite\QuantitesAnnexes;
use App\Calcul\Electricite\RegistreTypesElements;
use App\Calcul\Electricite\ReseauCalcul;
use App\Calcul\Electricite\ResultatCheminement;
use App\Calcul\Electricite\Section;
use App\Calcul\Materiau\SourceCandidats;
use App\Catalogue\Categorie\ElectriqueGaine;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
use App\Entity\Equipement;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Enum\FamilleEquipement;
use App\Enum\TypeSurface;
use App\Geometrie\Avertissement;
use App\Geometrie\Point2D;

/**
 * Installation électrique d'un projet pour le calcul : positions des équipements dans le repère de leur pièce,
 * cheminement de chaque réseau (`CheminementCalculator`), quantités annexes (`QuantitesAnnexes`) résolues en articles
 * du catalogue, et contrôles. Les besoins sont rendus par pièce, au même format que ceux des systèmes de surface.
 */
final class ElectriciteProjet
{
    /** Hauteur de plafond (mm) supposée quand la pièce n'en définit aucune. */
    public const int HAUTEUR_PLAFOND_DEFAUT = 2500;

    private readonly CheminementCalculator $cheminement;

    public function __construct(
        private readonly RegistreTypesElements $types,
        private readonly RegistreCategories $categories,
        private readonly SourceCandidats $candidats,
        private readonly ControleElectricite $controle,
    ) {
        $this->cheminement = new CheminementCalculator();
    }

    public function analyser(Projet $projet): ResultatElectricite
    {
        $avertissements = [];
        $erreurs = [];
        /** @var array<int, Piece> $pieces */
        $pieces = [];
        /** @var array<int, array<string, ElementReseau>> $parPiece */
        $parPiece = [];
        /** @var array<string, ElementReseau> $elements */
        $elements = [];
        $passages = [];

        foreach ($projet->getPieces() as $piece) {
            $cle = self::clePiece($piece);
            $passages[$cle] = $this->passages($piece);
            foreach ($piece->getEquipements() as $equipement) {
                if (FamilleEquipement::Electrique !== $equipement->getFamille()) {
                    continue;
                }
                if (!$this->types->possede($equipement->getType())) {
                    $avertissements[] = new Avertissement('electrical_unknown_type', ['type' => $equipement->getType()]);

                    continue;
                }
                $element = $this->element($equipement);
                $elements[$element->cle] = $element;
                $pieces[spl_object_id($piece)] = $piece;
                $parPiece[spl_object_id($piece)][$element->cle] = $element;
            }
        }

        $gaines = $this->gaines();
        $cheminements = [];
        foreach ($projet->getReseaux() as $reseau) {
            if (FamilleEquipement::Electrique !== $reseau->getFamille()) {
                continue;
            }
            $calcul = $this->reseau($projet, $reseau, $elements);
            if (null === $calcul) {
                continue;
            }
            $resultat = $this->cheminement->calculer($calcul, $gaines, $passages);
            $cheminements[] = $resultat;
            array_push($avertissements, ...$resultat->avertissements);
            array_push($erreurs, ...$resultat->erreurs);
        }

        $supports = [];
        $specifications = [];
        $pasFixation = self::pasFixation($projet);
        $quantites = new QuantitesAnnexes();
        foreach ($parPiece as $id => $deLaPiece) {
            $piece = $pieces[$id];
            $cles = array_keys($deLaPiece);
            $locaux = array_map(static fn (ResultatCheminement $c): ResultatCheminement => $c->pourEnfants($cles), $cheminements);
            $lignes = [];
            foreach ($quantites->calculer(array_values($deLaPiece), $locaux, $pasFixation) as $besoin) {
                $lignes[] = $this->ligne($besoin, $piece, $specifications, $avertissements);
            }
            $besoins = new BesoinsProjet();
            $besoins->ajouter($lignes, $piece->getNom().' / '.BesoinsSupport::LIBELLE_ELECTRICITE);
            $supports[] = new BesoinsSupport($piece, null, $besoins);
        }

        array_push($avertissements, ...$this->controle->controler($projet));

        return new ResultatElectricite($supports, $specifications, $cheminements, self::uniques($avertissements), self::uniques($erreurs));
    }

    /**
     * Position d'un équipement dans le repère de sa pièce.
     */
    public function point(Equipement $equipement): PointCheminement
    {
        $piece = $equipement->getPiece();
        $p = $equipement->getSurface()->getPlan()->versPiece(new Point2D($equipement->getU(), $equipement->getV()));

        return new PointCheminement(self::clePiece($piece), $p->x, $p->y, $p->z, $this->hauteurPlafond($piece, new Point2D($p->x, $p->y)));
    }

    /**
     * Clé d'un équipement dans le domaine : `e` + son identifiant (jamais un entier pur, qui deviendrait un indice de tableau), ou à défaut `o` + celle de l'objet (pas encore enregistré).
     */
    public static function cle(Equipement $equipement): string
    {
        return null === $equipement->getId() ? 'o'.spl_object_id($equipement) : 'e'.$equipement->getId();
    }

    private static function clePiece(Piece $piece): string
    {
        return 'p'.spl_object_id($piece);
    }

    private function hauteurPlafond(Piece $piece, Point2D $point): int
    {
        return $piece->hauteurA($point) ?? $piece->getHauteurSousPlafond() ?? self::HAUTEUR_PLAFOND_DEFAUT;
    }

    private function element(Equipement $equipement): ElementReseau
    {
        $type = $this->types->get($equipement->getType());
        $creuse = null !== $equipement->getSurface()->getCloison() || null !== $this->controle->entraxeOssature($equipement->getSurface());

        return new ElementReseau(
            self::cle($equipement),
            $equipement->getNom() ?? $type->libelle,
            $type,
            $this->point($equipement),
            $creuse ? 'cloison_creuse' : 'encastrement',
        );
    }

    /**
     * Haut de chaque porte de la pièce, dans son repère.
     *
     * @return list<PointCheminement>
     */
    private function passages(Piece $piece): array
    {
        $passages = [];
        foreach ($piece->getSurfaces() as $surface) {
            if (TypeSurface::Mur !== $surface->getType()) {
                continue;
            }
            foreach ($surface->getOuvertures() as $ouverture) {
                if ('porte' !== $ouverture->getType()) {
                    continue;
                }
                $p = $surface->getPlan()->versPiece(new Point2D($ouverture->getU() + intdiv($ouverture->getLargeur(), 2), $ouverture->getV() + $ouverture->getHauteur()));
                $passages[] = new PointCheminement(self::clePiece($piece), $p->x, $p->y, $p->z, $this->hauteurPlafond($piece, new Point2D($p->x, $p->y)));
            }
        }

        return $passages;
    }

    /**
     * @param array<string, ElementReseau> $elements
     */
    private function reseau(Projet $projet, Reseau $reseau, array $elements): ?ReseauCalcul
    {
        $membres = [];
        foreach ($projet->getPieces() as $piece) {
            foreach ($piece->getEquipements() as $equipement) {
                if ($equipement->getReseau() === $reseau && isset($elements[self::cle($equipement)])) {
                    $membres[self::cle($equipement)] = $elements[self::cle($equipement)];
                }
            }
        }
        if ([] === $membres) {
            return null;
        }
        $liaisons = [];
        foreach ($reseau->getLiaisons() as $l) {
            $liaisons[] = new Liaison('e'.$l['equipement'], 'e'.$l['parent'], $l['conducteurs']);
        }
        // Le point de départ peut être un équipement hors réseau (le tableau) : il est ajouté s'il existe.
        $depart = null === $reseau->getDepart() ? '' : 'e'.$reseau->getDepart();
        if ('' !== $depart && !isset($membres[$depart]) && isset($elements[$depart])) {
            $membres[$depart] = $elements[$depart];
        }
        $parametres = $projet->getParametres()['electricite'] ?? [];
        $reserve = $reseau->getReservePct()
            ?? (\is_array($parametres) && is_numeric($parametres['reserve_pct'] ?? null) ? (int) $parametres['reserve_pct'] : ReseauCalcul::RESERVE_DEFAUT_PCT);

        return new ReseauCalcul($reseau->getNom(), $depart, $membres, $liaisons, Section::centiemes($reseau->getSection()), $reserve);
    }

    private static function pasFixation(Projet $projet): int
    {
        $parametres = $projet->getParametres()['electricite'] ?? [];

        return \is_array($parametres) && is_numeric($parametres['pas_fixation_mm'] ?? null) && (int) $parametres['pas_fixation_mm'] > 0
            ? (int) $parametres['pas_fixation_mm']
            : QuantitesAnnexes::PAS_FIXATION_DEFAUT_MM;
    }

    /**
     * Gaines du catalogue qui décrivent leurs nombres maximaux de conducteurs.
     *
     * @return list<GaineDisponible>
     */
    private function gaines(): array
    {
        $gaines = [];
        $specification = MaterielSpecification::creer('conduit', new ElectriqueGaine(), []);
        foreach ($this->candidats->candidats($specification) as $article) {
            $diametre = $article->valeur('diametre');
            $liste = $article->valeur('conducteurs_max');
            if (!is_numeric($diametre) || !\is_array($liste)) {
                continue;
            }
            $max = [];
            foreach ($liste as $ligne) {
                if (\is_array($ligne) && is_scalar($ligne['section'] ?? null) && is_numeric($ligne['nombre'] ?? null)) {
                    $max[Section::centiemes((string) $ligne['section'])] = (int) $ligne['nombre'];
                }
            }
            $taux = $article->valeur('taux_remplissage_max');
            $gaines[] = new GaineDisponible((int) $diametre, is_numeric($taux) ? (int) $taux : 0, $max);
        }

        return $gaines;
    }

    /**
     * @param array<int, MaterielSpecification> $specifications
     * @param list<Avertissement>               $avertissements
     */
    private function ligne(BesoinElectrique $besoin, Piece $piece, array &$specifications, array &$avertissements): LigneBesoin
    {
        $criteres = [];
        foreach ($besoin->criteres as $cle => $valeur) {
            $criteres[] = new Critere($cle, Operateur::Egal, $valeur);
        }
        $specification = MaterielSpecification::creer($besoin->cle, $this->categories->get($besoin->categorie), $criteres);
        $article = $this->candidats->candidats($specification)[0] ?? null;
        $cle = $besoin->categorie.'.'.$besoin->cle;
        if (null === $article) {
            $avertissements[] = new Avertissement('electrical_no_candidate', ['category' => $besoin->categorie, 'criteria' => self::decrire($besoin->criteres)]);
        } else {
            $specifications[spl_object_id($article)] = $specification;
        }

        return new LigneBesoin($cle, $piece->getNom(), $besoin->categorie, $besoin->type, $besoin->quantite, materiau: $article);
    }

    /**
     * @param array<string, int|string> $criteres
     */
    private static function decrire(array $criteres): string
    {
        $morceaux = [];
        foreach ($criteres as $cle => $valeur) {
            $morceaux[] = $cle.'='.$valeur;
        }

        return implode(', ', $morceaux);
    }

    /**
     * @param list<Avertissement> $liste
     *
     * @return list<Avertissement>
     */
    private static function uniques(array $liste): array
    {
        $uniques = [];
        foreach ($liste as $a) {
            $uniques[json_encode([$a->code, $a->params], \JSON_THROW_ON_ERROR)] = $a;
        }

        return array_values($uniques);
    }
}
