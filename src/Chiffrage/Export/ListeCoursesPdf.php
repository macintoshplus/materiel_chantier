<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

use App\Entity\Chiffrage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Liste de courses d'un chiffrage : le même gabarit Twig produit l'aperçu HTML et, via Dompdf, le PDF.
 */
final class ListeCoursesPdf
{
    public function __construct(private readonly Environment $twig, private readonly ListeCourses $liste)
    {
    }

    public function html(Chiffrage $chiffrage, PorteeExport $portee, bool $avecPrix): string
    {
        $sections = $this->liste->sections($chiffrage, $portee);

        return $this->twig->render('export/liste.html.twig', [
            'chiffrage' => $chiffrage,
            'portee' => $portee,
            'avecPrix' => $avecPrix,
            'sections' => $sections,
            'total' => array_sum(array_map(static fn (SectionExport $s): int => $s->total(), $sections)),
            'partiel' => $this->liste->estPartiel($sections),
        ]);
    }

    public function generer(Chiffrage $chiffrage, PorteeExport $portee, bool $avecPrix): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($chiffrage, $portee, $avecPrix));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
