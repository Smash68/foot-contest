<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Format\SingleElimination\ThirdPlaceFixture;
use App\Competition\Domain\Model\EncounterId;

final class ThirdPlaceFixtureNormalizer
{
    /** @return array<string, string> */
    public function normalize(ThirdPlaceFixture $fixture): array
    {
        return [
            'id' => $fixture->id->value,
            'semi_final_one_id' => $fixture->semiFinalOneId->value,
            'semi_final_two_id' => $fixture->semiFinalTwoId->value,
        ];
    }

    public function denormalize(mixed $data): ThirdPlaceFixture
    {
        assert(is_array($data));
        assert(is_string($data['id']));
        assert(is_string($data['semi_final_one_id']));
        assert(is_string($data['semi_final_two_id']));

        return new ThirdPlaceFixture(
            new EncounterId($data['id']),
            new EncounterId($data['semi_final_one_id']),
            new EncounterId($data['semi_final_two_id']),
        );
    }
}
