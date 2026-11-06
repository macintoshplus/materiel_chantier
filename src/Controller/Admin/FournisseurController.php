<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Fournisseur;
use App\Form\Admin\FournisseurType;
use App\Repository\FournisseurRepository;
use App\Repository\PrixMateriauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/catalogue/fournisseurs')]
final class FournisseurController extends AbstractController
{
    #[Route('', name: 'fournisseur_index', methods: ['GET'])]
    public function index(FournisseurRepository $repository): Response
    {
        return $this->render('admin/fournisseur/index.html.twig', [
            'fournisseurs' => $repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'fournisseur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, new Fournisseur(), $em, 'New supplier');
    }

    #[Route('/{id}/modifier', name: 'fournisseur_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'id')] Fournisseur $fournisseur, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $fournisseur, $em, 'Edit supplier');
    }

    #[Route('/{id}', name: 'fournisseur_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Fournisseur $fournisseur, PrixMateriauRepository $prix): Response
    {
        return $this->render('admin/fournisseur/show.html.twig', [
            'fournisseur' => $fournisseur,
            'prix' => $prix->vendusPar($fournisseur),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'fournisseur_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'id')] Fournisseur $fournisseur, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer' . $fournisseur->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($fournisseur);
        $em->flush();
        $this->addFlash('success', 'Supplier deleted.');

        return $this->redirectToRoute('fournisseur_index');
    }

    private function formulaire(Request $request, Fournisseur $fournisseur, EntityManagerInterface $em, string $titre): Response
    {
        $form = $this->createForm(FournisseurType::class, $fournisseur);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($fournisseur);
            $em->flush();
            $this->addFlash('success', 'Supplier saved.');

            return $this->redirectToRoute('fournisseur_index');
        }

        return $this->render('admin/fournisseur/form.html.twig', ['form' => $form, 'titre' => $titre]);
    }
}
