<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\SingleEliminationBracket;
use App\Competition\Domain\Model\TeamId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BracketType extends Type
{
    public const NAME = 'bracket';

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

        $data = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data));

        return new SingleEliminationBracket($this->hydrateRounds($data['rounds']));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        assert($value instanceof Bracket);

        return json_encode([
            'type' => 'single_elimination',
            'rounds' => $this->extractRounds($value->getRounds()),
        ], JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /** @return Round[] */
    private function hydrateRounds(mixed $rounds): array
    {
        assert(is_array($rounds));

        return array_map(function (mixed $round): Round {
            assert(is_array($round));
            assert(is_int($round['number']));
            assert(is_array($round['encounters']));

            return new Round($round['number'], array_map($this->hydrateEncounter(...), $round['encounters']));
        }, $rounds);
    }

    private function hydrateEncounter(mixed $encounter): Encounter
    {
        assert(is_array($encounter));
        assert(is_string($encounter['id']));

        return new Encounter(
            new EncounterId($encounter['id']),
            $this->hydrateParticipant($encounter['home']),
            $this->hydrateParticipant($encounter['away']),
        );
    }

    private function hydrateParticipant(mixed $participant): Participant
    {
        assert(is_array($participant));
        assert(is_string($participant['state']));

        return match ($participant['state']) {
            'team' => Participant::forTeam(new TeamId($this->stringField($participant, 'team_id'))),
            'bye' => Participant::bye(),
            'pending' => Participant::pendingWinnerOf(new EncounterId($this->stringField($participant, 'encounter_id'))),
            default => throw new \LogicException("Unknown participant state '{$participant['state']}'."),
        };
    }

    /** @param array<array-key, mixed> $data */
    private function stringField(array $data, string $key): string
    {
        $value = $data[$key];
        assert(is_string($value));

        return $value;
    }

    /**
     * @param Round[] $rounds
     *
     * @return array<int, array{number: int, encounters: array<int, array{id: string, home: array<string, string>, away: array<string, string>}>}>
     */
    private function extractRounds(array $rounds): array
    {
        return array_values(array_map(fn (Round $round) => [
            'number' => $round->getNumber(),
            'encounters' => array_values(array_map($this->extractEncounter(...), $round->getEncounters())),
        ], $rounds));
    }

    /** @return array{id: string, home: array<string, string>, away: array<string, string>} */
    private function extractEncounter(Encounter $encounter): array
    {
        return [
            'id' => $encounter->id->value,
            'home' => $this->extractParticipant($encounter->getHome()),
            'away' => $this->extractParticipant($encounter->getAway()),
        ];
    }

    /** @return array<string, string> */
    private function extractParticipant(Participant $participant): array
    {
        if ($participant->isBye()) {
            return ['state' => 'bye'];
        }

        if ($participant->isPending()) {
            return ['state' => 'pending', 'encounter_id' => $participant->getPendingWinnerOfEncounterId()->value];
        }

        return ['state' => 'team', 'team_id' => $participant->getTeamId()->value];
    }
}
