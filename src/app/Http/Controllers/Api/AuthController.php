<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);

        return $this->success([
            'token' => $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Registrasi customer berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda sedang tidak aktif.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $user->refresh();

        return $this->success([
            'token' => $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()),
            'Data pengguna berhasil diambil.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->noContent('Logout berhasil.');
    }
}
