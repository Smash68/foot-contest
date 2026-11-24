<?php

declare(strict_types=1);

namespace App\Tests\ApiDoc;

use App\Tests\Support\Http\OpenApiContract;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OpenApiContractTest extends TestCase
{
    private const string SPECIFICATION = <<<'JSON'
        {
            "openapi": "3.0.0",
            "info": {"title": "Fixture", "version": "1.0.0"},
            "paths": {
                "/teams/{teamId}": {
                    "get": {
                        "parameters": [{"name": "teamId", "in": "path", "required": true, "schema": {"type": "string"}}],
                        "responses": {
                            "200": {
                                "description": "The team.",
                                "content": {
                                    "application/json": {
                                        "schema": {
                                            "type": "object",
                                            "required": ["name"],
                                            "properties": {"name": {"type": "string"}}
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        JSON;

    #[Test]
    public function it_accepts_an_exchange_that_matches_the_documented_operation(): void
    {
        $contract = OpenApiContract::fromSpecification(self::SPECIFICATION);

        $contract->assertSatisfiedBy(
            Request::create('/teams/42'),
            new JsonResponse(['name' => 'Les Bleus']),
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_rejects_an_exchange_on_an_undocumented_operation(): void
    {
        $contract = OpenApiContract::fromSpecification(self::SPECIFICATION);

        $this->expectException(AssertionFailedError::class);

        $contract->assertSatisfiedBy(
            Request::create('/players/42'),
            new JsonResponse(['name' => 'Jane']),
        );
    }

    #[Test]
    public function it_rejects_a_response_body_that_does_not_match_the_documented_schema(): void
    {
        $contract = OpenApiContract::fromSpecification(self::SPECIFICATION);

        $this->expectException(AssertionFailedError::class);

        $contract->assertSatisfiedBy(
            Request::create('/teams/42'),
            new JsonResponse(['label' => 'Les Bleus']),
        );
    }
}
