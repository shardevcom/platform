<?php

declare(strict_types=1);

namespace Shardev\Documentation\DTO;

/**
 * Represents a single API endpoint documentation.
 */
final class EndpointDocumentation
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $summary,
        public readonly ?string $description,
        public readonly string $operationId,
        public readonly array $tags,
        public readonly array $parameters,  // path, query, header
        public readonly ?array $requestBody, // OpenAPI requestBody
        public readonly array $responses,    // statusCode => response spec
        public readonly array $security,     // security requirements
        public readonly bool $deprecated,
        public readonly array $extensions,   // x-* extensions
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'] ?? '',
            path: $data['path'] ?? '',
            summary: $data['summary'] ?? '',
            description: $data['description'] ?? null,
            operationId: $data['operationId'] ?? '',
            tags: $data['tags'] ?? [],
            parameters: $data['parameters'] ?? [],
            requestBody: $data['requestBody'] ?? null,
            responses: $data['responses'] ?? [],
            security: $data['security'] ?? [],
            deprecated: $data['deprecated'] ?? false,
            extensions: $data['extensions'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'summary' => $this->summary,
            'description' => $this->description,
            'operationId' => $this->operationId,
            'tags' => $this->tags,
            'parameters' => $this->parameters,
            'requestBody' => $this->requestBody,
            'responses' => $this->responses,
            'security' => $this->security,
            'deprecated' => $this->deprecated,
            'extensions' => $this->extensions,
        ];
    }
}