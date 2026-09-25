<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGeneratorFactory;

/**
 * Builds a valid single-elimination Competition; a test only states what matters to it.
 */
final class CompetitionBuilder
{
    /** @var list<array{name: string, captainId: string}> */
    private array $teams = [];
    private bool $bracketGenerated = false;

    public static function aCompetition(): self
    {
        return new self();
    }

    public function withTeam(string $name, string $captainId): self
    {
        $clone = clone $this;
        $clone->teams[] = ['name' => $name, 'captainId' => $captainId];

        return $clone;
    }

    /** Closes the registration, then generates the bracket. */
    public function withBracketGenerated(): self
    {
        $clone = clone $this;
        $clone->bracketGenerated = true;

        return $clone;
    }

    public function build(): Competition
    {
        $competition = Competition::create(
            new CompetitionId('competition-1'),
            'Summer Cup',
            TeamCapacity::of(2, 16),
            new BracketConfiguration(CompetitionFormat::SingleElimination, false),
            new OrganizationId('organization-1'),
        );

        foreach ($this->teams as $index => $team) {
            $competition->register(Team::create(new TeamId('team-'.($index + 1)), $team['name'], new PlayerId($team['captainId'])));
        }

        if ($this->bracketGenerated) {
            $competition->closeRegistration();
            $competition->generateBracket(new BracketGeneratorFactory([
                CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
            ]));
        }

        return $competition;
    }
}
