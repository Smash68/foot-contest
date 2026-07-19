<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type;

use App\Competition\Domain\Model\PlayerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PlayerIdType extends Type
{
    public const NAME = 'player_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PlayerId
    {
        if ($value === null) {
            return null;
        }

        assert(is_string($value));

        return new PlayerId($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof PlayerId);

        return $value->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
