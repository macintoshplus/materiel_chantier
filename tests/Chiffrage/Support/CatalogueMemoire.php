<?php

declare(strict_types=1);

namespace App\Tests\Chiffrage\Support;

use App\Chiffrage\Besoin;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\SourcePrix;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;

/**
 * Catalogue en mémoire (sans base) pour tester les stratégies de fournisseur.
 */
final class CatalogueMemoire implements SourcePrix
{
    /** @var list<Fournisseur> */
    private array $fournisseurs = [];

    /** @var array<string, PrixMateriau> */
    private array $prix = [];

    public function fournisseur(string $nom, ?string $livraison = null, ?string $latitude = null, ?string $longitude = null): Fournisseur
    {
        $f = new Fournisseur();
        $f->setNom($nom);
        $f->setFraisLivraisonTtc($livraison);
        $f->setLatitude($latitude);
        $f->setLongitude($longitude);

        return $this->fournisseurs[] = $f;
    }

    public static function article(string $reference): Materiau
    {
        $m = new Materiau();
        $m->setReference($reference);
        $m->setLibelle($reference);
        $m->setCategorie('plaque_platre');

        return $m;
    }

    public function vend(Fournisseur $fournisseur, Materiau $article, string $prixLot, ?int $parLot = null): PrixMateriau
    {
        return $this->prix[$fournisseur->getNom().'|'.$article->getReference()] = new PrixMateriau($article, $fournisseur, $prixLot, new \DateTimeImmutable('2020-01-01'), $parLot);
    }

    public static function besoin(Materiau $article, int $unites): Besoin
    {
        return new Besoin($article, new QuantiteBrute($article, $unites));
    }

    public function fournisseurs(): array
    {
        return $this->fournisseurs;
    }

    public function prix(Materiau $article, Fournisseur $fournisseur, \DateTimeImmutable $date): ?PrixMateriau
    {
        return $this->prix[$fournisseur->getNom().'|'.$article->getReference()] ?? null;
    }
}
