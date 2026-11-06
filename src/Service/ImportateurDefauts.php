<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use App\Repository\FournisseurRepository;
use App\Repository\MateriauRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée les articles correspondant aux objets autrefois codés en dur dans le contrôleur, et le fournisseur
 * « Défaut » portant leurs prix. Idempotent : ce qui existe déjà n'est pas recréé.
 */
final class ImportateurDefauts
{
    public const string FOURNISSEUR = 'Défaut';

    public const string MONTANT = 'DEFAUT-MONTANT-3000';
    public const string RAIL = 'DEFAUT-RAIL-3000';
    public const string PLAQUE = 'DEFAUT-PLAQUE-2500X1200';
    public const string JOINT_PLAT = 'DEFAUT-JOINT-PLAT-75000';
    public const string JOINT_ARMEE = 'DEFAUT-JOINT-ARMEE-30000';
    public const string APPUIS = 'DEFAUT-APPUIS';

    /**
     * @return list<array{reference: string, libelle: string, categorie: string, longueur: ?int, largeur: ?int, epaisseur: ?int, unite_vente: string, unites_par_lot: int, caracteristiques: array<string, mixed>, prix: string}>
     */
    public static function articles(): array
    {
        return [
            ['reference' => self::MONTANT, 'libelle' => 'Montant 3 m', 'categorie' => 'profil_ossature', 'longueur' => 3000, 'largeur' => 60, 'epaisseur' => null,
                'unite_vente' => 'piece', 'unites_par_lot' => 1, 'caracteristiques' => ['famille' => 'montant', 'pose' => ['entraxe_defaut' => 600]], 'prix' => '3.90'],
            ['reference' => self::RAIL, 'libelle' => 'Rail 3 m', 'categorie' => 'profil_ossature', 'longueur' => 3000, 'largeur' => 45, 'epaisseur' => null,
                'unite_vente' => 'piece', 'unites_par_lot' => 1, 'caracteristiques' => ['famille' => 'rail'], 'prix' => '3.80'],
            ['reference' => self::PLAQUE, 'libelle' => 'Plaque de plâtre 2,5 x 1,2 m', 'categorie' => 'plaque_platre', 'longueur' => 2500, 'largeur' => 1200, 'epaisseur' => 13,
                'unite_vente' => 'piece', 'unites_par_lot' => 1, 'caracteristiques' => ['type' => 'standard', 'pose' => ['entraxe_max' => 600, 'pas_vis_premiere_peau' => 300, 'pas_vis_derniere_peau' => 250, 'pas_vis_peau_unique' => 250]], 'prix' => '7.95'],
            ['reference' => self::JOINT_PLAT, 'libelle' => 'Bande à joint plate 75 m', 'categorie' => 'bande_joint', 'longueur' => 75000, 'largeur' => null, 'epaisseur' => null,
                'unite_vente' => 'rouleau', 'unites_par_lot' => 5, 'caracteristiques' => ['type' => 'plat', 'longueur_rouleau' => 75000], 'prix' => '59.00'],
            ['reference' => self::JOINT_ARMEE, 'libelle' => 'Bande à joint armée 30 m', 'categorie' => 'bande_joint', 'longueur' => 30000, 'largeur' => null, 'epaisseur' => null,
                'unite_vente' => 'rouleau', 'unites_par_lot' => 3, 'caracteristiques' => ['type' => 'armee', 'longueur_rouleau' => 30000], 'prix' => '63.90'],
            ['reference' => self::APPUIS, 'libelle' => 'Appuis intermédiaires', 'categorie' => 'appui_fixation', 'longueur' => null, 'largeur' => null, 'epaisseur' => null,
                'unite_vente' => 'piece', 'unites_par_lot' => 50, 'caracteristiques' => ['usage' => 'appui_intermediaire', 'pose' => ['hauteur_defaut' => 1200]], 'prix' => '58.20'],
        ];
    }

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MateriauRepository $materiaux,
        private readonly FournisseurRepository $fournisseurs,
        private readonly PrixService $prix,
    ) {
    }

    /**
     * @return array{articles: int, prix: int} nombre d'éléments créés
     */
    public function importer(\DateTimeImmutable $maintenant): array
    {
        $fournisseur = $this->fournisseurs->parNom(self::FOURNISSEUR);
        if (null === $fournisseur) {
            $fournisseur = new Fournisseur();
            $fournisseur->setNom(self::FOURNISSEUR);
            $this->em->persist($fournisseur);
        }

        $nbArticles = 0;
        $nbPrix = 0;
        $nouveaux = [];
        foreach (self::articles() as $def) {
            $materiau = $this->materiaux->parReference($def['reference']);
            if (null === $materiau) {
                $materiau = new Materiau();
                $materiau->setReference($def['reference']);
                $materiau->setLibelle($def['libelle']);
                $materiau->setCategorie($def['categorie']);
                $materiau->setLongueur($def['longueur']);
                $materiau->setLargeur($def['largeur']);
                $materiau->setEpaisseur($def['epaisseur']);
                $materiau->setUniteVente($def['unite_vente']);
                $materiau->setUnitesParLot($def['unites_par_lot']);
                $materiau->setCaracteristiques($def['caracteristiques']);
                $this->em->persist($materiau);
                ++$nbArticles;
            }
            $nouveaux[] = [$materiau, $def['prix']];
        }
        $this->em->flush();

        foreach ($nouveaux as [$materiau, $prix]) {
            if ([] === $this->prixExistants($materiau, $fournisseur)) {
                $this->prix->definirPrix($materiau, $fournisseur, $prix, $maintenant, null, PrixMateriau::SOURCE_IMPORT);
                ++$nbPrix;
            }
        }

        return ['articles' => $nbArticles, 'prix' => $nbPrix];
    }

    /**
     * @return list<PrixMateriau>
     */
    private function prixExistants(Materiau $materiau, Fournisseur $fournisseur): array
    {
        return $this->em->getRepository(PrixMateriau::class)->findBy(['materiau' => $materiau, 'fournisseur' => $fournisseur]);
    }
}
