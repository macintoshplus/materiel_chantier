<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\Projet;
use App\Entity\Reseau;
use App\Enum\FamilleEquipement;
use App\Form\Dto\ReseauSaisie;
use App\Form\ReseauType;
use App\Service\ReseauEditionService;
use App\Service\ResumeReseaux;
use App\Service\TypesEquipement;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Réseaux (circuits) d'un projet : liste avec résultats de calcul, création, modification, suppression. La famille est
 * un paramètre de la route ; seules les familles qui ont des types de circuit sont éditables (l'électricité pour
 * l'instant : `TypeCircuit`), les autres répondent 404 jusqu'à l'étape 08.
 */
final class ReseauController extends AbstractController
{
    use EditeurControllerTrait;

    private const string FAMILLES = 'electrique|plomberie|ventilation';

    #[Route('/projets/{projet}/reseaux/{famille}', name: 'reseau_index', requirements: ['projet' => '\d+', 'famille' => self::FAMILLES], methods: ['GET'])]
    public function index(#[MapEntity(id: 'projet')] Projet $projet, FamilleEquipement $famille, ResumeReseaux $resumes): Response
    {
        $this->verifierFamille($famille);
        $resume = $resumes->pour($projet, $famille);

        $messagesParReseau = [];
        foreach ($resume['reseaux'] as $r) {
            $messagesParReseau[(int) $r->reseau->getId()] = $this->messages([...$r->erreurs, ...$r->avertissements]);
        }

        return $this->render('reseau/index.html.twig', [
            'messagesParReseau' => $messagesParReseau,
            'projet' => $projet,
            'famille' => $famille,
            'resumes' => $resume['reseaux'],
            'avertissements' => $this->messages($resume['avertissements']),
            'erreurs' => $this->messages($resume['erreurs']),
        ]);
    }

    #[Route('/projets/{projet}/reseaux/{famille}/new', name: 'reseau_new', requirements: ['projet' => '\d+', 'famille' => self::FAMILLES], methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[MapEntity(id: 'projet')] Projet $projet,
        FamilleEquipement $famille,
        ReseauEditionService $edition,
        TypesEquipement $types,
        TranslatorInterface $translator,
    ): Response {
        $this->verifierFamille($famille);
        $saisie = new ReseauSaisie();
        $saisie->nom = $translator->trans('Circuit').' '.(\count($projet->getReseaux()) + 1);

        return $this->formulaire($request, $projet, $famille, null, $saisie, $edition, $types, $translator);
    }

    #[Route('/reseaux/{reseau}/edit', name: 'reseau_edit', requirements: ['reseau' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(id: 'reseau')] Reseau $reseau,
        ReseauEditionService $edition,
        TypesEquipement $types,
        TranslatorInterface $translator,
    ): Response {
        $this->verifierFamille($reseau->getFamille());

        return $this->formulaire($request, $reseau->getProjet(), $reseau->getFamille(), $reseau, ReseauSaisie::depuis($reseau), $edition, $types, $translator);
    }

    #[Route('/reseaux/{reseau}/delete', name: 'reseau_delete', requirements: ['reseau' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'reseau')] Reseau $reseau, ReseauEditionService $edition): Response
    {
        if (!$this->isCsrfTokenValid('supprimer-reseau'.$reseau->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $projet = $reseau->getProjet();
        $famille = $reseau->getFamille();
        $edition->supprimer($reseau);
        $this->addFlash('success', 'Circuit deleted.');

        return $this->redirectToRoute('reseau_index', ['projet' => $projet->getId(), 'famille' => $famille->value]);
    }

    private function verifierFamille(FamilleEquipement $famille): void
    {
        if (FamilleEquipement::Electrique !== $famille) {
            throw $this->createNotFoundException();
        }
    }

    private function formulaire(
        Request $request,
        Projet $projet,
        FamilleEquipement $famille,
        ?Reseau $reseau,
        ReseauSaisie $saisie,
        ReseauEditionService $edition,
        TypesEquipement $types,
        TranslatorInterface $translator,
    ): Response {
        $sections = ['1.5', '2.5', '4', '6', '10', '16'];
        if (!\in_array($saisie->section, $sections, true)) {
            $sections[] = $saisie->section;
        }
        $form = $this->createForm(ReseauType::class, $saisie, [
            'equipements' => $edition->equipementsDuProjet($projet, $famille),
            'libelle' => static fn (Equipement $e): string => \sprintf(
                '%s - %s / %s (%d, %d)',
                $e->getNom() ?? $translator->trans($types->libelle($e->getFamille(), $e->getType())),
                $e->getPiece()->getNom(),
                $e->getSurface()->getNom(),
                $e->getU(),
                $e->getV(),
            ),
            'sections' => $sections,
        ]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                /** @var ReseauSaisie $donnees */
                $donnees = $form->getData();
                $edition->enregistrer($projet, $famille, $reseau, $donnees);
                $this->addFlash('success', 'Circuit saved.');

                return $this->redirectToRoute('reseau_index', ['projet' => $projet->getId(), 'famille' => $famille->value]);
            }
        }

        return $this->render('reseau/edit.html.twig', [
            'projet' => $projet,
            'famille' => $famille,
            'reseau' => $reseau,
            'form' => $form,
        ], new Response(status: $statut));
    }
}
