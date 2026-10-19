<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Infrastructure\UserPreference;

/**
 * Per-user preferences adapter (GET/PUT {prefix}/api/v1/auth/preferences).
 *
 * It merges the stored row over the platform defaults so a client always gets
 * a complete object, and validates input before writing. It holds no decision
 * logic: any authenticated user may read and write their own row.
 */
final class PreferencesController
{
    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'theme' => 'system',
        'language' => 'en',
        'notifications' => ['email' => true, 'push' => false],
    ];

    /**
     * @var list<string>
     */
    private const THEMES = ['light', 'dark', 'system'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $preference = UserPreference::query()
            ->where('guard', $this->guard($request))
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        return $this->ok($this->present($preference), 'Preferences loaded.');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $theme = $request->input('theme');
        $language = $request->input('language');
        $notifications = $request->input('notifications');

        $errors = [];

        if ($theme !== null && (! is_string($theme) || ! in_array($theme, self::THEMES, true))) {
            $errors['theme'][] = 'The selected theme is invalid.';
        }

        if ($language !== null && (! is_string($language) || $language === '')) {
            $errors['language'][] = 'The language must be a non-empty string.';
        }

        if ($notifications !== null && ! is_array($notifications)) {
            $errors['notifications'][] = 'The notifications must be an object.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        $preference = UserPreference::query()->firstOrNew([
            'guard' => $this->guard($request),
            'user_id' => $user->getAuthIdentifier(),
        ]);

        if (is_string($theme)) {
            $preference->theme = $theme;
        }

        if (is_string($language)) {
            $preference->language = $language;
        }

        if (is_array($notifications)) {
            $preference->notifications = array_merge(
                is_array($preference->notifications) ? $preference->notifications : [],
                $notifications,
            );
        }

        $preference->save();

        return $this->ok($this->present($preference), 'Preferences saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(?UserPreference $preference): array
    {
        $notifications = is_array($preference?->notifications) ? $preference->notifications : [];

        return [
            'theme' => $preference?->theme ?? self::DEFAULTS['theme'],
            'language' => $preference?->language ?? self::DEFAULTS['language'],
            'notifications' => array_merge(self::DEFAULTS['notifications'], $notifications),
        ];
    }

    private function guard(Request $request): string
    {
        return (string) ($request->route('guard') ?? config('platforms.default', 'web'));
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
