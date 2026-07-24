<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer\BracketNormalizer;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BracketType extends Type
{
    public const NAME = 'bracket';

    private readonly BracketNormalizer $normalizer;

    public function __construct()
    {
        $this->normalizer = new BracketNormalizer();
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Bracket
    {
        if ($value === null) {
            return null;
        }

        assert(is_string($value));

        return $this->normalizer->denormalize(json_decode($value, true, flags: JSON_THROW_ON_ERROR));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof Bracket);

        return json_encode($this->normalizer->normalize($value), JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
