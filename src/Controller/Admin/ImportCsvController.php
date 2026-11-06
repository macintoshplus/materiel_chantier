<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ImportateurCsv;
use App\Service\RapportImport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

final class ImportCsvController extends AbstractController
{
    #[Route('/catalogue/import', name: 'catalogue_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ImportateurCsv $importateur): Response
    {
        $form = $this->createFormBuilder(null, ['translation_domain' => 'messages'])
            ->add('fichier', FileType::class, [
                'label' => 'CSV file',
                'constraints' => [new NotNull(), new File(maxSize: '2M', extensions: ['csv', 'txt'])],
            ])
            ->getForm();
        $form->handleRequest($request);

        $rapport = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();
            if ($fichier instanceof UploadedFile) {
                $contenu = file_get_contents($fichier->getPathname());
                $rapport = false === $contenu ? new RapportImport() : $importateur->importer($contenu, new \DateTimeImmutable());
            }
        }

        return $this->render('admin/import.html.twig', [
            'form' => $form,
            'rapport' => $rapport,
            'colonnes' => ImportateurCsv::COLONNES,
        ]);
    }
}
