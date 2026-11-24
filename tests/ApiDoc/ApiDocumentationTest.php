<?php

declare(strict_types=1);

namespace App\Tests\ApiDoc;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocumentationTest extends WebTestCase
{
    #[Test]
    public function it_serves_the_openapi_specification(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();

        $specification = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($specification);
        self::assertArrayHasKey('openapi', $specification);
    }

    #[Test]
    public function it_serves_a_browsable_interface_for_the_specification(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
    }
}
