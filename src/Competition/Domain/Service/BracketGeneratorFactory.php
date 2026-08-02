<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

use App\Competition\Domain\Format\SingleElimination\BracketGeneratorWithThirdPlaceMatch;
use App\Competition\Domain\Model\BracketConfiguration;

final readonly class BracketGeneratorFactory
{
    /** @param array<string, BracketGenerator> $generatorsByFormat */
    public function __construct(
        private array $generatorsByFormat,
    ) {
    }

    public function forConfiguration(BracketConfiguration $configuration): BracketGenerator
    {
        $generator = $this->generatorsByFormat[$configuration->format->value]
            ?? throw new \LogicException("No generator registered for format '{$configuration->format->value}'.");

        return $configuration->includeThirdPlaceMatch
            ? new BracketGeneratorWithThirdPlaceMatch($generator)
            : $generator;
    }
}
