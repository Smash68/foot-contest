<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\TeamId;

final class ParticipantNormalizer
{
    /** @return array<string, string> */
    public function normalize(Participant $participant): array
    {
        if ($participant->isBye()) {
            return ['state' => 'bye'];
        }

        if ($participant->isPending()) {
            return ['state' => 'pending', 'encounter_id' => $participant->getPendingWinnerOfEncounterId()->value];
        }

        return ['state' => 'team', 'team_id' => $participant->getTeamId()->value];
    }

    public function denormalize(mixed $data): Participant
    {
        assert(is_array($data));
        assert(is_string($data['state']));

        return match ($data['state']) {
            'team' => Participant::forTeam(new TeamId($this->stringField($data, 'team_id'))),
            'bye' => Participant::bye(),
            'pending' => Participant::pendingWinnerOf(new EncounterId($this->stringField($data, 'encounter_id'))),
            default => throw new \LogicException("Unknown participant state '{$data['state']}'."),
        };
    }

    /** @param array<array-key, mixed> $data */
    private function stringField(array $data, string $key): string
    {
        $value = $data[$key];
        assert(is_string($value));

        return $value;
    }
}
