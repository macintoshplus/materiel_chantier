<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuPiece;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Form\Dto\NouvellePiece;
use App\Form\Dto\PieceSaisie;
use App\Form\NouvellePieceType;
use App\Form\PieceSaisieType;
use App\Geometrie\ErreurGeometrique;
use App\Service\EditionPieceSaisie;
use App\Service\OrdreSurfaces;
use App\Service\PieceCreationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PieceController extends AbstractController
{
    use EditeurControllerTrait;

    /**
     * « Start from scratch » : rectangle rapide ou tableau de sommets ; les surfaces sont générées puis la pièce
     * s'ouvre dans l'éditeur.
     */
    #[Route('/projets/{id}/pieces/nouvelle', name: 'piece_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[MapEntity(id: 'id')] Projet $projet,
        EntityManagerInterface $em,
        PieceCreationService $creation,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $saisie = new NouvellePiece();
        $saisie->nom = 'Room '.($projet->getPieces()->count() + 1);
        $form = $this->createForm(NouvellePieceType::class, $saisie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $piece = new Piece($projet);
            $piece->setNom(trim($saisie->nom));
            $piece->setHauteurSousPlafond($saisie->hauteurSousPlafond);
            $piece->setFormeSol($saisie->contour());
            try {
                $violations = $validator->validate($piece);
                if (\count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $form->addError(new FormError((string) $violation->getMessage()));
                    }
                } else {
                    $avertissements = $creation->generer($piece);
                    $em->persist($piece);
                    $em->flush();
                    $this->addFlash('success', 'Room created.');
                    $this->signaler($avertissements);

                    return $this->redirectToRoute('piece_show', ['id' => $piece->getId()]);
                }
            } catch (ErreurGeometrique $e) {
                $this->erreurDeFormulaire($form, $e, $translator);
            }
            $projet->getPieces()->removeElement($piece);
        }

        return $this->render('piece/new.html.twig', [
            'projet' => $projet,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Page de la pièce : aperçu de dessus, murs, cloisons et édition (nom, hauteur, sommets du sol).
     */
    #[Route('/rooms/{id}', name: 'piece_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        #[MapEntity(id: 'id')] Piece $piece,
        EditionPieceSaisie $edition,
        ApercuPiece $apercu,
        TranslatorInterface $translator,
    ): Response {
        $sol = $piece->getSol();
        $form = $this->createForm(PieceSaisieType::class, PieceSaisie::depuis($piece), ['aretes' => null === $sol ? [] : $sol->getContour()->ids()]);
        $form->handleRequest($request);
        $impacts = null;
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                /** @var PieceSaisie $saisie */
                $saisie = $form->getData();
                try {
                    $plan = $edition->planifier($piece, $saisie);
                    if (!$plan->estVide() && !$request->request->getBoolean('_confirmer')) {
                        $impacts = $this->messages($plan->impacts);
                    } else {
                        $plan = $edition->enregistrer($piece, $saisie);
                        $this->addFlash('success', 'Room saved.');
                        $this->signaler($plan->impacts);

                        return $this->redirigerApres($request, $this->generateUrl('piece_show', ['id' => $piece->getId()]));
                    }
                } catch (ErreurGeometrique $e) {
                    $this->erreurDeFormulaire($form, $e, $translator);
                }
            }
        }

        return $this->render('piece/show.html.twig', [
            'piece' => $piece,
            'form' => $form,
            'impacts' => $impacts,
            'apercu' => $apercu->construire($piece),
            'murs' => OrdreSurfaces::murs($piece),
        ], new Response(status: $statut));
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
}
