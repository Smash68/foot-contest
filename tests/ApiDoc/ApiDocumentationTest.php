<?php

declare(strict_types=1);

namespace App\Tests\ApiDoc;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ApiDocumentationTest extends WebTestCase
{
    #[Test]
    public function it_serves_the_openapi_specification(): void
    {
        $specification = $this->fetchSpecification();

        self::assertArrayHasKey('openapi', $specification);
    }

    #[Test]
    public function it_declares_jwt_bearer_authentication(): void
    {
        $specification = $this->fetchSpecification();

        self::assertIsArray($specification['components'] ?? null);
        self::assertIsArray($specification['components']['securitySchemes'] ?? null);
        self::assertEquals(
            ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
            $specification['components']['securitySchemes']['Bearer'] ?? null,
        );
    }

    #[Test]
    public function it_documents_the_business_error_responses(): void
    {
        $specification = $this->fetchSpecification();

        self::assertEquals(
            ['type' => 'object', 'required' => ['error'], 'properties' => ['error' => ['type' => 'string']]],
            self::component($specification, 'schemas', 'Error'),
        );
        foreach (['BusinessRuleViolation', 'Conflict', 'Forbidden', 'NotFound', 'InvalidCredentials'] as $response) {
            self::assertResponseComponentUsesSchema($specification, $response, 'Error');
        }
    }

    #[Test]
    public function it_documents_the_authentication_error_response(): void
    {
        $specification = $this->fetchSpecification();

        self::assertEquals(
            [
                'type' => 'object',
                'required' => ['code', 'message'],
                'properties' => ['code' => ['type' => 'integer'], 'message' => ['type' => 'string']],
            ],
            self::component($specification, 'schemas', 'AuthenticationError'),
        );
        self::assertResponseComponentUsesSchema($specification, 'Unauthenticated', 'AuthenticationError');
    }

    #[Test]
    public function it_serves_a_browsable_interface_for_the_specification(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
    }

    #[Test]
    public function it_serves_the_interface_assets_from_the_application_itself(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/doc');

        $assetUrls = $crawler
            ->filterXPath('//script[@src] | //link[@rel="stylesheet"] | //img[@src]')
            ->each(static fn (Crawler $node): string => (string) ($node->attr('src') ?? $node->attr('href')));

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);

        self::assertNotEmpty($assetUrls);
        foreach ($assetUrls as $assetUrl) {
            self::assertStringStartsWith('/', $assetUrl);
            self::assertStringStartsNotWith('//', $assetUrl);
            self::assertFileExists($projectDir.'/public'.$assetUrl);
        }
    }

    /**
     * @return array<mixed>
     */
    private function fetchSpecification(): array
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();

        $specification = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($specification);

        return $specification;
    }

    /**
     * @param array<mixed> $specification
     */
    private static function component(array $specification, string $type, string $name): mixed
    {
        self::assertIsArray($specification['components'] ?? null);
        self::assertIsArray($specification['components'][$type] ?? null);
        self::assertArrayHasKey($name, $specification['components'][$type]);

        return $specification['components'][$type][$name];
    }

    /**
     * @param array<mixed> $specification
     */
    private static function assertResponseComponentUsesSchema(array $specification, string $response, string $schema): void
    {
        $component = self::component($specification, 'responses', $response);
        self::assertIsArray($component);
        self::assertArrayHasKey('description', $component);
        self::assertEquals(
            ['application/json' => ['schema' => ['$ref' => '#/components/schemas/'.$schema]]],
            $component['content'] ?? null,
        );
    }
}
