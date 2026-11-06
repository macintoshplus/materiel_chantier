<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuSurface;
use App\Entity\Equipement;
use App\Entity\Piece;
use App\Entity\Reseau;
use App\Entity\Surface;
use App\Enum\FamilleEquipement;
use App\Enum\TypeSurface;
use App\Form\Dto\EquipementSaisie;
use App\Form\EquipementType;
use App\Service\EquipementEditionService;
use App\Service\TypesEquipement;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Équipements posés sur une surface, pour toutes les familles : la famille est un paramètre de la route et les types
 * viennent du registre de la famille (`TypesEquipement`).
 */
final class EquipementController extends AbstractController
{
    use EditeurControllerTrait;

    private const string FAMILLES = 'electrique|plomberie|ventilation';

    #[Route('/rooms/{piece}/walls/{surface}/equipments/{famille}/new', name: 'equipement_new', requirements: ['piece' => '\d+', 'surface' => '\d+', 'famille' => self::FAMILLES], methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'surface')] Surface $surface,
        FamilleEquipement $famille,
        TypesEquipement $types,
        EquipementEditionService $edition,
        ApercuSurface $apercu,
    ): Response {
        if ($surface->getPiece() !== $piece || [] === $types->types($famille)) {
            throw $this->createNotFoundException();
        }
        $saisie = new EquipementSaisie();
        $type = $request->query->getString('type');
        if ($types->possede($famille, $type)) {
            $saisie->type = $type;
            $saisie->v = \in_array($surface->getType(), [TypeSurface::Mur, TypeSurface::CloisonFace], true) ? $types->hauteurDefaut($famille, $type) : 0;
        }

        return $this->formulaire($request, $piece, $surface, $famille, null, $saisie, $types, $edition, $apercu);
    }

    #[Route('/equipments/{equipement}/edit', name: 'equipement_edit', requirements: ['equipement' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(id: 'equipement')] Equipement $equipement,
        TypesEquipement $types,
        EquipementEditionService $edition,
        ApercuSurface $apercu,
    ): Response {
        return $this->formulaire($request, $equipement->getPiece(), $equipement->getSurface(), $equipement->getFamille(), $equipement, EquipementSaisie::depuis($equipement), $types, $edition, $apercu);
    }

    #[Route('/equipments/{equipement}/delete', name: 'equipement_delete', requirements: ['equipement' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'equipement')] Equipement $equipement, EquipementEditionService $edition): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-equipement'.$equipement->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $retour = $this->urlSurface($equipement->getSurface());
        $edition->supprimer($equipement);
        $this->addFlash('success', 'Equipment deleted.');

        return $this->redirect($retour);
    }

    private function formulaire(
        Request $request,
        Piece $piece,
        Surface $surface,
        FamilleEquipement $famille,
        ?Equipement $equipement,
        EquipementSaisie $saisie,
        TypesEquipement $types,
        EquipementEditionService $edition,
        ApercuSurface $apercu,
    ): Response {
        $reseaux = array_values(array_filter($piece->getProjet()->getReseaux()->toArray(), static fn (Reseau $r): bool => $r->getFamille() === $famille));
        $form = $this->createForm(EquipementType::class, $saisie, ['famille' => $famille, 'types' => $types->types($famille), 'reseaux' => $reseaux]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                /** @var EquipementSaisie $donnees */
                $donnees = $form->getData();
                $avertissements = $edition->enregistrer($piece, $surface, $famille, $equipement, $donnees);
                $this->addFlash('success', 'Equipment saved.');
                $this->signaler($avertissements);

                return $this->redirect($this->urlSurface($surface));
            }
        }

        return $this->render('equipement/edit.html.twig', [
            'piece' => $piece,
            'surface' => $surface,
            'famille' => $famille,
            'equipement' => $equipement,
            'form' => $form,
            'apercu' => null !== $surface->getCloison() && null !== $surface->getCote() ? $apercu->pourCloison($surface->getCloison(), $surface->getCote()) : $apercu->pourSurface($surface),
            'retour' => $this->urlSurface($surface),
        ], new Response(status: $statut));
    }

    private function urlSurface(Surface $surface): string
    {
        $cloison = $surface->getCloison();
        if (null !== $cloison) {
            return $this->generateUrl('partition_show', ['piece' => $surface->getPiece()->getId(), 'cloison' => $cloison->getId()]);
        }

        return $this->generateUrl('surface_show', ['piece' => $surface->getPiece()->getId(), 'surface' => $surface->getId()]);
    }
}
