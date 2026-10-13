<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Illuminate\Http\Request;
use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Factory for creating transport-appropriate ApplicationContext builders.
 *
 * Centralizes the creation logic and ensures consistent configuration
 * across all transport layers.
 */
final class ApplicationContextBuilderFactory
{
    public function __construct(
        private readonly BaseApplicationContextBuilder $baseBuilder,
        private readonly HttpApplicationContextBuilder $httpBuilder,
        private readonly CliApplicationContextBuilder $cliBuilder,
        private readonly JobApplicationContextBuilder $jobBuilder,
        private readonly GraphQLApplicationContextBuilder $graphqlBuilder,
    ) {}

    public function http(): HttpApplicationContextBuilder
    {
        return $this->httpBuilder;
    }

    public function cli(): CliApplicationContextBuilder
    {
        return $this->cliBuilder;
    }

    public function job(): JobApplicationContextBuilder
    {
        return $this->jobBuilder;
    }

    public function graphql(): GraphQLApplicationContextBuilder
    {
        return $this->graphqlBuilder;
    }

    /**
     * Get the base builder for direct identity-to-context conversion.
     */
    public function base(): BaseApplicationContextBuilder
    {
        return $this->baseBuilder;
    }

    /**
     * Convenience method to build context from HTTP request.
     */
    public function fromRequest(Request $request): ApplicationContext
    {
        return $this->httpBuilder->fromRequest($request);
    }

    /**
     * Convenience method to build context from console input.
     */
    public function fromInput(InputInterface $input): ApplicationContext
    {
        return $this->cliBuilder->fromInput($input);
    }

    /**
     * Convenience method to build context from job payload.
     */
    public function fromPayload(array $payload): ApplicationContext
    {
        return $this->jobBuilder->fromPayload($payload);
    }

    /**
     * Convenience method to build context from GraphQL resolver context.
     */
    public function fromResolverContext(array $context): ApplicationContext
    {
        return $this->graphqlBuilder->fromResolverContext($context);
    }

    /**
     * Build context directly from an ApplicationIdentity.
     */
    public function fromIdentity(ApplicationIdentity $identity, array $additional = []): ApplicationContext
    {
        return $this->baseBuilder->fromIdentity($identity, $additional);
    }

    /**
     * Build a guest context.
     */
    public function forGuest(array $additional = []): ApplicationContext
    {
        return $this->baseBuilder->forGuest($additional);
    }
}