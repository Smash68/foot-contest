<?php

declare(strict_types=1);

namespace App\Tests\Support\Http;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OpenAPI specification seen as a contract: an HTTP exchange satisfies it only if the
 * specification documents its operation and explicitly documents its response status, and the
 * response matches what it documents.
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

    public static function ofApplication(ContainerInterface $container): self
    {
        $generator = $container->get('nelmio_api_doc.generator.default');
        assert($generator instanceof ApiDocGenerator);

        return self::fromSpecification($generator->generate()->toJson());
    }

    public function assertSatisfiedBy(Request $request, Response $response): void
    {
        $exchange = "{$request->getMethod()} {$request->getPathInfo()}";

        try {
            $operation = $this->requestValidator->validate($this->psrHttpFactory->createRequest($request));
        } catch (ValidationFailed $failure) {
            Assert::fail("{$exchange}: {$failure->getMessage()}");
        }

        Assert::assertTrue(
            $this->documentsStatus($operation, $response->getStatusCode()),
            "{$exchange}: response status {$response->getStatusCode()} is not explicitly documented.",
        );

        try {
            $this->responseValidator->validate($operation, $this->psrHttpFactory->createResponse($response));
        } catch (ValidationFailed $failure) {
            Assert::fail("{$exchange}: {$failure->getMessage()}");
        }
    }

    /**
     * A catch-all "default" response does not count: NelmioApiDocBundle adds an empty one to
     * every operation, which would otherwise let any undocumented response through.
     */
    private function documentsStatus(OperationAddress $operation, int $status): bool
    {
        $pathItem = $this->responseValidator->getSchema()->paths->getPath($operation->path());
        $documentedOperation = $pathItem?->getOperations()[$operation->method()] ?? null;

        return $documentedOperation?->responses?->hasResponse((string) $status) ?? false;
    }
}
