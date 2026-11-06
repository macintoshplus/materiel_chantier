<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuCalcul;
use App\Apercu\ApercuSurface;
use App\Entity\Piece;
use App\Entity\Surface;
use App\Form\Dto\SurfaceSaisie;
use App\Form\SurfaceSaisieType;
use App\Geometrie\ErreurGeometrique;
use App\Service\ControlePlacement;
use App\Service\OrdreSurfaces;
use App\Service\SurfaceEditionService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Page d'une surface : mur, pan de plafond ou sol (même page, mêmes groupes).
 */
final class SurfaceController extends AbstractController
{
    use EditeurControllerTrait;

    #[Route('/rooms/{piece}/walls/{surface}', name: 'surface_show', requirements: ['piece' => '\d+', 'surface' => '\d+'], methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'surface')] Surface $surface,
        SurfaceEditionService $edition,
        ControlePlacement $controle,
        ApercuSurface $apercu,
        ApercuCalcul $apercuCalcul,
        TranslatorInterface $translator,
    ): Response {
        if ($surface->getPiece() !== $piece || null !== $surface->getCloison()) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(SurfaceSaisieType::class, SurfaceSaisie::depuis($surface));
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                /** @var SurfaceSaisie $saisie */
                $saisie = $form->getData();
                try {
                    $avertissements = $edition->enregistrer($surface, $saisie);
                    $this->addFlash('success', 'Surface saved.');
                    $this->signaler($avertissements);

                    return $this->redirigerApres($request, $this->generateUrl('surface_show', ['piece' => $piece->getId(), 'surface' => $surface->getId()]));
                } catch (ErreurGeometrique $e) {
                    $this->erreurDeFormulaire($form, $e, $translator);
                }
            }
        }

        $calcul = $apercuCalcul->pourSurface($surface);

        return $this->render('surface/show.html.twig', [
            'piece' => $piece,
            'surface' => $surface,
            'form' => $form,
            'apercu' => $apercu->pourSurface($surface),
            'controles' => $this->messages($controle->pourSurface($surface)),
            'calcul' => $calcul,
            'messagesCalcul' => null === $calcul ? [] : $this->messages([...$calcul['erreurs'], ...$calcul['avertissements']]),
            'voisins' => OrdreSurfaces::voisins($surface),
            'contraintes' => $surface->getContraintesSommets(),
        ], new Response(status: $statut));
    }
}
