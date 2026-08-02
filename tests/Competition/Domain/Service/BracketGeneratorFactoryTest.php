<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain\Service;

use App\Competition\Domain\Format\SingleElimination\BracketGeneratorWithThirdPlaceMatch;
use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGenerator;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketGeneratorFactoryTest extends TestCase
{
    #[Test]
    public function it_returns_the_generator_registered_for_the_format(): void
    {
        $generator = $this->fakeGenerator();
        $factory = new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => $generator,
        ]);

        $resolved = $factory->forConfiguration(new BracketConfiguration(CompetitionFormat::SingleElimination, false));

        self::assertSame($generator, $resolved);
    }

    #[Test]
    public function it_rejects_a_format_with_no_registered_generator(): void
    {
        $factory = new BracketGeneratorFactory([]);

        $this->expectException(\LogicException::class);

        $factory->forConfiguration(new BracketConfiguration(CompetitionFormat::SingleElimination, false));
    }

    #[Test]
    public function it_wraps_the_registered_generator_with_a_third_place_match_when_included(): void
    {
        $factory = new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => $this->fakeGenerator(),
        ]);

        $resolved = $factory->forConfiguration(new BracketConfiguration(CompetitionFormat::SingleElimination, true));

        self::assertInstanceOf(BracketGeneratorWithThirdPlaceMatch::class, $resolved);
    }

    private function fakeGenerator(): BracketGenerator
    {
        return new class implements BracketGenerator {
            /** @param TeamId[] $teamIds */
            public function generate(array $teamIds): Bracket
            {
                throw new \LogicException('Not expected to be called in this test.');
            }
        };
    }
}
