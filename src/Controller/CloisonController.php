<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuPiece;
use App\Apercu\ApercuSurface;
use App\Entity\Cloison;
use App\Entity\Piece;
use App\Enum\CoteCloison;
use App\Form\CloisonSaisieType;
use App\Form\Dto\CloisonSaisie;
use App\Geometrie\ErreurGeometrique;
use App\Service\ControlePlacement;
use App\Service\EditionCloisonSaisie;
use App\Service\OrdreSurfaces;
use App\Service\SuppressionCloison;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CloisonController extends AbstractController
{
    use EditeurControllerTrait;

    #[Route('/rooms/{piece}/partitions/new', name: 'partition_new', requirements: ['piece' => '\d+'], methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        EditionCloisonSaisie $edition,
        ApercuSurface $apercu,
        TranslatorInterface $translator,
    ): Response {
        return $this->editeur($request, $piece, null, $edition, $apercu, new ControlePlacement(), $translator);
    }

    #[Route('/rooms/{piece}/partitions/{cloison}', name: 'partition_show', requirements: ['piece' => '\d+', 'cloison' => '\d+'], methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'cloison')] Cloison $cloison,
        EditionCloisonSaisie $edition,
        ApercuSurface $apercu,
        ControlePlacement $controle,
        TranslatorInterface $translator,
    ): Response {
        if ($cloison->getPiece() !== $piece) {
            throw $this->createNotFoundException();
        }

        return $this->editeur($request, $piece, $cloison, $edition, $apercu, $controle, $translator);
    }

    #[Route('/rooms/{piece}/partitions/{cloison}/delete', name: 'partition_delete', requirements: ['piece' => '\d+', 'cloison' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'cloison')] Cloison $cloison,
        SuppressionCloison $suppression,
    ): Response {
        if ($cloison->getPiece() !== $piece) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('supprimer-cloison'.$cloison->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $avertissements = $suppression->supprimerAvecAvertissements($cloison);
        $this->addFlash('success', 'Partition deleted.');
        $this->signaler($avertissements);

        return $this->redirectToRoute('piece_show', ['id' => $piece->getId()]);
    }

    private function editeur(
        Request $request,
        Piece $piece,
        ?Cloison $cloison,
        EditionCloisonSaisie $edition,
        ApercuSurface $apercu,
        ControlePlacement $controle,
        TranslatorInterface $translator,
    ): Response {
        $saisie = null === $cloison ? $this->saisieInitiale() : CloisonSaisie::depuis($cloison);
        $form = $this->createForm(CloisonSaisieType::class, $saisie, $this->options($piece, $cloison));
        $form->handleRequest($request);
        $impacts = null;
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                try {
                    $plan = $edition->planifier($piece, $cloison, $saisie);
                    if (!$plan->estVide() && !$request->request->getBoolean('_confirmer')) {
                        $impacts = $this->messages($plan->impacts);
                    } else {
                        [$cloison, $plan] = $edition->enregistrer($piece, $cloison, $saisie);
                        $this->addFlash('success', 'Partition saved.');
                        $this->signaler($plan->impacts);

                        return $this->redirigerApres($request, $this->generateUrl('partition_show', ['piece' => $piece->getId(), 'cloison' => $cloison->getId()]));
                    }
                } catch (ErreurGeometrique $e) {
                    $this->erreurDeFormulaire($form, $e, $translator);
                }
            }
        }

        $cote = CoteCloison::tryFrom($request->query->getString('side')) ?? CoteCloison::B;

        return $this->render('cloison/show.html.twig', [
            'piece' => $piece,
            'cloison' => $cloison,
            'form' => $form,
            'impacts' => $impacts,
            'cote' => $cote,
            'apercu' => null === $cloison ? null : $apercu->pourCloison($cloison, $cote),
            'apercuPiece' => null === $cloison ? (new ApercuPiece())->construire($piece) : null,
            'controles' => null === $cloison ? [] : $this->messages($controle->pourCloison($cloison)),
            'murs' => OrdreSurfaces::murs($piece),
        ], new Response(status: $statut));
    }

    private function saisieInitiale(): CloisonSaisie
    {
        $saisie = new CloisonSaisie();
        $saisie->debutX = 0;
        $saisie->debutY = 0;

        return $saisie;
    }

    /**
     * @return array{murs: array<string, string>, ancrages: array<string, string>}
     */
    private function options(Piece $piece, ?Cloison $cloison): array
    {
        $murs = [];
        $ancrages = ['Automatic' => CloisonSaisie::AUTO, 'Free' => CloisonSaisie::LIBRE];
        foreach (OrdreSurfaces::murs($piece) as $mur) {
            $murs[$mur->getNom()] = (string) $mur->getId();
            $ancrages['Wall: '.$mur->getNom()] = 'mur:'.$mur->getId();
        }
        foreach ($piece->getCloisons() as $autre) {
            if ($autre !== $cloison && null !== $autre->getId()) {
                $ancrages['Partition: '.$autre->getNom()] = 'cloison:'.$autre->getId();
            }
        }

        return ['murs' => $murs, 'ancrages' => $ancrages];
    }
}
