<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Catalogue\RegistreCategories;
use App\Entity\Materiau;
use App\Form\Admin\MateriauType;
use App\Form\Admin\NouveauPrix;
use App\Form\Admin\NouveauPrixType;
use App\Repository\MateriauRepository;
use App\Repository\PrixMateriauRepository;
use App\Service\PrixService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/catalogue/materiaux')]
final class MateriauController extends AbstractController
{
    #[Route('', name: 'materiau_index', methods: ['GET'])]
    public function index(Request $request, MateriauRepository $repository, RegistreCategories $categories): Response
    {
        $categorie = $request->query->getString('categorie');

        return $this->render('admin/materiau/index.html.twig', [
            'materiaux' => $repository->lister($categorie),
            'categories' => $categories->toutes(),
            'categorie' => $categorie,
        ]);
    }

    #[Route('/nouveau', name: 'materiau_new', methods: ['GET', 'POST'])]
    public function new(Request $request, RegistreCategories $categories, EntityManagerInterface $em): Response
    {
        $code = $request->query->getString('categorie');
        if (!$categories->possede($code)) {
            return $this->render('admin/materiau/choisir_categorie.html.twig', ['categories' => $categories->toutes()]);
        }

        $materiau = new Materiau();
        $materiau->setCategorie($code);

        return $this->formulaire($request, $materiau, $categories, $em, 'New article');
    }

    #[Route('/{id}/modifier', name: 'materiau_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(id: 'id')] Materiau $materiau,
        RegistreCategories $categories,
        EntityManagerInterface $em,
    ): Response {
        return $this->formulaire($request, $materiau, $categories, $em, 'Edit article');
    }

    #[Route('/{id}/dupliquer', name: 'materiau_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(
        Request $request,
        #[MapEntity(id: 'id')] Materiau $materiau,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('dupliquer' . $materiau->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $copie = clone $materiau;
        $copie->setReference($materiau->getReference() . '-copie');
        $copie->setLibelle($materiau->getLibelle() . ' (copy)');
        $em->persist($copie);
        $em->flush();

        return $this->redirectToRoute('materiau_edit', ['id' => $copie->getId()]);
    }

    #[Route('/{id}/prix', name: 'materiau_prix', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function prix(
        Request $request,
        #[MapEntity(id: 'id')] Materiau $materiau,
        PrixMateriauRepository $repository,
        PrixService $service,
    ): Response {
        $nouveau = new NouveauPrix();
        $nouveau->dateDebut = new \DateTimeImmutable();
        $form = $this->createForm(NouveauPrixType::class, $nouveau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $nouveau->fournisseur && null !== $nouveau->prixLotTtc) {
            try {
                $service->definirPrix(
                    $materiau,
                    $nouveau->fournisseur,
                    $nouveau->prixLotTtc,
                    $nouveau->dateDebut,
                    $nouveau->unitesParLot,
                    referenceFournisseur: $nouveau->referenceFournisseur,
                );
                $this->addFlash('success', 'Price saved.');

                return $this->redirectToRoute('materiau_prix', ['id' => $materiau->getId()]);
            } catch (\InvalidArgumentException) {
                $form->addError(new \Symfony\Component\Form\FormError('The new price cannot start before the current one.'));
            }
        }

        return $this->render('admin/materiau/prix.html.twig', [
            'materiau' => $materiau,
            'courants' => $repository->courants($materiau),
            'historique' => $repository->toutLHistorique($materiau),
            'form' => $form,
        ]);
    }

    private function formulaire(Request $request, Materiau $materiau, RegistreCategories $categories, EntityManagerInterface $em, string $titre): Response
    {
        $categorie = $categories->get($materiau->getCategorie());
        $form = $this->createForm(MateriauType::class, $materiau, ['categorie' => $categorie]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($materiau);
            $em->flush();
            $this->addFlash('success', 'Article saved.');

            return $this->redirectToRoute('materiau_index', ['categorie' => $materiau->getCategorie()]);
        }

        return $this->render('admin/materiau/form.html.twig', [
            'form' => $form,
            'materiau' => $materiau,
            'categorie' => $categorie,
            'titre' => $titre,
        ]);
    }
}
