<?php

declare(strict_types=1);

namespace App\Tests\Support\Assertion;

use App\Competition\Application\GetBracket\View\BracketView;
use App\Competition\Application\GetBracket\View\EncounterView;
use App\Competition\Application\GetBracket\View\ParticipantViewType;
use App\Competition\Application\GetBracket\View\RoundView;
use PHPUnit\Framework\Assert;

/**
 * Reads a bracket view the way a person would: how many rounds, who plays each encounter, and what is known so far.
 */
final class BracketViewAssert
{
    private function __construct(private readonly BracketView $bracket)
    {
    }

    public static function assertThat(BracketView $bracket): self
    {
        return new self($bracket);
    }

    public function hasRoundsCount(int $count): self
    {
        Assert::assertCount($count, $this->bracket->rounds);

        return $this;
    }

    public function roundHasEncountersCount(int $roundNumber, int $count): self
    {
        Assert::assertCount($count, $this->round($roundNumber)->encounters);

        return $this;
    }

    public function encounterOpposes(int $roundNumber, int $encounterIndex, string $teamIdA, string $teamIdB): self
    {
        $encounter = $this->encounter($roundNumber, $encounterIndex);

        Assert::assertSame(ParticipantViewType::Team, $encounter->home->type);
        Assert::assertSame(ParticipantViewType::Team, $encounter->away->type);
        Assert::assertEqualsCanonicalizing([$teamIdA, $teamIdB], [$encounter->home->teamId, $encounter->away->teamId]);

        return $this;
    }

    public function encounterHasNoResult(int $roundNumber, int $encounterIndex): self
    {
        Assert::assertNull($this->encounter($roundNumber, $encounterIndex)->result);

        return $this;
    }

    public function isNotComplete(): self
    {
        Assert::assertFalse($this->bracket->isComplete);

        return $this;
    }

    public function hasNoChampion(): self
    {
        Assert::assertNull($this->bracket->champion);

        return $this;
    }

    private function encounter(int $roundNumber, int $index): EncounterView
    {
        return $this->round($roundNumber)->encounters[$index];
    }

    private function round(int $number): RoundView
    {
        foreach ($this->bracket->rounds as $round) {
            if ($round->number === $number) {
                return $round;
            }
        }

        Assert::fail("No round numbered {$number}.");
    }
}
