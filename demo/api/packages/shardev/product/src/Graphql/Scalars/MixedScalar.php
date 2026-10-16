<?php

declare(strict_types=1);

namespace Shardevcom\Product\Graphql\Scalars;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ScalarType;

/**
 * The GraphQL `Mixed` scalar, backed by an arbitrarily-typed JSON value.
 *
 * It only moves a value across the transport boundary: the use case still
 * validates every filter against the resource allow-list. The class cannot be
 * called Mixed because PHP reserves that word.
 */
final class MixedScalar extends ScalarType
{
    public string $name = 'Mixed';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral(\GraphQL\Language\AST\Node $node, ?array $variables = null): mixed
    {
        return match (true) {
            $node instanceof StringValueNode => $node->value,
            $node instanceof IntValueNode => (int) $node->value,
            $node instanceof FloatValueNode => (float) $node->value,
            $node instanceof BooleanValueNode => $node->value,
            $node instanceof NullValueNode => null,
            $node instanceof ListValueNode => array_map(
                fn ($value) => $this->parseLiteral($value, $variables),
                iterator_to_array($node->values->getIterator()),
            ),
            default => Parser::parseValue($node),
        };
    }
}
