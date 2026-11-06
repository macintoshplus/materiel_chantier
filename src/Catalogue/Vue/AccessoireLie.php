<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

final readonly class AccessoireLie
{
    public function __construct(
        public string $specification,
        public ?int $parJoint,
        public ?int $pasMm,
    ) {
    }
}
