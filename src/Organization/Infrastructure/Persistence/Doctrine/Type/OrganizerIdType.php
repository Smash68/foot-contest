<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\OrganizerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class OrganizerIdType extends Type
{
    public const NAME = 'organizer_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?OrganizerId
    {
        if ($value === null) {
            return null;
        }

        assert(is_string($value));

        return new OrganizerId($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof OrganizerId);

        return $value->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
