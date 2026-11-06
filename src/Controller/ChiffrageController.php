<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chiffrage\ComparaisonChiffrages;
use App\Entity\Chiffrage;
use App\Entity\Projet;
use App\Enum\TypeStrategie;
use App\Repository\FournisseurRepository;
use App\Geometrie\Avertissement;
use App\Service\ChiffrageService;
use App\Service\MessageAvertissement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;

#[Route('/projets/{id}/chiffrages', requirements: ['id' => '\d+'])]
final class ChiffrageController extends AbstractController
{
    #[Route('', name: 'chiffrage_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[MapEntity(id: 'id')] Projet $projet,
        ChiffrageService $service,
        FournisseurRepository $fournisseurs,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('chiffrage'.$projet->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $strategie = TypeStrategie::tryFrom($request->request->getString('strategie')) ?? TypeStrategie::MeilleurPrix;
            $parametres = [
                'fournisseur' => $request->request->getString('fournisseur'),
                'choix' => $request->request->all('choix'),
            ];
            $chiffrage = $service->creer($projet, $strategie, new \DateTimeImmutable(), $parametres, trim($request->request->getString('libelle')));
            $this->addFlash('success', 'Quote created.');

            return $this->redirectToRoute('chiffrage_show', ['id' => $projet->getId(), 'chiffrage' => $chiffrage->getId()]);
        }

        return $this->render('chiffrage/index.html.twig', [
            'projet' => $projet,
            'strategies' => TypeStrategie::cases(),
            'fournisseurs' => $fournisseurs->findBy(['actif' => true], ['nom' => 'ASC']),
            'articles' => $service->articlesDuProjet($projet),
        ]);
    }

    #[Route('/comparer', name: 'chiffrage_compare', methods: ['GET'])]
    public function comparer(
        Request $request,
        #[MapEntity(id: 'id')] Projet $projet,
        EntityManagerInterface $em,
        ComparaisonChiffrages $comparaison,
    ): Response {
        $a = $em->find(Chiffrage::class, $request->query->getInt('a'));
        $b = $em->find(Chiffrage::class, $request->query->getInt('b'));
        if (!$a instanceof Chiffrage || !$b instanceof Chiffrage || $a->getProjet() !== $projet || $b->getProjet() !== $projet) {
            throw $this->createNotFoundException();
        }

        return $this->render('chiffrage/compare.html.twig', ['projet' => $projet, 'a' => $a, 'b' => $b, 'comparaison' => $comparaison->comparer($a, $b)]);
    }

    #[Route('/{chiffrage}', name: 'chiffrage_show', requirements: ['chiffrage' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Projet $projet, #[MapEntity(id: 'chiffrage')] Chiffrage $chiffrage): Response
    {
        if ($chiffrage->getProjet() !== $projet) {
            throw $this->createNotFoundException();
        }

        $messages = array_map(
            static fn (array $a): TranslatableMessage => MessageAvertissement::depuis(new Avertissement($a['code'], $a['params'])),
            $chiffrage->getAvertissements(),
        );

        return $this->render('chiffrage/show.html.twig', ['projet' => $projet, 'chiffrage' => $chiffrage, 'messages' => $messages]);
    }

    #[Route('/{chiffrage}/supprimer', name: 'chiffrage_delete', requirements: ['chiffrage' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'id')] Projet $projet, #[MapEntity(id: 'chiffrage')] Chiffrage $chiffrage, EntityManagerInterface $em): Response
    {
        if ($chiffrage->getProjet() !== $projet || !$this->isCsrfTokenValid('supprimer-chiffrage'.$chiffrage->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($chiffrage);
        $em->flush();
        $this->addFlash('success', 'Quote deleted.');

        return $this->redirectToRoute('chiffrage_index', ['id' => $projet->getId()]);
    }
}
