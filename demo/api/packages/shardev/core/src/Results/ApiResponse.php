<?php

declare(strict_types=1);

namespace Shardevcom\Core\Results;

/**
 * Internal operation result (T-014).
 *
 * Use cases return this; adapters frame it. REST serializes toArray()
 * verbatim as the {status,code,message,data,meta} envelope. GraphQL keeps
 * its own conventions but reads the same object, so both transports share
 * status codes, messages and error details without duplication.
 */
final class ApiResponse
{
    private function __construct(
        public readonly string $status,
        public readonly string $code,
        public readonly int $httpStatus,
        public readonly string $message,
        public readonly mixed $data,
        public readonly ApiMeta $meta,
    ) {
    }

    public static function success(mixed $data = null, string $message = '', ?ApiMeta $meta = null): self
    {
        return new self('success', 'OK', 200, $message, $data, $meta ?? self::anonymousMeta());
    }

    public static function partial(mixed $data = null, string $message = '', ?ApiMeta $meta = null): self
    {
        return new self('partial', 'PARTIAL', 207, $message, $data, $meta ?? self::anonymousMeta());
    }

    public static function error(ApiError $error, ?ApiMeta $meta = null): self
    {
        $meta ??= self::anonymousMeta();

        if ($error->details !== []) {
            $meta = $meta->with(['details' => $error->details]);
        }

        return new self('error', $error->code, $error->status, $error->message, null, $meta);
    }

    /**
     * `code` is the semantic reason (RESOURCE_NOT_FOUND, VALIDATION_FAILED),
     * which the HTTP status line cannot express and a client cannot derive.
     * The numeric status travels separately as httpStatus.
     *
     * @return array{status: string, code: string, message: string, data: mixed, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
            'meta' => $this->meta->toArray(),
        ];
    }

    private static function anonymousMeta(): ApiMeta
    {
        return new ApiMeta('n/a', 'n/a');
    }
}
