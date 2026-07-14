<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\User\StoreStaffRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UserManagementController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return UserResource::collection(
            User::query()
                ->filter($request->query())
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar pengguna berhasil diambil.',
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
            'status' => UserStatus::Active,
        ]);

        return $this->success(
            new UserResource($user),
            'Akun staff berhasil dibuat.',
            201,
        );
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
    ): JsonResponse {
        $this->preventSelfModification($request, $user);
        $role = UserRole::from($request->validated('role'));

        if (! in_array($role, [UserRole::Cashier, UserRole::Driver], true)) {
            throw ValidationException::withMessages([
                'role' => ['Owner hanya dapat mengatur role cashier atau driver melalui endpoint ini.'],
            ]);
        }

        $user->update(['role' => $role]);
        $user->refresh();

        return $this->success(
            new UserResource($user),
            'Role pengguna berhasil diperbarui.',
        );
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
    ): JsonResponse {
        $this->preventSelfModification($request, $user);
        $user->update([
            'status' => UserStatus::from($request->validated('status')),
        ]);
        $user->refresh();

        return $this->success(
            new UserResource($user),
            'Status pengguna berhasil diperbarui.',
        );
    }

    private function preventSelfModification(Request $request, User $target): void
    {
        if ($request->user()->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['Owner tidak dapat mengubah role atau status akunnya sendiri.'],
            ]);
        }
    }
}
