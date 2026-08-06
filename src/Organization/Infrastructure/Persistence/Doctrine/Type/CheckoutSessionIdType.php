<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\CheckoutSessionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CheckoutSessionIdType extends Type
{
    public const NAME = 'checkout_session_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CheckoutSessionId
    {
        if ($value === null) {
            return null;
        }

        assert(is_string($value));

        return new CheckoutSessionId($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof CheckoutSessionId);

        return $value->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
