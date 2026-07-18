<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type;

use App\Competition\Domain\Model\CompetitionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CompetitionIdType extends Type
{
    public const NAME = 'competition_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CompetitionId
    {
        if ($value === null) {
            return null;
        }

        assert(is_string($value));

        return new CompetitionId($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof CompetitionId);

        return $value->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}