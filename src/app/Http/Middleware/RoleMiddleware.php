<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles,
    ): Response|JsonResponse {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang tidak aktif.',
            ], 403);
        }

        $allowed = collect($roles)
            ->map(
                static fn (string $role): string => UserRole::tryFrom($role)?->value ?? $role,
            )
            ->all();

        $currentRole = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        if (! in_array($currentRole, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
