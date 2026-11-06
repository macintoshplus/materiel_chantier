<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

final readonly class PoseePlaque
{
    public function __construct(
        public int $entraxeMax,
        public int $pasVisPremierePeau,
        public int $pasVisDernierePeau,
        public int $pasVisPeauUnique,
    ) {
    }
}
