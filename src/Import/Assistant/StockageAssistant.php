<?php

declare(strict_types=1);

namespace App\Import\Assistant;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Fichier téléversé (répertoire temporaire `var/import`, purgé après 24 h, à la fin et à l'annulation) et état de
 * l'assistant (session). Le jeton est aléatoire et propre à la session : un jeton inconnu de la session est refusé.
 */
final class StockageAssistant
{
    private const DUREE_VIE = 86400;

    public function __construct(
        private readonly RequestStack $requetes,
        #[Autowire('%kernel.project_dir%/var/import')]
        private readonly string $repertoire,
    ) {
    }

    /**
     * Enregistre le contenu et crée l'état ; retourne le jeton.
     */
    public function deposer(string $cheminSource, EtatAssistant $etat): string
    {
        $this->purger();
        if (!is_dir($this->repertoire) && !@mkdir($this->repertoire, 0o775, true) && !is_dir($this->repertoire)) {
            throw new \RuntimeException('Cannot create the import directory.');
        }
        $jeton = bin2hex(random_bytes(16));
        if (!@copy($cheminSource, $this->chemin($jeton))) {
            throw new \RuntimeException('Cannot store the uploaded file.');
        }
        $this->sauver($jeton, $etat);

        return $jeton;
    }

    public function chemin(string $jeton): string
    {
        return $this->repertoire.'/'.$jeton.'.dat';
    }

    public function etat(string $jeton): ?EtatAssistant
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/', $jeton) || !is_file($this->chemin($jeton))) {
            return null;
        }
        $brut = $this->requetes->getSession()->get('import.'.$jeton);

        return \is_array($brut) ? EtatAssistant::depuisTableau($brut) : null;
    }

    public function sauver(string $jeton, EtatAssistant $etat): void
    {
        $this->requetes->getSession()->set('import.'.$jeton, $etat->versTableau());
    }

    public function supprimer(string $jeton): void
    {
        if (1 === preg_match('/^[0-9a-f]{32}$/', $jeton)) {
            @unlink($this->chemin($jeton));
            $this->requetes->getSession()->remove('import.'.$jeton);
        }
    }

    /**
     * Supprime les fichiers temporaires de plus de 24 h.
     */
    public function purger(): void
    {
        foreach (glob($this->repertoire.'/*.dat') ?: [] as $fichier) {
            if ((int) @filemtime($fichier) < time() - self::DUREE_VIE) {
                @unlink($fichier);
            }
        }
    }
}
