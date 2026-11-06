<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Piece;
use App\Entity\Projet;
use App\Form\Projet\PieceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PieceController extends AbstractController
{
    #[Route('/projets/{id}/pieces/nouvelle', name: 'piece_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'id')] Projet $projet, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, new Piece($projet), $em, 'New room');
    }

    #[Route('/pieces/{id}/modifier', name: 'piece_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'id')] Piece $piece, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $piece, $em, 'Edit room');
    }

    #[Route('/pieces/{id}/supprimer', name: 'piece_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'id')] Piece $piece, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-piece'.$piece->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projet = $piece->getProjet();
        $projet->getPieces()->removeElement($piece);
        $em->remove($piece);
        $em->flush();
        $this->addFlash('success', 'Room deleted.');

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    private function formulaire(Request $request, Piece $piece, EntityManagerInterface $em, string $titre): Response
    {
        $form = $this->createForm(PieceType::class, $piece);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($piece);
            $em->flush();
            $this->addFlash('success', 'Room saved.');

            return $this->redirectToRoute('projet_show', ['id' => $piece->getProjet()->getId()]);
        }

        return $this->render('piece/form.html.twig', ['form' => $form, 'titre' => $titre, 'piece' => $piece]);
    }
}
