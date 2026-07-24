<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\Round;

final class RoundNormalizer
{
    public function __construct(
        private readonly EncounterNormalizer $encounters = new EncounterNormalizer(),
    ) {
    }

    /** @return array{number: int, encounters: array<int, array{id: string, home: array<string, string>, away: array<string, string>, result: array<string, mixed>|null}>} */
    public function normalize(Round $round): array
    {
        return [
            'number' => $round->getNumber(),
            'encounters' => array_values(array_map($this->encounters->normalize(...), $round->getEncounters())),
        ];
    }

    public function denormalize(mixed $data): Round
    {
        assert(is_array($data));
        assert(is_int($data['number']));
        assert(is_array($data['encounters']));

        return new Round($data['number'], array_map($this->encounters->denormalize(...), $data['encounters']));
    }
}
