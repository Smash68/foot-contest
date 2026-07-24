<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine\Type\Normalizer;

use App\Competition\Domain\Format\SingleElimination\BracketWithThirdPlaceMatch;
use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\SingleEliminationBracket;

final class BracketNormalizer
{
    public function __construct(
        private readonly RoundNormalizer $rounds = new RoundNormalizer(),
        private readonly ThirdPlaceFixtureNormalizer $fixtures = new ThirdPlaceFixtureNormalizer(),
        private readonly EncounterResultNormalizer $results = new EncounterResultNormalizer(),
    ) {
    }

    /** @return array<string, mixed> */
    public function normalize(Bracket $bracket): array
    {
        if ($bracket instanceof BracketWithThirdPlaceMatch) {
            $thirdPlaceResult = $bracket->getThirdPlaceEncounter()?->getResult();

            return [
                'type' => 'single_elimination_third_place',
                'rounds' => $this->normalizeRounds($bracket),
                'third_place_fixture' => $this->fixtures->normalize($bracket->getFixture()),
                'third_place_result' => $thirdPlaceResult === null ? null : $this->results->normalize($thirdPlaceResult),
            ];
        }

        return [
            'type' => 'single_elimination',
            'rounds' => $this->normalizeRounds($bracket),
        ];
    }

    /** @return array<int, mixed> */
    private function normalizeRounds(Bracket $bracket): array
    {
        return array_values(array_map($this->rounds->normalize(...), $bracket->getRounds()));
    }

    public function denormalize(mixed $data): Bracket
    {
        assert(is_array($data));
        assert(is_array($data['rounds']));
        assert(is_string($data['type']));

        $inner = new SingleEliminationBracket(array_map($this->rounds->denormalize(...), $data['rounds']));

        if ($data['type'] === 'single_elimination') {
            return $inner;
        }

        $bracket = new BracketWithThirdPlaceMatch($inner, $this->fixtures->denormalize($data['third_place_fixture']));

        (new \ReflectionMethod($bracket, 'buildThirdPlaceEncounterIfReady'))->invoke($bracket);

        if ($data['third_place_result'] !== null) {
            $bracket->getThirdPlaceEncounter()?->play($this->results->denormalize($data['third_place_result']));
        }

        return $bracket;
    }
}
