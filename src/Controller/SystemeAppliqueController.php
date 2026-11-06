<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuSurface;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\RegistreSystemes;
use App\Entity\Cloison;
use App\Entity\Piece;
use App\Entity\Surface;
use App\Entity\SystemeApplique;
use App\Enum\CoteCloison;
use App\Form\SystemeSaisieType;
use App\Service\CalculSysteme;
use App\Service\SystemeEditionService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Application d'un système du registre à une surface (mur, pan de plafond) ou à une cloison : choix de la définition,
 * paramètres, articles par emplacement de couche, variantes. L'activation passe par `variantes_activer`.
 */
final class SystemeAppliqueController extends AbstractController
{
    use EditeurControllerTrait;

    #[Route('/rooms/{piece}/walls/{surface}/systems', name: 'systemes_surface', requirements: ['piece' => '\d+', 'surface' => '\d+'], methods: ['GET'])]
    public function indexSurface(
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'surface')] Surface $surface,
        SystemeEditionService $edition,
    ): Response {
        $this->verifierSurface($piece, $surface);

        return $this->liste($piece, $surface, $edition);
    }

    #[Route('/rooms/{piece}/partitions/{cloison}/systems', name: 'systemes_cloison', requirements: ['piece' => '\d+', 'cloison' => '\d+'], methods: ['GET'])]
    public function indexCloison(
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'cloison')] Cloison $cloison,
        SystemeEditionService $edition,
    ): Response {
        $this->verifierCloison($piece, $cloison);

        return $this->liste($piece, $cloison, $edition);
    }

    #[Route('/rooms/{piece}/walls/{surface}/systems/new', name: 'systeme_new_surface', requirements: ['piece' => '\d+', 'surface' => '\d+'], methods: ['GET', 'POST'])]
    public function newSurface(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'surface')] Surface $surface,
        RegistreSystemes $registre,
        SystemeEditionService $edition,
        CalculSysteme $calcul,
        ApercuSurface $apercu,
    ): Response {
        $this->verifierSurface($piece, $surface);

        return $this->editeur($request, $piece, $surface, null, $this->definition($request, $registre, $edition, $surface), $edition, $calcul, $apercu);
    }

    #[Route('/rooms/{piece}/partitions/{cloison}/systems/new', name: 'systeme_new_cloison', requirements: ['piece' => '\d+', 'cloison' => '\d+'], methods: ['GET', 'POST'])]
    public function newCloison(
        Request $request,
        #[MapEntity(id: 'piece')] Piece $piece,
        #[MapEntity(id: 'cloison')] Cloison $cloison,
        RegistreSystemes $registre,
        SystemeEditionService $edition,
        CalculSysteme $calcul,
        ApercuSurface $apercu,
    ): Response {
        $this->verifierCloison($piece, $cloison);

        return $this->editeur($request, $piece, $cloison, null, $this->definition($request, $registre, $edition, $cloison), $edition, $calcul, $apercu);
    }

    #[Route('/systems/{systeme}/edit', name: 'systeme_edit', requirements: ['systeme' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(id: 'systeme')] SystemeApplique $systeme,
        RegistreSystemes $registre,
        SystemeEditionService $edition,
        CalculSysteme $calcul,
        ApercuSurface $apercu,
    ): Response {
        $support = $this->support($systeme);
        if (!$registre->possede($systeme->getDefinition())) {
            throw $this->createNotFoundException();
        }

        return $this->editeur($request, $support->getPiece(), $support, $systeme, $registre->get($systeme->getDefinition()), $edition, $calcul, $apercu);
    }

    #[Route('/systems/{systeme}/delete', name: 'systeme_delete', requirements: ['systeme' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'systeme')] SystemeApplique $systeme, SystemeEditionService $edition): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-systeme'.$systeme->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $support = $this->support($systeme);
        $sansActif = $edition->supprimer($systeme);
        $this->addFlash('success', 'System deleted.');
        if ($sansActif) {
            $this->addFlash('warning', 'No variant is active any more: activate one of the remaining variants.');
        }

        return $this->redirect($this->urlListe($support));
    }

    private function liste(Piece $piece, Surface|Cloison $support, SystemeEditionService $edition): Response
    {
        return $this->render('systeme/index.html.twig', [
            'piece' => $piece,
            'support' => $support,
            'estCloison' => $support instanceof Cloison,
            'systemes' => $support->getSystemes(),
            'definitions' => $edition->definitionsCompatibles($support),
            'retour' => $this->urlSupport($support),
            'comparaison' => $support instanceof Cloison ? $this->generateUrl('variantes_piece', ['id' => $piece->getId()]) : $this->generateUrl('variantes_surface', ['piece' => $piece->getId(), 'surface' => $support->getId()]),
        ]);
    }

    private function editeur(
        Request $request,
        Piece $piece,
        Surface|Cloison $support,
        ?SystemeApplique $systeme,
        DefinitionSysteme $definition,
        SystemeEditionService $edition,
        CalculSysteme $calcul,
        ApercuSurface $apercu,
    ): Response {
        $saisie = null === $systeme ? $edition->initiale($definition, $support) : $edition->depuis($systeme);
        $form = $this->createForm(SystemeSaisieType::class, $saisie, [
            'definition' => $definition,
            'materiaux' => $edition->articlesParEmplacement($definition, $saisie),
            'cloison' => $support instanceof Cloison,
        ]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                if (null === $systeme) {
                    $systeme = $edition->creer($support, $definition, $saisie);
                } else {
                    $edition->modifier($systeme, $saisie);
                }
                $this->addFlash('success', 'System saved.');
                $resultat = $calcul->calculerSysteme($systeme);
                $this->signaler([...$resultat->erreurs, ...$resultat->avertissements]);

                return $this->redirigerApres($request, $this->generateUrl('systeme_edit', ['systeme' => $systeme->getId()]));
            }
        }

        $resultat = null === $systeme ? null : $calcul->calculerSysteme($systeme);

        return $this->render('systeme/edit.html.twig', [
            'piece' => $piece,
            'support' => $support,
            'estCloison' => $support instanceof Cloison,
            'systeme' => $systeme,
            'definition' => $definition,
            'form' => $form,
            'resultat' => $resultat,
            'messagesCalcul' => null === $resultat ? [] : $this->messages([...$resultat->erreurs, ...$resultat->avertissements]),
            'apercu' => $support instanceof Cloison ? $apercu->pourCloison($support, CoteCloison::B) : $apercu->pourSurface($support),
            'liste' => $this->urlListe($support),
        ], new Response(status: $statut));
    }

    private function definition(Request $request, RegistreSystemes $registre, SystemeEditionService $edition, Surface|Cloison $support): DefinitionSysteme
    {
        $code = $request->query->getString('definition');
        foreach ($edition->definitionsCompatibles($support) as $definition) {
            if ($definition->code() === $code) {
                return $registre->get($code);
            }
        }

        throw $this->createNotFoundException();
    }

    private function support(SystemeApplique $systeme): Surface|Cloison
    {
        return $systeme->getSurface() ?? $systeme->getCloison() ?? throw $this->createNotFoundException();
    }

    private function verifierSurface(Piece $piece, Surface $surface): void
    {
        if ($surface->getPiece() !== $piece || null !== $surface->getCloison()) {
            throw $this->createNotFoundException();
        }
    }

    private function verifierCloison(Piece $piece, Cloison $cloison): void
    {
        if ($cloison->getPiece() !== $piece) {
            throw $this->createNotFoundException();
        }
    }

    private function urlListe(Surface|Cloison $support): string
    {
        return $support instanceof Cloison
            ? $this->generateUrl('systemes_cloison', ['piece' => $support->getPiece()->getId(), 'cloison' => $support->getId()])
            : $this->generateUrl('systemes_surface', ['piece' => $support->getPiece()->getId(), 'surface' => $support->getId()]);
    }

    private function urlSupport(Surface|Cloison $support): string
    {
        return $support instanceof Cloison
            ? $this->generateUrl('partition_show', ['piece' => $support->getPiece()->getId(), 'cloison' => $support->getId()])
            : $this->generateUrl('surface_show', ['piece' => $support->getPiece()->getId(), 'surface' => $support->getId()]);
    }
}
