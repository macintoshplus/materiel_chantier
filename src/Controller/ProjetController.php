<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Projet;
use App\Form\Projet\ProjetType;
use App\Repository\ProjetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projets')]
final class ProjetController extends AbstractController
{
    #[Route('', name: 'projet_index', methods: ['GET'])]
    public function index(ProjetRepository $repository): Response
    {
        return $this->render('projet/index.html.twig', [
            'projets' => $repository->findBy([], ['dateCreation' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'projet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, new Projet(), $em, 'New project');
    }

    #[Route('/{id}', name: 'projet_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Projet $projet): Response
    {
        return $this->render('projet/show.html.twig', ['projet' => $projet]);
    }

    #[Route('/{id}/modifier', name: 'projet_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'id')] Projet $projet, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $projet, $em, 'Edit project');
    }

    #[Route('/{id}/supprimer', name: 'projet_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'id')] Projet $projet, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer'.$projet->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($projet);
        $em->flush();
        $this->addFlash('success', 'Project deleted.');

        return $this->redirectToRoute('projet_index');
    }

    private function formulaire(Request $request, Projet $projet, EntityManagerInterface $em, string $titre): Response
    {
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($projet);
            $em->flush();
            $this->addFlash('success', 'Project saved.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        return $this->render('projet/form.html.twig', ['form' => $form, 'titre' => $titre, 'projet' => $projet]);
    }
}
