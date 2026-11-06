<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\SourceMesures;
use App\Enum\TypePose;
use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Geometrie\Segment;
use App\Service\ControlePlacement;
use App\Service\OrdreSurfaces;
use App\Service\PieceCreationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée une pièce depuis un `PlanImporte` et un `ChoixImport`, quelle que soit la source (DXF, CSV, côtés et angles) :
 * contour choisi (décalé vers l'intérieur selon le mode), sol/murs/plafond via `PieceCreationService`, blocs de porte
 * et de fenêtre posés comme ouvertures sur le mur le plus proche, équipements conservés dans le rapport. Tout est fait
 * dans une transaction : en cas d'erreur, rien n'est enregistré.
 */
final readonly class PieceImportService
{
    /** Distance maximale (mm) entre un bloc et un mur, en plus de l'épaisseur du mur. */
    public const DISTANCE_BLOC_MUR = 300;

    private const PAR_DEFAUT = [
        'porte' => ['largeur' => 900, 'hauteur' => 2040, 'allege' => 0],
        'fenetre' => ['largeur' => 1200, 'hauteur' => 1200, 'allege' => 900],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private PieceCreationService $creation,
        private ControlePlacement $controle,
    ) {
    }

    /**
     * @throws ErreurGeometrique
     */
    public function creer(Projet $projet, PlanImporte $plan, ChoixImport $choix, SourceMesures $source): ResultatImport
    {
        $contour = null === $choix->contour ? null : ($plan->contours[$choix->contour] ?? null);
        if (null === $contour) {
            throw new ErreurGeometrique('import_contour_unknown');
        }
        if (RoleImport::Ignorer === ($choix->calques[$contour->calque] ?? null)) {
            throw new ErreurGeometrique('import_contour_unknown');
        }
        if (null !== $contour->rejet) {
            throw new ErreurGeometrique($contour->rejet->code, $contour->rejet->params);
        }
        if (!$contour->selectionnable()) {
            throw new ErreurGeometrique('import_contour_unknown');
        }
        if ($choix->hauteurSousPlafond <= 0) {
            throw new ErreurGeometrique('import_height_invalid', [], 'hauteurSousPlafond');
        }
        if ($choix->epaisseurMur < 0 || (ModeContour::Interieur !== $choix->mode && $choix->epaisseurMur <= 0)) {
            throw new ErreurGeometrique('import_offset_invalid');
        }

        $points = $contour->points;
        if (!(new Polygone($points))->sensAntiHoraire()) {
            $points = array_reverse($points);
        }
        $points = (new DecalageContour())->vers($points, $choix->decalage());
        $boite = (new Polygone($points))->boiteEnglobante();
        $translation = new Point2D(-$boite->minX, -$boite->minY);
        $points = array_map(static fn (Point2D $p): Point2D => $p->plus($translation), $points);

        $piece = new Piece($projet);
        try {
            $piece->setNom('' !== trim($choix->nom) ? trim($choix->nom) : 'Room '.$projet->getPieces()->count());
            $piece->setHauteurSousPlafond($choix->hauteurSousPlafond);
            $piece->setSourceMesures($source);
            $piece->setFormeSol(array_map(static fn (Point2D $p): array => ['x' => $p->x, 'y' => $p->y], $points));

            /** @var ResultatImport $resultat */
            $resultat = $this->em->wrapInTransaction(function () use ($piece, $plan, $choix, $translation): ResultatImport {
                $avertissements = $this->creation->generer($piece);
                [$ouvertures, $equipements, $avisBlocs] = $this->blocs($piece, $plan, $choix, $translation);
                array_push($avertissements, ...$avisBlocs);
                foreach (OrdreSurfaces::murs($piece) as $mur) {
                    array_push($avertissements, ...$this->controle->pourSurface($mur));
                }
                $this->em->persist($piece);
                $this->em->flush();

                return new ResultatImport($piece, $avertissements, $equipements, $ouvertures);
            });
        } catch (\Throwable $e) {
            $projet->getPieces()->removeElement($piece);
            throw $e;
        }

        $ignores = [];
        foreach ($plan->rapport->ignores as $type => $nombre) {
            $ignores[] = new Avertissement('import_ignored_entities', ['type' => $type, 'count' => $nombre]);
        }

        return new ResultatImport(
            $resultat->piece,
            [...$plan->rapport->avertissements, ...$ignores, ...$resultat->avertissements],
            $resultat->equipements,
            $resultat->ouvertures,
        );
    }

    /**
     * @return array{int, list<BlocImporte>, list<Avertissement>}
     */
    private function blocs(Piece $piece, PlanImporte $plan, ChoixImport $choix, Point2D $translation): array
    {
        $avis = [];
        $equipements = [];
        $numeros = ['porte' => 0, 'fenetre' => 0];
        $murs = OrdreSurfaces::murs($piece);
        $sol = $piece->getSol();
        $cree = 0;
        foreach ($plan->blocs as $index => $bloc) {
            $role = $choix->roleDuBloc($index, $bloc);
            if (RoleImport::Ignorer === $role || RoleImport::Contour === $role) {
                continue;
            }
            if (RoleImport::Equipement === $role) {
                $equipements[] = $bloc;
                $avis[] = new Avertissement('import_equipment_kept', ['block' => $bloc->nom, 'layer' => $bloc->calque]);
                continue;
            }
            $type = RoleImport::Porte === $role ? 'porte' : 'fenetre';
            $libelleRole = $type;
            $centre = $bloc->centre->plus($translation);
            // Un symbole de porte (battement inclus) a son centre loin du mur : son point d'insertion compte aussi.
            $insertion = $bloc->insertion->plus($translation);
            $choisi = null;
            $distance = INF;
            $segment = null;
            if (null !== $sol) {
                foreach ($murs as $mur) {
                    $arete = $mur->getAreteSol();
                    if (null === $arete) {
                        continue;
                    }
                    $candidat = new Segment($sol->getContour()->point($arete['fin']), $sol->getContour()->point($arete['debut']));
                    $d = min($candidat->distanceAuPoint($centre), $candidat->distanceAuPoint($insertion));
                    if ($d < $distance) {
                        $distance = $d;
                        $choisi = $mur;
                        $segment = $candidat;
                    }
                }
            }
            if (null === $choisi || null === $segment || $distance > self::DISTANCE_BLOC_MUR + $choix->epaisseurMur) {
                $avis[] = new Avertissement('import_block_not_on_wall', ['role' => $libelleRole, 'block' => $bloc->nom]);
                continue;
            }
            $defauts = self::PAR_DEFAUT[$type];
            $largeur = $bloc->attributMm(['WIDTH', 'LARGEUR', 'L']) ?? $bloc->largeur ?? $defauts['largeur'];
            $hauteur = $bloc->attributMm(['HEIGHT', 'HAUTEUR', 'H']) ?? $defauts['hauteur'];
            $allege = 'porte' === $type ? 0 : ($bloc->attributMm(['SILL', 'ALLEGE']) ?? $defauts['allege']);
            if ($largeur <= 0 || $hauteur <= 0 || $largeur > $segment->longueur()) {
                $avis[] = new Avertissement('import_block_too_wide', ['role' => $libelleRole, 'block' => $bloc->nom]);
                continue;
            }
            $u = max(0, min($segment->longueur() - $largeur, $segment->positionDe($centre) - intdiv($largeur, 2)));
            $ouverture = $this->ouverture($choisi, $type, ++$numeros[$type], $u, $allege, $largeur, $hauteur);
            $ouverture->setTypePose(TypePose::Applique);
            ++$cree;
        }
        if ($cree > 0) {
            $avis[] = new Avertissement('import_opening_created', ['count' => $cree]);
        }

        return [$cree, $equipements, $avis];
    }

    private function ouverture(Surface $mur, string $type, int $numero, int $u, int $v, int $largeur, int $hauteur): Ouverture
    {
        $ouverture = new Ouverture($mur);
        $ouverture->setType($type);
        $ouverture->setNom(('porte' === $type ? 'Door ' : 'Window ').$numero);
        $ouverture->definirRectangle($u, $v, $largeur, $hauteur);

        return $ouverture;
    }
}
