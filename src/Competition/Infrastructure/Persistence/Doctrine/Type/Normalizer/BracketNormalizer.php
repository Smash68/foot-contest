<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\SingleEliminationBracket;

final class BracketNormalizer
{
    public function __construct(
        private readonly RoundNormalizer $rounds = new RoundNormalizer(),
    ) {
    }

    /** @return array<string, mixed> */
    public function normalize(Bracket $bracket): array
    {
        return [
            'type' => 'single_elimination',
            'rounds' => array_values(array_map($this->rounds->normalize(...), $bracket->getRounds())),
        ];
    }

    public function denormalize(mixed $data): Bracket
    {
        assert(is_array($data));
        assert(is_array($data['rounds']));

        return new SingleEliminationBracket(array_map($this->rounds->denormalize(...), $data['rounds']));
    }
}
