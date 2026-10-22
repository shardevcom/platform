<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Communications\Application\Queries\ListMessages;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;

final class MessageController
{
    public function __construct(
        private readonly ListMessages $listMessages,
        private readonly AuthorizationManager $authorization,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'view', $context);

        $page = $this->listMessages->execute($request->query());

        return response()->json(ApiResponse::success(
            $page->items,
            'Communication messages retrieved.',
            ApiMeta::fromContext($context)->with(['pagination' => $page->pagination->toArray()]),
        )->toArray());
    }
}
