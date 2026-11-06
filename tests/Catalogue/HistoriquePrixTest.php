<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\HistoriquePrix;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use PHPUnit\Framework\TestCase;

final class HistoriquePrixTest extends TestCase
{
    private function prix(string $montant, string $debut): PrixMateriau
    {
        return new PrixMateriau(new Materiau(), new Fournisseur(), $montant, new \DateTimeImmutable($debut));
    }

    public function testUnNouveauPrixClotLePrixCourant(): void
    {
        $ancien = $this->prix('10.00', '2026-01-01');
        $nouveau = $this->prix('12.00', '2026-06-01');

        (new HistoriquePrix())->definir([$ancien], $nouveau);

        self::assertFalse($ancien->estCourant());
        self::assertEquals(new \DateTimeImmutable('2026-06-01'), $ancien->getDateFin());
        self::assertTrue($nouveau->estCourant());
    }

    public function testLePrixALaDatePasseeEstRetrouve(): void
    {
        $ancien = $this->prix('10.00', '2026-01-01');
        $nouveau = $this->prix('12.00', '2026-06-01');
        $historique = new HistoriquePrix();
        $historique->definir([$ancien], $nouveau);

        self::assertSame($ancien, $historique->prixA([$ancien, $nouveau], new \DateTimeImmutable('2026-03-15')));
        self::assertSame($nouveau, $historique->prixA([$ancien, $nouveau], new \DateTimeImmutable('2026-06-01')));
        self::assertNull($historique->prixA([$ancien, $nouveau], new \DateTimeImmutable('2025-12-31')));
    }

    public function testUnPrixNeSeTermineJamaisAvantSonDebut(): void
    {
        $courant = $this->prix('10.00', '2026-06-01');

        $this->expectException(\InvalidArgumentException::class);
        (new HistoriquePrix())->definir([$courant], $this->prix('9.00', '2026-01-01'));
    }
}
