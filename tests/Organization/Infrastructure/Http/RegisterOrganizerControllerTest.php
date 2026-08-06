<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegisterOrganizerControllerTest extends WebTestCase
{
    #[Test]
    public function it_registers_an_organizer(): void
    {
        $client = static::createClient();

        $client->request('POST', '/organizers', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'organizer@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
    }
}
