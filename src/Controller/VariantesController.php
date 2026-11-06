<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Service\ServiceVariantes;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Comparaison des variantes d'un système (surface, pièce ou projet) : R, U, épaisseur et coût, sans changer la
 * variante active. L'activation est une action explicite (POST).
 */
final class VariantesController extends AbstractController
{
    #[Route('/projets/{id}/variants', name: 'variantes_projet', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function projet(Projet $projet, ServiceVariantes $variantes): Response
    {
        return $this->afficher($variantes, $projet, $projet->getNom(), $this->generateUrl('projet_show', ['id' => $projet->getId()]));
    }

    #[Route('/rooms/{id}/variants', name: 'variantes_piece', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function piece(Piece $piece, ServiceVariantes $variantes): Response
    {
        return $this->afficher($variantes, $piece, $piece->getNom(), $this->generateUrl('piece_show', ['id' => $piece->getId()]));
    }

    #[Route('/rooms/{piece}/walls/{surface}/variants', name: 'variantes_surface', requirements: ['piece' => '\d+', 'surface' => '\d+'], methods: ['GET'])]
    public function surface(
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'surface')] Surface $surface,
        ServiceVariantes $variantes,
    ): Response {
        if ($surface->getPiece() !== $piece) {
            throw $this->createNotFoundException();
        }

        return $this->afficher($variantes, $surface, $surface->getNom(), $this->generateUrl('surface_show', ['piece' => $piece->getId(), 'surface' => $surface->getId()]));
    }

    #[Route('/variants/{systeme}/activate', name: 'variantes_activer', requirements: ['systeme' => '\d+'], methods: ['POST'])]
    public function activer(Request $request, #[MapEntity(id: 'systeme')] SystemeApplique $systeme, ServiceVariantes $variantes): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('activer-variante'.$systeme->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $variantes->activer($systeme);
        $this->addFlash('success', 'Variant activated.');
        $retour = $request->request->getString('_retour');

        return $this->redirect(str_starts_with($retour, '/') && !str_starts_with($retour, '//') && !str_starts_with($retour, '/\\') ? $retour : $this->generateUrl('projet_index'));
    }

    private function afficher(ServiceVariantes $variantes, Surface|Piece|Projet $cible, string $titre, string $retour): Response
    {
        return $this->render('variantes/comparaison.html.twig', [
            'titre' => $titre,
            'lignes' => $variantes->comparer($cible),
            'retour' => $retour,
        ]);
    }
}
