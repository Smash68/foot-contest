<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Format\SingleElimination\BracketWithThirdPlaceMatch;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrineCompetitionRepository;
use App\Tests\Support\Assertion\CompetitionAssert;
use App\Tests\Support\Builder\CompetitionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCompetitionRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_competition_by_its_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()->build();
        $id = $competition->getId();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertEquals($id, $found->getId());
    }

    #[Test]
    public function it_persists_the_bracket_configuration(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()->withThirdPlaceMatch()->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());

        self::assertNotNull($found);
        self::assertSame(CompetitionFormat::SingleElimination, $found->getFormat());
        self::assertTrue($found->includesThirdPlaceMatch());
    }

    #[Test]
    public function it_persists_that_registration_has_been_closed(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());

        self::assertNotNull($found);
        self::assertFalse($found->isOpenForRegistration());
    }

    #[Test]
    public function it_persists_registered_teams(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());

        self::assertNotNull($found);
        CompetitionAssert::assertThat($found)->hasRegisteredTeamsCount(2);
    }

    #[Test]
    public function it_persists_the_competition_name(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());

        self::assertNotNull($found);
        self::assertSame('Summer Cup', $found->getName());
    }

    #[Test]
    public function it_persists_the_team_capacity(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()->withCapacity(2, 3)->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $found->register($this->team('a', 'Team A'));
        $found->register($this->team('b', 'Team B'));
        $found->register($this->team('c', 'Team C'));

        $this->expectException(\LogicException::class);

        $found->register($this->team('d', 'Team D'));
    }

    #[Test]
    public function it_persists_that_no_bracket_has_been_generated_yet(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());

        self::assertNotNull($found);
        self::assertNull($found->getBracket());
    }

    #[Test]
    public function it_persists_a_freshly_generated_bracket(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withTeam('Team C', captainId: 'captain-c')
            ->withBracketGenerated()
            ->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $bracket = $found->getBracket();

        self::assertNotNull($bracket);
        self::assertSame(2, $bracket->countRounds());
        self::assertSame(2, $bracket->getRound(1)->countEncounters());
        self::assertSame(1, $bracket->getRound(2)->countEncounters());
    }

    #[Test]
    public function it_persists_a_recorded_encounter_result(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withTeam('Team C', captainId: 'captain-c')
            ->withBracketGenerated()
            ->build();

        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);
        $playedEncounterId = new EncounterId('encounter-2');
        $encounterToPlay = $bracket->getRound(1)->findEncounterById($playedEncounterId);
        self::assertNotNull($encounterToPlay);
        $expectedWinner = $encounterToPlay->getHome()->getTeamId();
        $bracket->recordResult($playedEncounterId, EncounterResult::regularTime(Score::of(2, 0)));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $reloadedBracket = $found->getBracket();
        self::assertNotNull($reloadedBracket);
        $playedEncounter = $reloadedBracket->getRound(1)->findEncounterById($playedEncounterId);

        self::assertNotNull($playedEncounter);
        self::assertTrue($playedEncounter->isCompleted());
        self::assertSame($expectedWinner->value, $playedEncounter->getWinner()->value);
    }

    #[Test]
    public function it_persists_a_bracket_with_a_third_place_match_not_yet_playable(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withThirdPlaceMatch()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withTeam('Team C', captainId: 'captain-c')
            ->withTeam('Team D', captainId: 'captain-d')
            ->withBracketGenerated()
            ->build();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $bracket = $found->getBracket();

        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $bracket);
        self::assertNull($bracket->getThirdPlaceEncounter());
    }

    #[Test]
    public function it_persists_a_third_place_match_once_both_semi_finals_are_played(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withThirdPlaceMatch()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withTeam('Team C', captainId: 'captain-c')
            ->withTeam('Team D', captainId: 'captain-d')
            ->withBracketGenerated()
            ->build();

        $semiFinalOneId = new EncounterId('encounter-1');
        $semiFinalTwoId = new EncounterId('encounter-2');
        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);
        $semiFinalOne = $bracket->findEncounterById($semiFinalOneId);
        $semiFinalTwo = $bracket->findEncounterById($semiFinalTwoId);
        self::assertNotNull($semiFinalOne);
        self::assertNotNull($semiFinalTwo);
        $expectedLoserOne = $semiFinalOne->getAway()->getTeamId();
        $expectedLoserTwo = $semiFinalTwo->getAway()->getTeamId();
        $bracket->recordResult($semiFinalOneId, EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult($semiFinalTwoId, EncounterResult::regularTime(Score::of(3, 1)));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $reloadedBracket = $found->getBracket();
        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $reloadedBracket);
        $thirdPlaceEncounter = $reloadedBracket->getThirdPlaceEncounter();

        self::assertNotNull($thirdPlaceEncounter);
        self::assertFalse($thirdPlaceEncounter->isCompleted());
        self::assertSame($expectedLoserOne->value, $thirdPlaceEncounter->getHome()->getTeamId()->value);
        self::assertSame($expectedLoserTwo->value, $thirdPlaceEncounter->getAway()->getTeamId()->value);
    }

    #[Test]
    public function it_persists_a_played_third_place_match_result(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $competition = CompetitionBuilder::aCompetition()
            ->withThirdPlaceMatch()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withTeam('Team C', captainId: 'captain-c')
            ->withTeam('Team D', captainId: 'captain-d')
            ->withBracketGenerated()
            ->build();

        $bracket = $competition->getBracket();
        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $bracket);
        $bracket->recordResult(new EncounterId('encounter-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('encounter-2'), EncounterResult::regularTime(Score::of(3, 1)));
        $thirdPlaceEncounter = $bracket->getThirdPlaceEncounter();
        self::assertNotNull($thirdPlaceEncounter);
        $expectedWinner = $thirdPlaceEncounter->getHome()->getTeamId();
        $bracket->recordResult($thirdPlaceEncounter->id, EncounterResult::regularTime(Score::of(1, 0)));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($competition->getId());
        self::assertNotNull($found);
        $reloadedBracket = $found->getBracket();
        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $reloadedBracket);
        $reloadedThirdPlaceEncounter = $reloadedBracket->getThirdPlaceEncounter();

        self::assertNotNull($reloadedThirdPlaceEncounter);
        self::assertTrue($reloadedThirdPlaceEncounter->isCompleted());
        self::assertSame($expectedWinner->value, $reloadedThirdPlaceEncounter->getWinner()->value);
    }

    private function team(string $teamId, string $teamName): Team
    {
        return Team::create(new TeamId($teamId), $teamName, new PlayerId("{$teamId}@example.com"));
    }
}
