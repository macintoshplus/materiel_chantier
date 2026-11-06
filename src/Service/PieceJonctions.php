<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Entity\Jonction;
use App\Entity\Piece;
use App\Entity\Surface;
use App\Geometrie\Avertissement;
use App\Geometrie\JonctionCloisonDecrite;
use App\Geometrie\ServiceJonctions;

/**
 * Tient à jour les entités `Jonction` d'une pièce : rapproche les jonctions détectées par la bibliothèque
 * (sommets partagés) des jonctions existantes, en conserve l'identité, supprime les obsolètes.
 */
final readonly class PieceJonctions
{
    private ServiceJonctions $service;

    public function __construct()
    {
        $this->service = new ServiceJonctions();
    }

    /**
     * Jonctions entre le sol, les murs et les pans de plafond (les jonctions de cloison ne sont pas touchées).
     *
     * @return list<Avertissement> longueurs d'arêtes jointes différentes
     */
    public function synchroniser(Piece $piece): array
    {
        /** @var array<string, Surface> $surfaces */
        $surfaces = [];
        $sol = $piece->getSol();
        foreach ([...(null === $sol ? [] : [$sol]), ...$piece->getMurs(), ...$piece->getPans()] as $surface) {
            $surfaces[$surface->cle()] = $surface;
        }
        $decrites = $this->service->detecter(array_map(static fn (Surface $s) => $s->geometrie(), $surfaces));

        $index = [];
        foreach ($piece->getJonctions() as $jonction) {
            if (null === $jonction->getSurfaceA()->getCloison()) {
                $index[$jonction->identite()] = $jonction;
            }
        }
        $gardees = [];
        foreach ($decrites as $d) {
            $a = $surfaces[$d->cleA];
            $b = $surfaces[$d->cleB];
            $existante = $index[Jonction::cleIdentite($a, $d->debutA, $d->finA, $b)]
                ?? $index[Jonction::cleIdentite($b, $d->debutB, $d->finB, $a)]
                ?? null;
            if (null === $existante) {
                $existante = new Jonction($piece, $a, $d->debutA, $d->finA, $d->type, $b);
                $existante->definirCoteB($d->debutB, $d->finB);
            } else {
                $existante->definirType($d->type);
            }
            $gardees[spl_object_id($existante)] = true;
        }
        foreach ($index as $jonction) {
            if (!isset($gardees[spl_object_id($jonction)])) {
                $piece->getJonctions()->removeElement($jonction);
            }
        }

        $avertissements = [];
        foreach ($this->service->verifier($decrites) as $avertissement) {
            $avertissements[] = $avertissement->avec([
                'a' => $surfaces[(string) $avertissement->params['a']]->getNom(),
                'b' => $surfaces[(string) $avertissement->params['b']]->getNom(),
            ]);
        }

        return $avertissements;
    }

    /**
     * Retire les jonctions qui touchent une surface (avant sa suppression).
     */
    public function retirerDeLaSurface(Piece $piece, Surface $surface): void
    {
        foreach ($piece->getJonctions()->toArray() as $jonction) {
            if ($jonction->getSurfaceA() === $surface || $jonction->getSurfaceB() === $surface) {
                $piece->getJonctions()->removeElement($jonction);
            }
        }
    }

    /**
     * Remplace les jonctions des deux faces d'une cloison par celles décrites.
     *
     * @param list<JonctionCloisonDecrite> $decrites
     * @param array<string, Surface>       $cibles    surfaces cibles par clé (`cleB` des jonctions décrites)
     */
    public function synchroniserCloison(Cloison $cloison, array $decrites, array $cibles): void
    {
        $piece = $cloison->getPiece();
        $faces = [];
        foreach ($cloison->getFaces() as $face) {
            if (null !== $face->getCote()) {
                $faces[$face->getCote()->value] = $face;
            }
        }
        $index = [];
        foreach ($piece->getJonctions() as $jonction) {
            if ($jonction->getSurfaceA()->getCloison() === $cloison) {
                $index[$jonction->identite()] = $jonction;
            }
        }
        $gardees = [];
        foreach ($decrites as $d) {
            $a = $faces[$d->cote->value] ?? null;
            $b = null === $d->cleB ? null : ($cibles[$d->cleB] ?? null);
            if (null === $a || (null !== $d->cleB && null === $b)) {
                continue;
            }
            $existante = $index[Jonction::cleIdentite($a, $d->debutA, $d->finA, $b)] ?? null;
            if (null === $existante) {
                $existante = new Jonction($piece, $a, $d->debutA, $d->finA, $d->type, $b);
            } else {
                $existante->definirType($d->type);
            }
            $existante->definirCoteB($d->debutB, $d->finB, $d->debutMm, $d->finMm);
            $gardees[spl_object_id($existante)] = true;
        }
        foreach ($index as $jonction) {
            if (!isset($gardees[spl_object_id($jonction)])) {
                $piece->getJonctions()->removeElement($jonction);
            }
        }
    }
}
