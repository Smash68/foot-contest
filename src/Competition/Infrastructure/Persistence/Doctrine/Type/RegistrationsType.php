<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type;

use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class RegistrationsType extends Type
{
    public const NAME = 'registrations';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /** @return Team[] */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null) {
            return [];
        }

        assert(is_string($value));

        $rows = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($rows));

        $teams = [];
        foreach ($rows as $row) {
            assert(is_array($row));
            assert(is_string($row['team_id']));
            assert(is_string($row['team_name']));
            assert(is_string($row['captain_id']));

            $teams[] = Team::create(
                new TeamId($row['team_id']),
                $row['team_name'],
                new PlayerId($row['captain_id']),
            );
        }

        return $teams;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        assert(is_array($value));

        $rows = [];
        foreach ($value as $team) {
            assert($team instanceof Team);

            $rows[] = [
                'team_id' => $team->getId()->value,
                'team_name' => $team->getName(),
                'captain_id' => $team->getCaptainId()->value,
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
