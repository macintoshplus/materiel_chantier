<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Catalogue\BandeAJointArmee;
use App\Model\Catalogue\BandeAJointPlat;
use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Catalogue\Rail;
use App\Repository\FournisseurRepository;
use App\Repository\MateriauRepository;

/**
 * Construit les objets `Model\Catalogue` utilisés par les calculateurs actuels à partir du catalogue en base
 * (articles par défaut et prix du fournisseur « Défaut »). Adaptateur de transition.
 */
final class CatalogueHeritage
{
    public function __construct(
        private readonly MateriauRepository $materiaux,
        private readonly FournisseurRepository $fournisseurs,
        private readonly PrixService $prix,
    ) {
    }

    /**
     * @return array{montant: Montant, rail: Rail, appuisIntermediaire: AppuisIntermediaire, bandeAJoinPlat: BandeAJointPlat, bandeAJoinArmee: BandeAJointArmee, plaquePlatre: PlaquePlatre}
     */
    public function charger(\DateTimeImmutable $date): array
    {
        $montantArticle = $this->article(ImportateurDefauts::MONTANT);
        [$montantPrix, $montantLot] = $this->prixEtLot($montantArticle, $date);
        $montant = new Montant();
        $montant->setLongueur($this->dimension($montantArticle->getLongueur(), 'longueur'));
        $montant->setLargeur($this->dimension($montantArticle->getLargeur(), 'largeur'));
        $entraxe = $montantArticle->valeur('pose.entraxe_defaut');
        $montant->setEntraxe(\is_int($entraxe) ? $entraxe : throw new \LogicException('The default stud has no default spacing.'));
        $montant->setPrix($montantPrix);
        $montant->setUniteParLot($montantLot);

        $railArticle = $this->article(ImportateurDefauts::RAIL);
        [$railPrix, $railLot] = $this->prixEtLot($railArticle, $date);
        $rail = new Rail();
        $rail->setLongueur($this->dimension($railArticle->getLongueur(), 'longueur'));
        $rail->setLargeur($this->dimension($railArticle->getLargeur(), 'largeur'));
        $rail->setPrix($railPrix);
        $rail->setUniteParLot($railLot);

        $appuisArticle = $this->article(ImportateurDefauts::APPUIS);
        [$appuisPrix, $appuisLot] = $this->prixEtLot($appuisArticle, $date);
        $appuis = new AppuisIntermediaire();
        $appuis->setPrix($appuisPrix);
        $appuis->setUniteParLot($appuisLot);

        $platArticle = $this->article(ImportateurDefauts::JOINT_PLAT);
        [$platPrix, $platLot] = $this->prixEtLot($platArticle, $date);
        $joinPlat = new BandeAJointPlat();
        $joinPlat->setLongueur($this->dimension($platArticle->getLongueur(), 'longueur'));
        $joinPlat->setPrix($platPrix);
        $joinPlat->setUniteParLot($platLot);

        $armeeArticle = $this->article(ImportateurDefauts::JOINT_ARMEE);
        [$armeePrix, $armeeLot] = $this->prixEtLot($armeeArticle, $date);
        $joinArmee = new BandeAJointArmee();
        $joinArmee->setLongueur($this->dimension($armeeArticle->getLongueur(), 'longueur'));
        $joinArmee->setPrix($armeePrix);
        $joinArmee->setUniteParLot($armeeLot);

        $plaqueArticle = $this->article(ImportateurDefauts::PLAQUE);
        [$plaquePrix, $plaqueLot] = $this->prixEtLot($plaqueArticle, $date);
        $plaque = new PlaquePlatre();
        $plaque->setLongueur($this->dimension($plaqueArticle->getLongueur(), 'longueur'));
        $plaque->setLargeur($this->dimension($plaqueArticle->getLargeur(), 'largeur'));
        $plaque->setType(PlaquePlatre::TYPE_STD);
        $plaque->setPrix($plaquePrix);
        $plaque->setUniteParLot($plaqueLot);

        return [
            'montant' => $montant,
            'rail' => $rail,
            'appuisIntermediaire' => $appuis,
            'bandeAJoinPlat' => $joinPlat,
            'bandeAJoinArmee' => $joinArmee,
            'plaquePlatre' => $plaque,
        ];
    }

    private function article(string $reference): Materiau
    {
        return $this->materiaux->parReference($reference)
            ?? throw new \LogicException(\sprintf('Article "%s" not found: run "app:catalogue:importer-defauts".', $reference));
    }

    private function dimension(?int $valeur, string $nom): int
    {
        return $valeur ?? throw new \LogicException(\sprintf('The article has no %s.', $nom));
    }

    /**
     * @return array{0: float, 1: int}
     */
    private function prixEtLot(Materiau $materiau, \DateTimeImmutable $date): array
    {
        $fournisseur = $this->fournisseurs->parNom(ImportateurDefauts::FOURNISSEUR)
            ?? throw new \LogicException('Supplier not found: run "app:catalogue:importer-defauts".');
        $prix = $this->prix->prixA($materiau, $fournisseur, $date);
        if (!$prix instanceof PrixMateriau) {
            throw new \LogicException(\sprintf('No price for article "%s".', $materiau->getReference()));
        }

        return [(float) $prix->getPrixLotTtc(), $prix->unitesParLotEffectif()];
    }
}
