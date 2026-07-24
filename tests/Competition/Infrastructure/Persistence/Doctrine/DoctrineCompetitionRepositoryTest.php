<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Format\SingleElimination\BracketGeneratorWithThirdPlaceMatch;
use App\Competition\Domain\Format\SingleElimination\BracketWithThirdPlaceMatch;
use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrineCompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCompetitionRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_competition_by_its_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
    }

    #[Test]
    public function it_persists_that_registration_has_been_closed(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->team('a', 'Team A'));
        $competition->register($this->team('b', 'Team B'));
        $competition->closeRegistration();

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertFalse($found->isOpenForRegistration());
    }

    #[Test]
    public function it_persists_registered_teams(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->team('a', 'Team A'));
        $competition->register($this->team('b', 'Team B'));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertSame(2, $found->countRegistrations());
    }

    #[Test]
    public function it_persists_the_competition_name(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertSame('Summer Cup', $found->getName());
    }

    #[Test]
    public function it_persists_the_team_capacity(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 3));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);
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
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNull($found->getBracket());
    }

    #[Test]
    public function it_persists_a_freshly_generated_bracket(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->team('a', 'Team A'));
        $competition->register($this->team('b', 'Team B'));
        $competition->register($this->team('c', 'Team C'));
        $competition->closeRegistration();
        $competition->generateBracket(new SingleEliminationBracketGenerator());

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);
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
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->team('a', 'Team A'));
        $competition->register($this->team('b', 'Team B'));
        $competition->register($this->team('c', 'Team C'));
        $competition->closeRegistration();
        $competition->generateBracket(new SingleEliminationBracketGenerator());

        $playedEncounterId = new EncounterId('encounter-2');
        $expectedWinner = $competition->getBracket()->getRound(1)->findEncounterById($playedEncounterId)->getHome()->getTeamId();
        $competition->getBracket()->recordResult($playedEncounterId, EncounterResult::regularTime(Score::of(2, 0)));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);
        $playedEncounter = $found->getBracket()->getRound(1)->findEncounterById($playedEncounterId);

        self::assertTrue($playedEncounter->isCompleted());
        self::assertSame($expectedWinner->value, $playedEncounter->getWinner()->value);
    }

    #[Test]
    public function it_persists_a_bracket_with_a_third_place_match_not_yet_playable(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->team('a', 'Team A'));
        $competition->register($this->team('b', 'Team B'));
        $competition->register($this->team('c', 'Team C'));
        $competition->register($this->team('d', 'Team D'));
        $competition->closeRegistration();
        $competition->generateBracket(new BracketGeneratorWithThirdPlaceMatch(new SingleEliminationBracketGenerator()));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);
        $bracket = $found->getBracket();

        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $bracket);
        self::assertNull($bracket->getThirdPlaceEncounter());
    }

    private function team(string $teamId, string $teamName): Team
    {
        return Team::create(new TeamId($teamId), $teamName, new PlayerId("{$teamId}@example.com"));
    }
}
