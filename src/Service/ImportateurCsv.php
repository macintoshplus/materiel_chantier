<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use App\Repository\FournisseurRepository;
use App\Repository\MateriauRepository;
use App\Repository\PrixMateriauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Import CSV (séparateur `;`, UTF-8, en-tête obligatoire). Une ligne = un prix d'un article chez un fournisseur.
 * Colonnes : reference, libelle, categorie, longueur, largeur, epaisseur, unite_vente, unites_par_lot,
 * caracteristiques (JSON), fournisseur, prix_lot_ttc, unites_par_lot_prix, reference_fournisseur.
 */
final class ImportateurCsv
{
    public const array COLONNES = [
        'reference', 'libelle', 'categorie', 'longueur', 'largeur', 'epaisseur', 'unite_vente', 'unites_par_lot',
        'caracteristiques', 'fournisseur', 'prix_lot_ttc', 'unites_par_lot_prix', 'reference_fournisseur',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MateriauRepository $materiaux,
        private readonly FournisseurRepository $fournisseurs,
        private readonly PrixMateriauRepository $prixRepository,
        private readonly PrixService $prix,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function importer(string $contenu, \DateTimeImmutable $maintenant): RapportImport
    {
        $rapport = new RapportImport();
        $lignes = preg_split('/\R/', trim($contenu));
        if (false === $lignes || [] === $lignes || '' === ($lignes[0])) {
            $rapport->erreur(1, 'the file is empty.');

            return $rapport;
        }

        $entete = array_map(static fn (?string $c): string => trim((string) $c), str_getcsv(ltrim($lignes[0], "\xEF\xBB\xBF"), ';', '"', ''));
        $manquantes = array_diff(self::COLONNES, $entete);
        if ([] !== $manquantes) {
            $rapport->erreur(1, 'missing columns: {detail}.', implode(', ', $manquantes));

            return $rapport;
        }

        foreach (\array_slice($lignes, 1) as $i => $texte) {
            if ('' === trim($texte)) {
                continue;
            }
            $numero = $i + 2;
            $cellules = str_getcsv($texte, ';', '"', '');
            if (\count($cellules) !== \count($entete)) {
                $rapport->erreur($numero, 'wrong number of columns.');

                continue;
            }
            /** @var array<string, string> $ligne */
            $ligne = array_combine($entete, array_map(static fn (?string $c): string => trim((string) $c), $cellules));
            $this->traiterLigne($ligne, $numero, $maintenant, $rapport);
        }

        return $rapport;
    }

    /**
     * @param array<string, string> $ligne
     */
    private function traiterLigne(array $ligne, int $numero, \DateTimeImmutable $maintenant, RapportImport $rapport): void
    {
        $materiau = $this->materiaux->parReference($ligne['reference']);
        $nouveau = null === $materiau;
        if (null === $materiau) {
            $materiau = new Materiau();
            $materiau->setReference($ligne['reference']);
            $materiau->setLibelle($ligne['libelle']);
            $materiau->setCategorie($ligne['categorie']);
            $materiau->setLongueur($this->entier($ligne['longueur']));
            $materiau->setLargeur($this->entier($ligne['largeur']));
            $materiau->setEpaisseur($this->entier($ligne['epaisseur']));
            $materiau->setUniteVente('' === $ligne['unite_vente'] ? 'piece' : $ligne['unite_vente']);
            $materiau->setUnitesParLot($this->entier($ligne['unites_par_lot']) ?? 1);
            if ('' !== $ligne['caracteristiques']) {
                $json = json_decode($ligne['caracteristiques'], true);
                if (!\is_array($json)) {
                    $rapport->erreur($numero, 'invalid characteristics JSON.');

                    return;
                }
                $materiau->setCaracteristiques($json);
            }
            if (\count($this->validator->validate($materiau)) > 0) {
                $rapport->erreur($numero, 'invalid article "{detail}".', $ligne['reference']);

                return;
            }
        }

        $fournisseur = $this->fournisseurs->parNom($ligne['fournisseur']);
        if (null === $fournisseur) {
            $rapport->erreur($numero, 'unknown supplier "{detail}".', $ligne['fournisseur']);

            return;
        }
        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $ligne['prix_lot_ttc'])) {
            $rapport->erreur($numero, 'invalid price "{detail}".', $ligne['prix_lot_ttc']);

            return;
        }

        if ($nouveau) {
            $this->em->persist($materiau);
            $this->em->flush();
            ++$rapport->articlesCrees;
        } else {
            ++$rapport->articlesExistants;
        }

        $courant = null;
        foreach ($this->prixRepository->historique($materiau, $fournisseur) as $p) {
            if ($p->estCourant()) {
                $courant = $p;
            }
        }
        $lot = $this->entier($ligne['unites_par_lot_prix']);
        if (null !== $courant && $courant->getPrixLotTtc() === number_format((float) $ligne['prix_lot_ttc'], 2, '.', '') && $courant->getUnitesParLot() === $lot) {
            ++$rapport->prixInchanges;

            return;
        }

        $this->prix->definirPrix(
            $materiau,
            $fournisseur,
            $ligne['prix_lot_ttc'],
            $maintenant,
            $lot,
            PrixMateriau::SOURCE_IMPORT,
            '' === $ligne['reference_fournisseur'] ? null : $ligne['reference_fournisseur'],
        );
        ++$rapport->prixDefinis;
    }

    private function entier(string $valeur): ?int
    {
        return '' === $valeur ? null : (int) $valeur;
    }
}
