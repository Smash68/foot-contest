<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CreateCompetitionThroughMessageBusTest extends KernelTestCase
{
    #[Test]
    public function it_persists_a_competition_when_the_command_is_dispatched_through_the_message_bus(): void
    {
        $container = self::getContainer();

        $organizations = $container->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', new OrganizerId('organizer-1')));

        $envelope = $container->get(MessageBusInterface::class)->dispatch(
            new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 'organizer-1', $organizationId->value),
        );

        $id = $envelope->last(HandledStamp::class)->getResult();

        $competition = $container->get(CompetitionRepository::class)->ofId($id);

        self::assertNotNull($competition);
        self::assertTrue($competition->isOpenForRegistration());
    }
}
