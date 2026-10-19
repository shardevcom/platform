<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;

/**
 * Account notifications (GET/POST/DELETE {prefix}/api/v1/auth/notifications).
 *
 * Thin adapter over the notifiable's relation: it never builds a query off the
 * request and only ever touches notifications owned by the authenticated user,
 * so one account cannot read or mutate another's.
 */
final class NotificationsController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $items = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
            ->values()
            ->all();

        return $this->ok([
            'items' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'Notifications loaded.');
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $this->owned($request, $id);

        if ($notification === null) {
            return $this->error('RESOURCE_NOT_FOUND', 'Notification not found.', ['id' => $id], 404);
        }

        $notification->markAsRead();

        return $this->ok($this->present($notification->fresh()), 'Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $user->unreadNotifications->markAsRead();

        return $this->ok(['unread_count' => 0], 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $this->owned($request, $id);

        if ($notification === null) {
            return $this->error('RESOURCE_NOT_FOUND', 'Notification not found.', ['id' => $id], 404);
        }

        $notification->delete();

        return $this->ok(null, 'Notification deleted.');
    }

    private function owned(Request $request, string $id): ?DatabaseNotification
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return $user->notifications()->whereKey($id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $details
     */
    private function error(string $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json(
            ApiResponse::error(new ApiError($code, $message, $details, $status))->toArray(),
            $status
        );
    }

    private function ok(mixed $data, string $message): JsonResponse
    {
        return response()->json(ApiResponse::success($data, $message)->toArray());
    }
}
