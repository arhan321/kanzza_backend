<?php

namespace App\Application\Services;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(array $data): array
    {
        $user = $this->users->findByEmail($data['email']);

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

        $this->users->update($user, [
            'last_login_at' => now(),
        ]);

        return [
            'user' => $user->refresh(),
            'token' => $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }
}
