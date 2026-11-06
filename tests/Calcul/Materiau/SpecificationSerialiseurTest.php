<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Materiau;

use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
use PHPUnit\Framework\TestCase;

final class SpecificationSerialiseurTest extends TestCase
{
    public function test_aller_retour_avec_reference_au_contexte(): void
    {
        $serialiseur = new SpecificationSerialiseur(new RegistreCategories([new ProfilOssature(), new Isolant()]));
        $spec = MaterielSpecification::creer('ossature', new ProfilOssature(), [
            new Critere('serie', Operateur::SuperieurOuEgal, '{contexte.profondeur_cavite}'),
            new Critere('famille', Operateur::Dans, ['montant', 'rail']),
        ]);

        $donnees = $serialiseur->vers($spec);
        $json = json_decode(json_encode($donnees, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        $relue = $serialiseur->depuis($json);

        self::assertSame('ossature', $relue->role);
        self::assertSame('profil_ossature', $relue->categorie->code());
        self::assertEquals($spec->criteres, $relue->criteres);
        self::assertFalse($relue->estResolue());
    }

    public function test_des_donnees_invalides_sont_refusees(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SpecificationSerialiseur(new RegistreCategories([])))->depuis(['role' => 1]);
    }
}
