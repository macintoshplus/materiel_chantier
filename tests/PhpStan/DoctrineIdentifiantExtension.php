<?php

declare(strict_types=1);

namespace App\Tests\PhpStan;

use Doctrine\ORM\Mapping\GeneratedValue;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * Doctrine writes generated identifiers by reflection: tell PHPStan they are written and initialized.
 */
final class DoctrineIdentifiantExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->estGenere($property);
    }

    public function isInitialized(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->estGenere($property);
    }

    private function estGenere(ExtendedPropertyReflection $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if (GeneratedValue::class === $attribute->getName()) {
                return true;
            }
        }

        return false;
    }
}
