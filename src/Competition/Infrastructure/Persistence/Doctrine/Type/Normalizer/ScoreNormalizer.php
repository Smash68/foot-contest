<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\Score;

final class ScoreNormalizer
{
    /** @return array{home: int, away: int} */
    public function normalize(Score $score): array
    {
        return ['home' => $score->home, 'away' => $score->away];
    }

    public function denormalize(mixed $data): Score
    {
        assert(is_array($data));
        assert(is_int($data['home']));
        assert(is_int($data['away']));

        return Score::of($data['home'], $data['away']);
    }
}
