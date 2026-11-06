<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Projet;
use App\Repository\FournisseurRepository;
use App\Service\CalculProjet;
use App\Service\MessageAvertissement;
use App\Service\PorteeAgregation;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Action « Calculer » d'un projet : agrège les besoins de toutes les surfaces (coupes réunies, marge, lots entiers).
 */
final class CalculProjetController extends AbstractController
{
    #[Route('/projets/{id}/calculate', name: 'projet_calcul', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Projet $projet,
        CalculProjet $calcul,
        FournisseurRepository $fournisseurs,
    ): Response {
        $portee = PorteeAgregation::tryFrom($request->query->getString('portee')) ?? PorteeAgregation::Projet;
        $idFournisseur = $request->query->getInt('fournisseur');
        $fournisseur = $idFournisseur > 0 ? $fournisseurs->find($idFournisseur) : null;
        $resultat = $calcul->calculer($projet, $portee, $fournisseur);

        $messages = static fn (array $liste): array => array_map(MessageAvertissement::depuis(...), $liste);

        return $this->render('projet/calcul.html.twig', [
            'projet' => $projet,
            'portee' => $portee,
            'portees' => PorteeAgregation::cases(),
            'fournisseur' => $fournisseur,
            'fournisseurs' => $fournisseurs->findBy(['actif' => true], ['nom' => 'ASC']),
            'resultat' => $resultat,
            'messagesCalcul' => $messages([...$resultat->erreurs, ...$resultat->avertissements]),
            'messagesGroupes' => array_map(static fn ($g): array => array_map(MessageAvertissement::depuis(...), $g->resultat->avertissements), $resultat->groupes),
        ]);
    }
}
