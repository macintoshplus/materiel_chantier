<?php

declare(strict_types=1);

namespace App\Controller;

use App\Apercu\ApercuImport;
use App\Entity\Projet;
use App\Enum\SourceMesures;
use App\Form\CotesAnglesType;
use App\Form\ImportBlocsType;
use App\Form\ImportContourType;
use App\Form\ImportFichierType;
use App\Form\ImportParametresType;
use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Import\Assistant\ChargeurPlan;
use App\Import\Assistant\EtatAssistant;
use App\Import\Assistant\StockageAssistant;
use App\Import\ChoixImport;
use App\Import\ContourImporte;
use App\Import\DecalageContour;
use App\Import\Dxf\DxfReader;
use App\Import\ModeContour;
use App\Import\PieceImportService;
use App\Import\PlanImporte;
use App\Import\RapportPlan;
use App\Import\RoleImport;
use App\Import\SaisieCotesAngles;
use App\Import\UniteImport;
use App\Service\MessageAvertissement;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Création d'une pièce depuis une source de mesures : fichier DXF, relevé CSV de télémètre laser ou côtés et angles.
 * Assistant commun (envoi, unité et calques, contour, blocs et confirmation) : jamais d'import sans aperçu.
 */
final class PieceImportController extends AbstractController
{
    use EditeurControllerTrait;

    private const JETON = '[0-9a-f]{32}';

    public function __construct(
        private readonly StockageAssistant $stockage,
        private readonly ChargeurPlan $chargeur,
        private readonly ApercuImport $apercu,
        private readonly PieceImportService $import,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/projets/{id}/pieces/import/{source}', name: 'import_upload', requirements: ['id' => '\d+', 'source' => 'dxf|csv'], methods: ['GET', 'POST'])]
    public function envoyer(Request $request, #[MapEntity(id: 'id')] Projet $projet, string $source): Response
    {
        $form = $this->createForm(ImportFichierType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            try {
                $fichier = $request->files->all($form->getName())['fichier'] ?? null;
                $jeton = $this->deposer($projet, $source, $fichier instanceof UploadedFile ? $fichier : null);

                return $this->redirectToRoute('import_parametres', ['id' => $projet->getId(), 'jeton' => $jeton]);
            } catch (ErreurGeometrique $e) {
                $this->erreurDeFormulaire($form, $e, $this->translator);
            }
        }

        return $this->render('import/upload.html.twig', [
            'projet' => $projet,
            'source' => $source,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/projets/{id}/pieces/import/{jeton}/parametres', name: 'import_parametres', requirements: ['id' => '\d+', 'jeton' => self::JETON], methods: ['GET', 'POST'])]
    public function parametres(Request $request, #[MapEntity(id: 'id')] Projet $projet, string $jeton): Response
    {
        $etat = $this->etat($projet, $jeton);
        if (null === $etat) {
            return $this->expire($projet);
        }
        $chemin = $this->stockage->chemin($jeton);
        $plan = null;
        $erreur = null;
        try {
            $plan = $this->chargeur->charger($chemin, $etat);
        } catch (ErreurGeometrique $e) {
            $erreur = $e;
        }
        $calques = null === $plan ? [] : $plan->calques;
        $donnees = [
            'unite' => UniteImport::tryFrom($etat->unite) ?? UniteImport::Millimetre,
            'format' => $etat->format ?? '',
        ];
        foreach ($calques as $i => $calque) {
            $donnees['calque_'.$i] = RoleImport::tryFrom($etat->calques[$calque] ?? '') ?? RoleImport::devinerCalque($calque);
        }
        $form = $this->createForm(ImportParametresType::class, $donnees, ['source' => $etat->source, 'calques' => $calques]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $saisie */
            $saisie = $form->getData();
            $unite = $saisie['unite'] ?? null;
            $etat->unite = $unite instanceof UniteImport ? $unite->value : $etat->unite;
            $format = $saisie['format'] ?? '';
            $etat->format = \is_string($format) && '' !== $format ? $format : null;
            foreach ($calques as $i => $calque) {
                $role = $saisie['calque_'.$i] ?? null;
                if ($role instanceof RoleImport) {
                    $etat->calques[$calque] = $role->value;
                }
            }
            $etat->parametresConfirmes = true;
            $etat->contourConfirme = false;
            $this->stockage->sauver($jeton, $etat);
            try {
                $this->chargeur->charger($chemin, $etat);
                if ($this->cliquee($request, $form, 'continuer')) {
                    return $this->redirectToRoute('import_contour', ['id' => $projet->getId(), 'jeton' => $jeton]);
                }

                return $this->redirectToRoute('import_parametres', ['id' => $projet->getId(), 'jeton' => $jeton]);
            } catch (ErreurGeometrique $e) {
                $this->erreurDeFormulaire($form, $e, $this->translator);
                $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        } elseif ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
        }
        if (null !== $erreur && !$form->isSubmitted()) {
            $this->erreurDeFormulaire($form, $erreur, $this->translator);
        }

        return $this->render('import/parametres.html.twig', [
            'projet' => $projet,
            'jeton' => $jeton,
            'etat' => $etat,
            'form' => $form,
            'vue' => $this->apercu->depuisPlan($plan ?? new PlanImporte([], [], [], new RapportPlan([], []))),
            'messages' => null === $plan ? [] : $this->messages($plan->rapport->avertissements),
            'ignores' => null === $plan ? [] : $plan->rapport->ignores,
        ], new Response(status: $statut));
    }

    #[Route('/projets/{id}/pieces/import/{jeton}/contour', name: 'import_contour', requirements: ['id' => '\d+', 'jeton' => self::JETON], methods: ['GET', 'POST'])]
    public function contour(Request $request, #[MapEntity(id: 'id')] Projet $projet, string $jeton): Response
    {
        $etat = $this->etat($projet, $jeton);
        if (null === $etat) {
            return $this->expire($projet);
        }
        if (!$etat->parametresConfirmes) {
            return $this->redirectToRoute('import_parametres', ['id' => $projet->getId(), 'jeton' => $jeton]);
        }
        try {
            $plan = $this->chargeur->charger($this->stockage->chemin($jeton), $etat);
        } catch (ErreurGeometrique) {
            return $this->redirectToRoute('import_parametres', ['id' => $projet->getId(), 'jeton' => $jeton]);
        }

        $choix = [];
        $rejets = [];
        $meilleur = null;
        $meilleureAire = -1;
        foreach ($plan->contours as $i => $c) {
            if (RoleImport::Ignorer === (RoleImport::tryFrom($etat->calques[$c->calque] ?? '') ?? RoleImport::Contour)) {
                continue;
            }
            if (null !== $c->rejet) {
                $rejets[] = ['calque' => $c->calque, 'message' => MessageAvertissement::depuis($c->rejet)];
                continue;
            }
            if (!$c->selectionnable()) {
                continue;
            }
            $aire = $c->polygone()->aire();
            $choix[\sprintf('#%d · %s · %s m²', $i + 1, $c->calque, number_format($aire / 1_000_000, 2, '.', ''))] = $i;
            if ($aire > $meilleureAire) {
                $meilleureAire = $aire;
                $meilleur = $i;
            }
        }
        $courant = null !== $etat->contour && \in_array($etat->contour, $choix, true) ? $etat->contour : $meilleur;

        $form = $this->createForm(ImportContourType::class, [
            'contour' => $courant,
            'mode' => $etat->mode,
            'epaisseur' => $etat->epaisseur,
            'hauteur' => $etat->hauteur,
            'nom' => '' !== $etat->nom ? $etat->nom : 'Room '.($projet->getPieces()->count() + 1),
        ], ['contours' => $choix]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $saisie */
            $saisie = $form->getData();
            $etat->contour = \is_int($saisie['contour'] ?? null) ? $saisie['contour'] : null;
            $etat->mode = \is_string($saisie['mode'] ?? null) ? $saisie['mode'] : 'interieur';
            $etat->epaisseur = \is_int($saisie['epaisseur'] ?? null) ? $saisie['epaisseur'] : 0;
            $etat->hauteur = \is_int($saisie['hauteur'] ?? null) ? $saisie['hauteur'] : 0;
            $etat->nom = \is_string($saisie['nom'] ?? null) ? trim($saisie['nom']) : '';
            $etat->contourConfirme = false;
            $this->stockage->sauver($jeton, $etat);
            try {
                $this->contourFinal($plan, $etat);
                if ($this->cliquee($request, $form, 'continuer')) {
                    $etat->contourConfirme = true;
                    $this->stockage->sauver($jeton, $etat);

                    return $this->redirectToRoute('import_apercu', ['id' => $projet->getId(), 'jeton' => $jeton]);
                }

                return $this->redirectToRoute('import_contour', ['id' => $projet->getId(), 'jeton' => $jeton]);
            } catch (ErreurGeometrique $e) {
                $this->erreurDeFormulaire($form, $e, $this->translator);
                $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        } elseif ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        $final = null;
        $selection = $form->isSubmitted() ? $this->entierOuNull($form->get('contour')->getData()) : $courant;
        if (null !== $selection) {
            try {
                $final = $this->contourFinal($plan, $etat, $selection);
            } catch (ErreurGeometrique) {
                $final = null;
            }
        }

        return $this->render('import/contour.html.twig', [
            'projet' => $projet,
            'jeton' => $jeton,
            'form' => $form,
            'vue' => $this->apercu->depuisPlan($plan, $selection, $final),
            'rejets' => $rejets,
            'aucun' => [] === $choix,
        ], new Response(status: $statut));
    }

    #[Route('/projets/{id}/pieces/import/{jeton}/apercu', name: 'import_apercu', requirements: ['id' => '\d+', 'jeton' => self::JETON], methods: ['GET', 'POST'])]
    public function apercu(Request $request, #[MapEntity(id: 'id')] Projet $projet, string $jeton): Response
    {
        $etat = $this->etat($projet, $jeton);
        if (null === $etat) {
            return $this->expire($projet);
        }
        if (!$etat->contourConfirme || null === $etat->contour) {
            return $this->redirectToRoute('import_contour', ['id' => $projet->getId(), 'jeton' => $jeton]);
        }
        try {
            $plan = $this->chargeur->charger($this->stockage->chemin($jeton), $etat);
            $final = $this->contourFinal($plan, $etat);
        } catch (ErreurGeometrique) {
            return $this->redirectToRoute('import_contour', ['id' => $projet->getId(), 'jeton' => $jeton]);
        }

        $choixCourant = $this->choix($etat);
        $libelles = [];
        $donnees = [];
        foreach ($plan->blocs as $i => $bloc) {
            $libelles[$i] = $bloc->nom.' ('.$bloc->calque.')';
            $donnees['bloc_'.$i] = $choixCourant->roleDuBloc($i, $bloc);
        }
        $form = $this->createForm(ImportBlocsType::class, $donnees, ['blocs' => $libelles]);
        $form->handleRequest($request);
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $saisie */
            $saisie = $form->getData();
            foreach ($plan->blocs as $i => $_) {
                $role = $saisie['bloc_'.$i] ?? null;
                if ($role instanceof RoleImport) {
                    $etat->blocs[$i] = $role->value;
                }
            }
            $this->stockage->sauver($jeton, $etat);
            if ($this->cliquee($request, $form, 'creer')) {
                try {
                    $resultat = $this->import->creer($projet, $plan, $this->choix($etat), 'csv' === $etat->source ? SourceMesures::Csv : SourceMesures::Dxf);
                    $this->stockage->supprimer($jeton);
                    $this->addFlash('success', 'Room created from the import.');
                    $this->signaler($resultat->avertissements);

                    return $this->redirectToRoute('piece_show', ['id' => $resultat->piece->getId()]);
                } catch (ErreurGeometrique $e) {
                    $this->erreurDeFormulaire($form, $e, $this->translator);
                    $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
                }
            } else {
                return $this->redirectToRoute('import_apercu', ['id' => $projet->getId(), 'jeton' => $jeton]);
            }
        } elseif ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        $choixCourant = $this->choix($etat);
        $roles = [];
        $compte = ['porte' => 0, 'fenetre' => 0, 'equipement' => 0];
        foreach ($plan->blocs as $i => $bloc) {
            $role = $choixCourant->roleDuBloc($i, $bloc);
            if (RoleImport::Ignorer !== $role) {
                $roles[$i] = $role;
                $compte[$role->value] = ($compte[$role->value] ?? 0) + 1;
            }
        }

        return $this->render('import/apercu.html.twig', [
            'projet' => $projet,
            'jeton' => $jeton,
            'etat' => $etat,
            'form' => $form,
            'vue' => $this->apercu->depuisPlan($plan, $etat->contour, $final, $roles),
            'aire' => (new Polygone($final))->aire(),
            'compte' => $compte,
            'messages' => $this->messages($plan->rapport->avertissements),
            'ignores' => $plan->rapport->ignores,
        ], new Response(status: $statut));
    }

    #[Route('/projets/{id}/pieces/import/{jeton}/annuler', name: 'import_annuler', requirements: ['id' => '\d+', 'jeton' => self::JETON], methods: ['POST'])]
    public function annuler(Request $request, #[MapEntity(id: 'id')] Projet $projet, string $jeton): Response
    {
        if (!$this->isCsrfTokenValid('import-annuler'.$jeton, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->stockage->supprimer($jeton);

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Saisie « côtés et angles » : tracé et écart de fermeture recalculés à chaque « Save », création par « Create the room ».
     */
    #[Route('/projets/{id}/pieces/cotes', name: 'piece_cotes', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cotes(Request $request, #[MapEntity(id: 'id')] Projet $projet): Response
    {
        $form = $this->createForm(CotesAnglesType::class, [
            'nom' => 'Room '.($projet->getPieces()->count() + 1),
            'hauteur' => 2500,
            'fermerAuto' => true,
            'lignes' => [['cote' => 4000.0, 'angle' => 90.0], ['cote' => 3000.0, 'angle' => 90.0], ['cote' => 4000.0, 'angle' => 90.0], ['cote' => 3000.0, 'angle' => 90.0]],
        ]);
        $form->handleRequest($request);
        $resultat = null;
        $statut = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
            if ($form->isValid()) {
                /** @var array<string, mixed> $saisie */
                $saisie = $form->getData();
                try {
                    $cotes = [];
                    $angles = [];
                    $lignes = \is_array($saisie['lignes'] ?? null) ? $saisie['lignes'] : [];
                    foreach (array_values($lignes) as $i => $ligne) {
                        $cote = \is_array($ligne) && \is_float($ligne['cote'] ?? null) ? $ligne['cote'] : 0.0;
                        $angle = \is_array($ligne) && \is_float($ligne['angle'] ?? null) ? $ligne['angle'] : 90.0;
                        if ($cote <= 0.0) {
                            throw new ErreurGeometrique('import_side_invalid', ['number' => $i + 1]);
                        }
                        $cotes[] = $cote;
                        $angles[] = $angle;
                    }
                    $fermerAuto = true === ($saisie['fermerAuto'] ?? false);
                    $resultat = (new SaisieCotesAngles())->tracer($cotes, $angles, $fermerAuto ? SaisieCotesAngles::SEUIL_FERMETURE_MM : 1.0);
                    $statut = Response::HTTP_OK;
                    if ($request->request->has('_action') && 'creer' === $request->request->getString('_action')) {
                        if (null === $resultat->contour) {
                            throw new ErreurGeometrique('import_closure_gap', ['gap' => round($resultat->ecart, 1), 'threshold' => $fermerAuto ? SaisieCotesAngles::SEUIL_FERMETURE_MM : 1.0]);
                        }
                        $plan = new PlanImporte(
                            [new ContourImporte($resultat->contour, 'Saisie', true, null, 0, 'saisie')],
                            [],
                            [],
                            new RapportPlan([], null === $resultat->avertissement ? [] : [$resultat->avertissement]),
                        );
                        $nom = \is_string($saisie['nom'] ?? null) ? $saisie['nom'] : '';
                        $hauteur = \is_int($saisie['hauteur'] ?? null) ? $saisie['hauteur'] : 2500;
                        $cree = $this->import->creer($projet, $plan, new ChoixImport(0, $hauteur, nom: $nom), SourceMesures::Saisie);
                        $this->addFlash('success', 'Room created.');
                        $this->signaler($cree->avertissements);

                        return $this->redirectToRoute('piece_show', ['id' => $cree->piece->getId()]);
                    }
                } catch (ErreurGeometrique $e) {
                    $this->erreurDeFormulaire($form, $e, $this->translator);
                    $statut = Response::HTTP_UNPROCESSABLE_ENTITY;
                }
            }
        }

        return $this->render('import/cotes.html.twig', [
            'projet' => $projet,
            'form' => $form,
            'resultat' => $resultat,
            'vue' => null === $resultat ? null : $this->apercu->depuisTrace($resultat->contour ?? $resultat->trace, $resultat->ferme()),
            'message' => null === $resultat || null === $resultat->avertissement ? null : MessageAvertissement::depuis($resultat->avertissement),
        ], new Response(status: $statut));
    }

    /**
     * @throws ErreurGeometrique
     */
    private function deposer(Projet $projet, string $source, ?UploadedFile $fichier): string
    {
        if (null === $fichier) {
            throw new ErreurGeometrique('import_file_missing');
        }
        if (!$fichier->isValid()) {
            if (\in_array($fichier->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
                throw new ErreurGeometrique('import_upload_limit', ['max' => (string) ini_get('upload_max_filesize')]);
            }
            throw new ErreurGeometrique('import_file_unreadable');
        }
        $extension = strtolower($fichier->getClientOriginalExtension());
        $attendues = 'dxf' === $source ? ['dxf'] : ['csv', 'txt'];
        if (!\in_array($extension, $attendues, true)) {
            throw new ErreurGeometrique('import_file_extension', ['extension' => $attendues[0]]);
        }
        $taille = (int) $fichier->getSize();
        if ($taille > DxfReader::TAILLE_MAX) {
            throw new ErreurGeometrique('import_file_too_large', ['size' => round($taille / 1048576, 1), 'max' => DxfReader::TAILLE_MAX / 1048576]);
        }
        [$unite, $devinee] = $this->chargeur->proposerUnite($source, $fichier->getPathname());
        $etat = new EtatAssistant($source, (int) $projet->getId(), basename($fichier->getClientOriginalName()), $unite->value, $devinee);

        return $this->stockage->deposer($fichier->getPathname(), $etat);
    }

    private function etat(Projet $projet, string $jeton): ?EtatAssistant
    {
        $etat = $this->stockage->etat($jeton);

        return null !== $etat && $etat->projetId === $projet->getId() ? $etat : null;
    }

    private function expire(Projet $projet): RedirectResponse
    {
        $this->addFlash('warning', MessageAvertissement::depuis(new Avertissement('import_session_expired')));

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    private function choix(EtatAssistant $etat): ChoixImport
    {
        $calques = [];
        foreach ($etat->calques as $calque => $role) {
            $calques[$calque] = RoleImport::tryFrom($role) ?? RoleImport::Contour;
        }
        $blocs = [];
        foreach ($etat->blocs as $index => $role) {
            $blocs[$index] = RoleImport::tryFrom($role) ?? RoleImport::Equipement;
        }

        return new ChoixImport($etat->contour, $etat->hauteur, ModeContour::tryFrom($etat->mode) ?? ModeContour::Interieur, $etat->epaisseur, $etat->nom, $calques, $blocs);
    }

    /**
     * Contour final (nu intérieur des murs, antihoraire) pour l'aperçu.
     *
     * @return list<Point2D>
     *
     * @throws ErreurGeometrique
     */
    private function contourFinal(PlanImporte $plan, EtatAssistant $etat, ?int $index = null): array
    {
        $index ??= $etat->contour;
        $contour = null === $index ? null : ($plan->contours[$index] ?? null);
        if (null === $contour || !$contour->selectionnable()) {
            throw new ErreurGeometrique('import_contour_unknown');
        }
        $choix = $this->choix($etat);
        if ($choix->epaisseurMur < 0 || (ModeContour::Interieur !== $choix->mode && $choix->epaisseurMur <= 0)) {
            throw new ErreurGeometrique('import_offset_invalid');
        }
        $points = $contour->points;
        if (!(new Polygone($points))->sensAntiHoraire()) {
            $points = array_reverse($points);
        }

        return (new DecalageContour())->vers($points, $choix->decalage());
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function cliquee(Request $request, FormInterface $form, string $bouton): bool
    {
        return isset($request->request->all($form->getName())[$bouton]);
    }

    private function entierOuNull(mixed $valeur): ?int
    {
        return \is_int($valeur) ? $valeur : null;
    }
}
