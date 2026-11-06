<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait BaseDeDonneesTrait
{
    private function creerSchema(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $outil = new SchemaTool($em);
        $outil->dropDatabase();
        $outil->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }
}
