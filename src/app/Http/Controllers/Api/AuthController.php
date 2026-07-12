<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\AuthService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ], 'Registrasi customer berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
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
        $this->authService->logout($request->user());

        return $this->noContent('Logout berhasil.');
    }
}
