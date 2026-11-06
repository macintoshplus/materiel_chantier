<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chiffrage\Export\ListeCoursesPdf;
use App\Chiffrage\Export\PorteeExport;
use App\Chiffrage\Export\Regroupement;
use App\Chiffrage\Export\TypeExport;
use App\Entity\Chiffrage;
use App\Entity\Projet;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projets/{id}/chiffrages/{chiffrage}/exports', requirements: ['id' => '\d+', 'chiffrage' => '\d+'])]
final class ExportController extends AbstractController
{
    #[Route('', name: 'export_index', methods: ['GET'])]
    public function index(#[MapEntity(id: 'id')] Projet $projet, #[MapEntity(id: 'chiffrage')] Chiffrage $chiffrage): Response
    {
        $this->verifier($projet, $chiffrage);
        $pieces = $fournisseurs = [];
        foreach ($chiffrage->getLignes() as $ligne) {
            if (null !== $ligne->getPieceNom()) {
                $pieces[$ligne->getPieceNom()] = $ligne->getPieceNom();
            }
            if (null !== $ligne->getFournisseurNom()) {
                $fournisseurs[$ligne->getFournisseurNom()] = $ligne->getFournisseurNom();
            }
        }
        sort($pieces);
        sort($fournisseurs);

        return $this->render('chiffrage/exports.html.twig', ['projet' => $projet, 'chiffrage' => $chiffrage, 'pieces' => $pieces, 'fournisseurs' => $fournisseurs]);
    }

    #[Route('/liste', name: 'export_liste', methods: ['GET'])]
    public function liste(Request $request, #[MapEntity(id: 'id')] Projet $projet, #[MapEntity(id: 'chiffrage')] Chiffrage $chiffrage, ListeCoursesPdf $pdf): Response
    {
        $this->verifier($projet, $chiffrage);
        $portee = new PorteeExport(
            TypeExport::tryFrom($request->query->getString('type')) ?? TypeExport::Projet,
            '' === $request->query->getString('cible') ? null : $request->query->getString('cible'),
            Regroupement::tryFrom($request->query->getString('regroupement')) ?? Regroupement::Categorie,
            '' === $request->query->getString('piece') ? null : $request->query->getString('piece'),
        );
        $avecPrix = $request->query->getBoolean('prix', true);

        if ('pdf' === $request->query->getString('format')) {
            $reponse = new Response($pdf->generer($chiffrage, $portee, $avecPrix), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
            $reponse->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'liste-courses-'.$chiffrage->getId().'.pdf'));

            return $reponse;
        }

        return new Response($pdf->html($chiffrage, $portee, $avecPrix));
    }

    private function verifier(Projet $projet, Chiffrage $chiffrage): void
    {
        if ($chiffrage->getProjet() !== $projet) {
            throw $this->createNotFoundException();
        }
    }
}
