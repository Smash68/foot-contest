<?php

declare(strict_types=1);

namespace App\Tests\Support\Assertion;

use App\Competition\Application\GetEncounter\View\EncounterDetailView;
use App\Competition\Application\GetEncounter\View\ParticipantDetailView;
use App\Competition\Application\GetEncounter\View\ParticipantViewType;
use App\Competition\Application\GetEncounter\View\TeamSummaryView;
use PHPUnit\Framework\Assert;

/**
 * Reads an encounter sheet the way a person would: who plays, with whom, and what is known so far.
 */
final class EncounterSheetAssert
{
    private function __construct(private readonly EncounterDetailView $sheet)
    {
    }

    public static function assertThat(?EncounterDetailView $sheet): self
    {
        Assert::assertNotNull($sheet, 'Expected an encounter sheet, got none.');

        return new self($sheet);
    }

    public function isForEncounter(string $encounterId): self
    {
        Assert::assertSame($encounterId, $this->sheet->id);

        return $this;
    }

    /** The draw is random, so the order of the two teams does not matter. */
    public function opposes(string $teamName, string $otherTeamName): self
    {
        Assert::assertEqualsCanonicalizing(
            [$teamName, $otherTeamName],
            array_map(fn (TeamSummaryView $team) => $team->name, $this->teams()),
        );

        return $this;
    }

    public function listsOnlyTheCaptainInEachTeam(): self
    {
        foreach ($this->teams() as $team) {
            Assert::assertCount(1, $team->players);
            Assert::assertSame($team->captainId, $team->players[0]->id);
        }

        return $this;
    }

    public function hasNoResultYet(): self
    {
        Assert::assertNull($this->sheet->result);

        return $this;
    }

    /** @return list<TeamSummaryView> */
    private function teams(): array
    {
        return array_map(
            function (ParticipantDetailView $participant): TeamSummaryView {
                Assert::assertSame(ParticipantViewType::Team, $participant->type);
                Assert::assertNotNull($participant->team);

                return $participant->team;
            },
            [$this->sheet->home, $this->sheet->away],
        );
    }
}
