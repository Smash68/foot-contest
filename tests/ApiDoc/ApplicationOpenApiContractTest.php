<?php

declare(strict_types=1);

namespace App\Tests\ApiDoc;

use App\Tests\Support\Http\OpenApiContract;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ApplicationOpenApiContractTest extends KernelTestCase
{
    #[Test]
    public function it_checks_exchanges_against_the_specification_of_the_application(): void
    {
        $contract = OpenApiContract::ofApplication(self::getContainer());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('POST /organizers: response status 201 is not explicitly documented.');

        $contract->assertSatisfiedBy(
            Request::create('/organizers', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'email' => 'organizer@example.com',
                'password' => 'super-secret',
            ], JSON_THROW_ON_ERROR)),
            new JsonResponse(['id' => '42'], 201),
        );
    }
}
