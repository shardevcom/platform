<?php

declare(strict_types=1);

namespace Shardevcom\Documentation\DTO;

/**
 * Represents GraphQL API documentation (SDL + introspection).
 */
final class GraphQLDocumentation
{
    /**
     * @param array<string, mixed> $types
     * @param array<string, mixed> $queries
     * @param array<string, mixed> $mutations
     * @param array<string, mixed> $subscriptions
     * @param array<string, mixed> $directives
     * @param array<string, mixed> $scalars
     */
    public function __construct(
        public readonly string $schemaSdl,
        public readonly array $types,
        public readonly array $queries,
        public readonly array $mutations,
        public readonly array $subscriptions,
        public readonly array $directives,
        public readonly array $scalars,
        public readonly array $introspection,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schemaSdl: $data['schemaSdl'] ?? '',
            types: $data['types'] ?? [],
            queries: $data['queries'] ?? [],
            mutations: $data['mutations'] ?? [],
            subscriptions: $data['subscriptions'] ?? [],
            directives: $data['directives'] ?? [],
            scalars: $data['scalars'] ?? [],
            introspection: $data['introspection'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaSdl' => $this->schemaSdl,
            'types' => $this->types,
            'queries' => $this->queries,
            'mutations' => $this->mutations,
            'subscriptions' => $this->subscriptions,
            'directives' => $this->directives,
            'scalars' => $this->scalars,
            'introspection' => $this->introspection,
        ];
    }
}