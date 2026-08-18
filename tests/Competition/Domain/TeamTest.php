<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    #[Test]
    public function it_exposes_the_captain_id(): void
    {
        $captainId = new PlayerId('captain@example.com');

        $team = Team::create(new TeamId('a'), 'Team A', $captainId);

        self::assertTrue($captainId->equals($team->getCaptainId()));
    }

    #[Test]
    public function it_initializes_the_roster_with_the_captain(): void
    {
        $captainId = new PlayerId('captain@example.com');

        $team = Team::create(new TeamId('a'), 'Team A', $captainId);

        self::assertCount(1, $team->getRoster());
        self::assertTrue($captainId->equals($team->getRoster()[0]));
    }

    #[Test]
    public function it_records_a_join_request(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));
        $applicantId = new PlayerId('applicant@example.com');

        $team->requestToJoin($applicantId);

        self::assertCount(1, $team->getPendingRequests());
        self::assertTrue($applicantId->equals($team->getPendingRequests()[0]));
    }

    #[Test]
    public function it_rejects_a_join_request_from_a_player_already_in_the_roster(): void
    {
        $captainId = new PlayerId('captain@example.com');
        $team = Team::create(new TeamId('a'), 'Team A', $captainId);

        $this->expectException(\LogicException::class);

        $team->requestToJoin($captainId);
    }

    #[Test]
    public function it_is_idempotent_when_the_same_player_requests_to_join_twice(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));
        $applicantId = new PlayerId('applicant@example.com');

        $team->requestToJoin($applicantId);
        $team->requestToJoin($applicantId);

        self::assertCount(1, $team->getPendingRequests());
    }

    #[Test]
    public function it_moves_an_approved_applicant_from_pending_requests_to_the_roster(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));
        $applicantId = new PlayerId('applicant@example.com');
        $team->requestToJoin($applicantId);

        $team->approveJoinRequest($applicantId);

        self::assertCount(0, $team->getPendingRequests());
        self::assertCount(2, $team->getRoster());
        self::assertTrue($applicantId->equals($team->getRoster()[1]));
    }

    #[Test]
    public function it_rejects_approving_a_join_request_that_does_not_exist(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));

        $this->expectException(\InvalidArgumentException::class);

        $team->approveJoinRequest(new PlayerId('applicant@example.com'));
    }

    #[Test]
    public function it_is_idempotent_when_approving_a_player_already_in_the_roster(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));
        $applicantId = new PlayerId('applicant@example.com');
        $team->requestToJoin($applicantId);
        $team->approveJoinRequest($applicantId);

        $team->approveJoinRequest($applicantId);

        self::assertCount(2, $team->getRoster());
    }

    #[Test]
    public function it_removes_a_rejected_applicant_from_pending_requests(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));
        $applicantId = new PlayerId('applicant@example.com');
        $team->requestToJoin($applicantId);

        $team->rejectJoinRequest($applicantId);

        self::assertCount(0, $team->getPendingRequests());
        self::assertCount(1, $team->getRoster());
    }

    #[Test]
    public function it_rejects_rejecting_a_join_request_that_does_not_exist(): void
    {
        $team = Team::create(new TeamId('a'), 'Team A', new PlayerId('captain@example.com'));

        $this->expectException(\InvalidArgumentException::class);

        $team->rejectJoinRequest(new PlayerId('applicant@example.com'));
    }
}
