<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated eager-load allow-list (T-040).
 *
 * Accepts a CSV string ("brand,images") or an array. Every entry must be
 * declared in the definition — arbitrary relationship loading from clients
 * is rejected, which is what keeps GraphQL field selection safe later.
 */
final class IncludeSet
{
    /**
     * @param list<string> $includes
     */
    private function __construct(private array $includes)
    {
    }

    public static function fromString(string $input, ResourceDefinition $definition): self
    {
        return self::fromArray(
            array_filter(array_map(trim(...), explode(',', $input))),
            $definition
        );
    }

    /**
     * @param list<string> $input
     */
    public static function fromArray(array $input, ResourceDefinition $definition): self
    {
        $errors = [];
        $includes = [];

        foreach ($input as $include) {
            if (! is_string($include) || $include === '' || ! $definition->allowsInclude($include)) {
                $errors['includes.'.$include] = ['Include is not allowed for this resource.'];

                continue;
            }

            $includes[] = $include;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self(array_values(array_unique($includes)));
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->includes;
    }

    public function count(): int
    {
        return count($this->includes);
    }
}
