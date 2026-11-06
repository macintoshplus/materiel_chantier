<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;

/**
 * Choisit l'article réellement acheté à la place de celui du besoin (fournisseur, équivalent). Appelée avant
 * l'optimisation de coupes, pour que celle-ci utilise les dimensions et le lot de l'article retenu.
 */
interface SubstitutionArticle
{
    public function retenir(MaterielLisible $article): ResultatOffre;
}
