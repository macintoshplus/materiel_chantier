<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Cloison;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Service\SuppressionCloison;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SuppressionCloisonTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    public function testLesCloisonsAncreesSurLaCloisonSupprimeeDeviennentLibres(): void
    {
        self::bootKernel();
        $em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('P');
        $piece = new Piece($projet);
        $piece->setNom('Salon');
        $a = new Cloison($piece);
        $a->setNom('A');
        $em->persist($projet);
        $em->flush();

        $b = new Cloison($piece);
        $b->setNom('B');
        $b->setAncrageDebut(['type' => 'cloison', 'cible' => $a->getId(), 'position' => 500]);
        $b->setAncrageFin(['type' => 'mur', 'cible' => 7, 'position' => null]);
        $c = new Cloison($piece);
        $c->setNom('C');
        $c->setAncrageFin(['type' => 'cloison', 'cible' => $a->getId(), 'position' => 100]);
        $d = new Cloison($piece);
        $d->setNom('D');
        $em->persist($b);
        $em->persist($c);
        $em->persist($d);
        $em->flush();

        $liberees = (new SuppressionCloison($em))->supprimer($a);
        $em->clear();

        self::assertEqualsCanonicalizing(['B', 'C'], array_map(static fn (Cloison $x): string => $x->getNom(), $liberees));
        $b = $em->getRepository(Cloison::class)->findOneBy(['nom' => 'B']);
        self::assertSame(['type' => 'libre', 'cible' => null, 'position' => null], $b?->getAncrageDebut());
        self::assertSame('mur', $b->getAncrageFin()['type'], 'un ancrage sur un mur est conservé');
        self::assertSame('libre', $em->getRepository(Cloison::class)->findOneBy(['nom' => 'C'])?->getAncrageFin()['type']);
        self::assertNull($em->getRepository(Cloison::class)->findOneBy(['nom' => 'A']));
        self::assertCount(3, $em->getRepository(Cloison::class)->findAll());
    }
}
