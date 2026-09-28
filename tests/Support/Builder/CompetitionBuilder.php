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
    /** @var list<array{name: string, captainId: string, id: ?string}> */
    private array $teams = [];
    /** @var list<array{teamId: string, playerId: string}> */
    private array $teamMembers = [];
    /** @var list<array{teamId: string, playerId: string}> */
    private array $pendingJoinRequests = [];
    private string $organizationId = 'organization-1';
    private bool $thirdPlaceMatch = false;
    private bool $registrationClosed = false;
    private bool $bracketGenerated = false;

    public static function aCompetition(): self
    {
        return new self();
    }

    /** The owning organization; pass the id of a real Organization when the test goes through the authorization check. */
    public function ownedBy(string $organizationId): self
    {
        $clone = clone $this;
        $clone->organizationId = $organizationId;

        return $clone;
    }

    /** Plays a third-place match; chosen at creation, like the format, so it applies once the bracket is generated. */
    public function withThirdPlaceMatch(): self
    {
        $clone = clone $this;
        $clone->thirdPlaceMatch = true;

        return $clone;
    }

    /** The team id defaults to "team-N" (N = registration order); pass `id` when the test must refer to the team. */
    public function withTeam(string $name, string $captainId, ?string $id = null): self
    {
        $clone = clone $this;
        $clone->teams[] = ['name' => $name, 'captainId' => $captainId, 'id' => $id];

        return $clone;
    }

    /** Adds a confirmed roster member (join request approved). The team must be registered with an explicit `id`. */
    public function withTeamMember(string $teamId, string $playerId): self
    {
        $clone = clone $this;
        $clone->teamMembers[] = ['teamId' => $teamId, 'playerId' => $playerId];

        return $clone;
    }

    /** The team must be registered with an explicit `id`; the request is applied while the registration is still open. */
    public function withPendingJoinRequest(string $teamId, string $playerId): self
    {
        $clone = clone $this;
        $clone->pendingJoinRequests[] = ['teamId' => $teamId, 'playerId' => $playerId];

        return $clone;
    }

    /** Closes the registration, without generating the bracket. */
    public function withRegistrationClosed(): self
    {
        $clone = clone $this;
        $clone->registrationClosed = true;

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
            new BracketConfiguration(CompetitionFormat::SingleElimination, $this->thirdPlaceMatch),
            new OrganizationId($this->organizationId),
        );

        foreach ($this->teams as $index => $team) {
            $competition->register(Team::create(new TeamId($team['id'] ?? 'team-'.($index + 1)), $team['name'], new PlayerId($team['captainId'])));
        }

        foreach ($this->teamMembers as $member) {
            $competition->requestToJoinTeam(new TeamId($member['teamId']), new PlayerId($member['playerId']));
            $competition->approveJoinRequest(new TeamId($member['teamId']), new PlayerId($member['playerId']));
        }

        foreach ($this->pendingJoinRequests as $request) {
            $competition->requestToJoinTeam(new TeamId($request['teamId']), new PlayerId($request['playerId']));
        }

        if ($this->registrationClosed || $this->bracketGenerated) {
            $competition->closeRegistration();
        }

        if ($this->bracketGenerated) {
            $competition->generateBracket(new BracketGeneratorFactory([
                CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
            ]));
        }

        return $competition;
    }
}
