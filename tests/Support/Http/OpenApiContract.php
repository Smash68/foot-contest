<?php

declare(strict_types=1);

namespace App\Tests\Support\Http;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OpenAPI specification seen as a contract: an HTTP exchange satisfies it only if the
 * specification documents its operation, and the response matches what it documents.
 */
final readonly class OpenApiContract
{
    private function __construct(
        private ServerRequestValidator $requestValidator,
        private ResponseValidator $responseValidator,
        private PsrHttpFactory $psrHttpFactory,
    ) {
    }

    public static function fromSpecification(string $json): self
    {
        $validatorBuilder = new ValidatorBuilder()->fromJson($json);
        $psr17Factory = new Psr17Factory();

        return new self(
            $validatorBuilder->getServerRequestValidator(),
            $validatorBuilder->getResponseValidator(),
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
        );
    }

    public function assertSatisfiedBy(Request $request, Response $response): void
    {
        try {
            $operation = $this->requestValidator->validate($this->psrHttpFactory->createRequest($request));
            $this->responseValidator->validate($operation, $this->psrHttpFactory->createResponse($response));
        } catch (ValidationFailed $failure) {
            Assert::fail("{$request->getMethod()} {$request->getPathInfo()}: {$failure->getMessage()}");
        }
    }
}
