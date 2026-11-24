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
}
