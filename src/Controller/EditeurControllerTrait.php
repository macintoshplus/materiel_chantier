<?php

declare(strict_types=1);

namespace App\Controller;

use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Service\MessageAvertissement;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Comportements communs des écrans d'édition : enregistrement explicite, « Save and continue », avertissements.
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
 */
trait EditeurControllerTrait
{
    /**
     * Après un enregistrement, retourne vers la page demandée par « Save and continue » (chemin local uniquement), sinon `$defaut`.
     */
    private function redirigerApres(Request $request, string $defaut): RedirectResponse
    {
        $suite = $request->request->getString('_continue');
        if (str_starts_with($suite, '/') && !str_starts_with($suite, '//') && !str_starts_with($suite, '/\\')) {
            return $this->redirect($suite);
        }

        return $this->redirect($defaut);
    }

    /**
     * @param iterable<Avertissement> $avertissements
     */
    private function signaler(iterable $avertissements): void
    {
        foreach ($avertissements as $avertissement) {
            $this->addFlash('warning', MessageAvertissement::depuis($avertissement));
        }
    }

    /**
     * @param iterable<Avertissement> $avertissements
     *
     * @return list<TranslatableMessage>
     */
    private function messages(iterable $avertissements): array
    {
        $messages = [];
        foreach ($avertissements as $avertissement) {
            $messages[] = MessageAvertissement::depuis($avertissement);
        }

        return $messages;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function erreurDeFormulaire(FormInterface $form, ErreurGeometrique $erreur, TranslatorInterface $translator): void
    {
        $form->addError(new FormError(MessageAvertissement::depuis($erreur->avertissement())->trans($translator)));
    }
}
