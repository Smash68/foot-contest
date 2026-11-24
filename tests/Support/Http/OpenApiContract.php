<?php

declare(strict_types=1);

namespace App\Tests\Support\Http;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OpenAPI specification seen as a contract: an HTTP exchange satisfies it only if the
 * specification documents its operation.
 */
final readonly class OpenApiContract
{
    private function __construct(
        private ServerRequestValidator $requestValidator,
        private PsrHttpFactory $psrHttpFactory,
    ) {
    }

    public static function fromSpecification(string $json): self
    {
        $psr17Factory = new Psr17Factory();

        return new self(
            new ValidatorBuilder()->fromJson($json)->getServerRequestValidator(),
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
        );
    }

    public function assertSatisfiedBy(Request $request, Response $response): void
    {
        try {
            $this->requestValidator->validate($this->psrHttpFactory->createRequest($request));
        } catch (ValidationFailed $failure) {
            Assert::fail("{$request->getMethod()} {$request->getPathInfo()}: {$failure->getMessage()}");
        }
    }
}
