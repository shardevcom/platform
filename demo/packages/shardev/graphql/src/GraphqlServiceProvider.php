<?php

declare(strict_types=1);

namespace Shardevcom\Graphql;

use GraphQL\Error\DebugFlag;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Graphql\Exceptions\GraphQLErrorHandler;

/**
 * GraphQL adapter integration.
 *
 * Registers schema/resolvers (T-073), error handler (T-074), and security guards (T-074).
 * Resolvers stay thin by rule. No business logic lives here.
 */
final class GraphqlServiceProvider extends ServiceProvider
{
    /**
     * Limits are expressed as GraphQL-php validation constants.
     */
    public const MAX_QUERY_DEPTH = QueryDepth::DISABLED + 15;
    public const MAX_QUERY_COMPLEXITY = QueryComplexity::DISABLED + 1000;
    public const MAX_FIRST = 100;

    public function register(): void
    {
        // Bind the schema path
        $this->app->bind('lighthouse.schema.path', fn () => base_path('graphql/schema.graphql'));

        // Lighthouse resolves resolvers from the schema's @field directives,
        // so only the context builder they depend on needs an explicit binding.
        $this->app->bind(HttpApplicationContextBuilder::class);

        // T-CASA-022: rate.limit runs before AttemptAuthentication so anonymous
        // and authenticated requests are both bounded; mutations get their own
        // bucket (security.rate_limit.operations.graphql_mutation).
        // T-CASA-023: 'correlation' runs first so the request terminal line
        // carries the same correlation id as the lifecycle/error lines.
        // MUST live in register(): Lighthouse boot()s before this provider, so
        // the route middleware list is captured at Lighthouse registration
        // time. (The aliases resolve at request time, so registration order of
        // the aliases themselves is irrelevant here.)
        config(['lighthouse.route.middleware' => array_merge(
            ['correlation', 'rate.limit'],
            (array) config('lighthouse.route.middleware', []),
        )]);
    }

    public function boot(): void
    {
        // T-074: the domain error handler is registered in the config key
        // Lighthouse actually reads. Binding ErrorHandler::class in the
        // container did nothing, so every domain error surfaced as a generic
        // GraphQL error with no extensions.code.
        config(['lighthouse.error_handlers' => array_merge(
            (array) config('lighthouse.error_handlers', []),
            [GraphQLErrorHandler::class],
        )]);

        // Lighthouse resolves @find/@create/@update/@delete against these
        // namespaces when it maps a schema type to an Eloquent model.
        config([
            'lighthouse.namespaces.models' => array_values(array_filter(array_unique(array_merge(
                (array) config('lighthouse.namespaces.models', ['App\\Models']),
                ['App\\Models', 'Shardevcom\\Product\\Models'],
            )))),
            'lighthouse.namespaces.queries' => array_values(array_filter(array_unique(array_merge(
                (array) config('lighthouse.namespaces.queries', ['App\\GraphQL\\Queries']),
                ['App\\GraphQL\\Queries', 'Shardevcom\\Product\\Graphql\\Resolvers'],
            )))),
            'lighthouse.namespaces.mutations' => array_values(array_filter(array_unique(array_merge(
                (array) config('lighthouse.namespaces.mutations', ['App\\GraphQL\\Mutations']),
                ['App\\GraphQL\\Mutations', 'Shardevcom\\Product\\Graphql\\Resolvers'],
            )))),
        ]);

        // Security guards (T-074, T-CASA-022).
        // These keys hold GraphQL-php rule constants, not booleans or sizes:
        // DISABLED == 0. Assigning `false`/`15` makes the validator rules throw
        // on construction, so the whole endpoint 500s before the first resolver.
        config([
            'lighthouse.security.max_query_complexity' => self::MAX_QUERY_COMPLEXITY,
            'lighthouse.security.max_query_depth' => self::MAX_QUERY_DEPTH,
            'lighthouse.security.disable_introspection' => app()->environment('production')
                ? DisableIntrospection::ENABLED
                : DisableIntrospection::DISABLED,
            'lighthouse.pagination.max_count' => self::MAX_FIRST,
        ]);

        config([
            'lighthouse.debug' => app()->environment('production')
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE
                : (DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE),
        ]);
    }
}
