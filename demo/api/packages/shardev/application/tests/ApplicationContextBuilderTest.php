<?php

declare(strict_types=1);

namespace Shardevcom\Application\Tests;

use Shardevcom\Application\ApplicationContext\BaseApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\CliApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\GraphQLApplicationContextBuilder;
use Shardevcom\Application\ApplicationContext\JobApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @internal
 */
final class ApplicationContextBuilderTest extends TestCase
{
    private BaseApplicationContextBuilder $baseBuilder;
    private CliApplicationContextBuilder $cliBuilder;
    private JobApplicationContextBuilder $jobBuilder;
    private GraphQLApplicationContextBuilder $graphqlBuilder;

    protected function setUp(): void
    {
        $mockResolver = new class implements PermissionResolver {
            public function can(ApplicationContext $context, string $resource, \Shardevcom\Core\ResourceAction|string $action): bool
            {
                $actionStr = $action instanceof \Shardevcom\Core\ResourceAction ? $action->value : $action;
                return in_array("{$resource}.{$actionStr}", $context->permissions(), true);
            }
        };

        $this->baseBuilder = new BaseApplicationContextBuilder($mockResolver);
        $this->cliBuilder = new CliApplicationContextBuilder($this->baseBuilder);
        $this->jobBuilder = new JobApplicationContextBuilder($this->baseBuilder);
        $this->graphqlBuilder = new GraphQLApplicationContextBuilder($this->baseBuilder);
    }

    public function testBaseBuilderBuildsContextFromIdentity(): void
    {
        $identity = new ApplicationIdentity(
            id: 'user-123',
            kind: 'user',
            attributes: [
                'abilities' => ['products.view', 'products.create'],
                'tenant_id' => 'tenant-456',
                'device' => ['name' => 'iPhone'],
            ],
        );

        $context = $this->baseBuilder->fromIdentity($identity);

        $this->assertSame('user-123', $context->userId());
        $this->assertSame('tenant-456', $context->tenantId());
        $this->assertSame(['products.view', 'products.create'], $context->permissions());
        $this->assertSame('iPhone', $context->deviceId());
        $this->assertNotNull($context->requestId());
        $this->assertSame($context->requestId(), $context->correlationId());
    }

    public function testBaseBuilderBuildsGuestContext(): void
    {
        $context = $this->baseBuilder->forGuest([
            'tenantId' => 'tenant-789',
            'locale' => 'es',
        ]);

        $this->assertNull($context->userId());
        $this->assertSame('tenant-789', $context->tenantId());
        $this->assertSame('es', $context->locale());
        $this->assertSame([], $context->permissions());
    }

    public function testBaseBuilderUsesDefaultsForMissingValues(): void
    {
        $builder = new BaseApplicationContextBuilder(
            new class implements PermissionResolver {
                public function can(ApplicationContext $context, string $resource, \Shardevcom\Core\ResourceAction|string $action): bool { return false; }
            },
            ['locale' => 'fr', 'timezone' => 'Europe/Paris', 'application_id' => 'app-1']
        );

        $identity = new ApplicationIdentity(id: 'user-1', kind: 'user');
        $context = $builder->fromIdentity($identity);

        $this->assertSame('fr', $context->locale());
        $this->assertSame('Europe/Paris', $context->timezone());
        $this->assertSame('app-1', $context->applicationId());
    }

    public function testCliBuilderBuildsFromInput(): void
    {
        $input = $this->createMockInput([
            'user-id' => 'cli-user',
            'tenant-id' => 'cli-tenant',
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
        ]);

        $context = $this->cliBuilder->fromInput($input);

        $this->assertSame('cli-user', $context->userId());
        $this->assertSame('cli-tenant', $context->tenantId());
        $this->assertSame('de', $context->locale());
        $this->assertSame('Europe/Berlin', $context->timezone());
        $this->assertSame([], $context->permissions());
    }

    public function testCliBuilderBuildsGuestFromInputWithoutUserId(): void
    {
        $input = $this->createMockInput([
            'tenant-id' => 'cli-tenant',
        ]);

        $context = $this->cliBuilder->fromInput($input);

        $this->assertNull($context->userId());
        $this->assertSame('cli-tenant', $context->tenantId());
    }

    public function testJobBuilderBuildsFromPayloadWithContext(): void
    {
        $payload = [
            'context' => [
                'userId' => 'job-user',
                'tenantId' => 'job-tenant',
                'locale' => 'en',
                'timezone' => 'UTC',
                'permissions' => ['products.view'],
                'requestId' => 'req-123',
                'correlationId' => 'corr-123',
            ],
            'tenant_id' => 'overridden-tenant',
        ];

        $context = $this->jobBuilder->fromPayload($payload);

        $this->assertSame('job-user', $context->userId());
        $this->assertSame('overridden-tenant', $context->tenantId());
        $this->assertSame(['products.view'], $context->permissions());
        $this->assertSame('req-123', $context->requestId());
        $this->assertSame('corr-123', $context->correlationId());
    }

    public function testJobBuilderBuildsFromFlatPayload(): void
    {
        $payload = [
            'user_id' => 'job-user-2',
            'tenant_id' => 'job-tenant-2',
            'locale' => 'it',
            'capabilities' => ['batch.process'],
        ];

        $context = $this->jobBuilder->fromPayload($payload);

        $this->assertSame('job-user-2', $context->userId());
        $this->assertSame('job-tenant-2', $context->tenantId());
        $this->assertSame('it', $context->locale());
        $this->assertSame(['batch.process'], $context->capabilities());
    }

    public function testJobBuilderBuildsGuestFromPayloadWithoutUser(): void
    {
        $payload = [
            'tenant_id' => 'job-tenant-3',
        ];

        $context = $this->jobBuilder->fromPayload($payload);

        $this->assertNull($context->userId());
        $this->assertSame('job-tenant-3', $context->tenantId());
    }

    public function testGraphQLBuilderBuildsFromResolverContext(): void
    {
        $identity = new ApplicationIdentity(
            id: 'gql-user',
            kind: 'user',
            attributes: ['abilities' => ['products.create']],
        );

        $context = $this->graphqlBuilder->fromResolverContext([
            'authentication' => $identity,
            'locale' => 'pt',
            'tenant_id' => 'gql-tenant',
        ]);

        $this->assertSame('gql-user', $context->userId());
        $this->assertSame(['products.create'], $context->permissions());
        $this->assertSame('pt', $context->locale());
        $this->assertSame('gql-tenant', $context->tenantId());
    }

    public function testGraphQLBuilderBuildsGuestFromResolverContext(): void
    {
        $context = $this->graphqlBuilder->fromResolverContext([
            'locale' => 'ja',
            'timezone' => 'Asia/Tokyo',
        ]);

        $this->assertNull($context->userId());
        $this->assertSame('ja', $context->locale());
        $this->assertSame('Asia/Tokyo', $context->timezone());
    }

    private function createMockInput(array $options): InputInterface
    {
        $definition = new InputDefinition([
            new InputOption('user-id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('tenant-id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('application-id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('device-id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('locale', null, InputOption::VALUE_OPTIONAL),
            new InputOption('timezone', null, InputOption::VALUE_OPTIONAL),
            new InputOption('request-id', null, InputOption::VALUE_OPTIONAL),
            new InputOption('correlation-id', null, InputOption::VALUE_OPTIONAL),
        ]);

        $input = new class($options, $definition) implements InputInterface {
            private array $options;
            private InputDefinition $definition;

            public function __construct(array $options, InputDefinition $definition)
            {
                $this->options = $options;
                $this->definition = $definition;
            }

            public function getOption(string $name): mixed { return $this->options[$name] ?? null; }
            public function getArgument(string $name): mixed { return null; }
            public function getArguments(): array { return []; }
            public function getOptions(): array { return $this->options; }
            public function setOption(string $name, mixed $value): void { $this->options[$name] = $value; }
            public function setArgument(string $name, mixed $value): void {}
            public function hasOption(string $name): bool { return isset($this->options[$name]); }
            public function hasArgument(string $name): bool { return false; }
            public function isInteractive(): bool { return false; }
            public function setInteractive(bool $interactive): void {}
            public function getFirstArgument(): ?string { return null; }
            public function bind(InputDefinition $definition): void { $this->definition = $definition; }
            public function validate(): void {}
            public function getStream(): ?\Symfony\Component\Console\Input\InputStream { return null; }
            public function setStream(\Symfony\Component\Console\Input\InputStream $stream): void {}
            public function getDefinition(): InputDefinition { return $this->definition; }
            public function hasParameterOption(string|array $values, bool $onlyParams = false): bool { return false; }
            public function getParameterOption(string|array $values, mixed $default = false, bool $onlyParams = false): mixed { return $default; }
            public function __toString(): string { return ''; }
        };

        return $input;
    }
}