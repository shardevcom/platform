<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\Contracts\ConfigurationProvider;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Contracts\QueryExecutor;
use Shardevcom\Core\Contracts\ResourceRegistry;
use Shardevcom\Core\Contracts\ResourceRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-010 acceptance: every boundary contract exists with its exact
 * operation surface, documents its boundary, and stays Illuminate-free.
 *
 * Referenced value types (ApplicationContext, ResourceAction, ...) land
 * in T-011/T-012/T-020; PHP resolves signature types lazily, so the
 * contracts compile and reflect without them.
 */
final class ContractsTest extends TestCase
{
    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function contracts(): array
    {
        return [
            'authorization manager' => [AuthorizationManager::class, ['authorize']],
            'resource registry' => [ResourceRegistry::class, ['register', 'get', 'has', 'all']],
            'query executor' => [QueryExecutor::class, ['execute']],
            'authentication provider' => [AuthenticationProvider::class, ['identityFromToken']],
            'permission resolver' => [PermissionResolver::class, ['can']],
            'configuration provider' => [ConfigurationProvider::class, ['key', 'visibility', 'resolve']],
            'resource repository' => [ResourceRepository::class, ['resourceName', 'supportedOperations']],
        ];
    }

    #[DataProvider('contracts')]
    public function test_contract_exists_with_expected_surface(string $contract, array $methods): void
    {
        $this->assertTrue(interface_exists($contract), "{$contract} must be an interface");

        $reflection = new \ReflectionClass($contract);

        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "{$contract} must declare {$method}()");
        }

        $this->assertSame($methods, array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $reflection->getMethods()
        ), "{$contract} must not grow ceremonial methods");

        $doc = (string) $reflection->getDocComment();
        $this->assertStringContainsString('Boundary:', $doc, "{$contract} must justify its boundary");
    }

    public function test_contracts_do_not_import_illuminate(): void
    {
        $files = glob(dirname(__DIR__, 3).'/packages/shardevcom/core/src/Contracts/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                'Illuminate',
                $source,
                basename($file).' must stay framework-agnostic'
            );
        }
    }
}
