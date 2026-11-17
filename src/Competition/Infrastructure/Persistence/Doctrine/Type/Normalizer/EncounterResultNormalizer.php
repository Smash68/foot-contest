<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\EncounterResult;

final class EncounterResultNormalizer
{
    public function __construct(
        private readonly ScoreNormalizer $scores = new ScoreNormalizer(),
    ) {
    }

    /** @return array<string, mixed> */
    public function normalize(EncounterResult $result): array
    {
        return [
            'regular_time' => $this->scores->normalize($result->regularTime),
            'extra_time' => $result->extraTime === null ? null : $this->scores->normalize($result->extraTime),
            'penalties' => $result->penalties === null ? null : $this->scores->normalize($result->penalties),
        ];
    }

    public function denormalize(mixed $data): EncounterResult
    {
        assert(is_array($data));

        return EncounterResult::of(
            $this->scores->denormalize($data['regular_time']),
            $data['extra_time'] !== null ? $this->scores->denormalize($data['extra_time']) : null,
            $data['penalties'] !== null ? $this->scores->denormalize($data['penalties']) : null,
        );
    }
}
