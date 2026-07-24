<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;

final class EncounterNormalizer
{
    public function __construct(
        private readonly ParticipantNormalizer $participants = new ParticipantNormalizer(),
        private readonly EncounterResultNormalizer $results = new EncounterResultNormalizer(),
    ) {
    }

    /** @return array{id: string, home: array<string, string>, away: array<string, string>, result: array<string, mixed>|null} */
    public function normalize(Encounter $encounter): array
    {
        return [
            'id' => $encounter->id->value,
            'home' => $this->participants->normalize($encounter->getHome()),
            'away' => $this->participants->normalize($encounter->getAway()),
            'result' => $encounter->getResult() === null ? null : $this->results->normalize($encounter->getResult()),
        ];
    }

    public function denormalize(mixed $data): Encounter
    {
        assert(is_array($data));
        assert(is_string($data['id']));

        $encounter = new Encounter(
            new EncounterId($data['id']),
            $this->participants->denormalize($data['home']),
            $this->participants->denormalize($data['away']),
        );

        if ($data['result'] !== null) {
            $encounter->play($this->results->denormalize($data['result']));
        }

        return $encounter;
    }
}
