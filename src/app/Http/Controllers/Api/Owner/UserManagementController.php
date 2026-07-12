<?php

namespace App\Http\Controllers\Api\Owner;

use App\Application\Services\UserManagementService;
use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\User\StoreStaffRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserManagementController extends ApiController
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->userManagementService->paginate($request->query()),
        )->additional([
            'success' => true,
            'message' => 'Daftar pengguna berhasil diambil.',
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $user = $this->userManagementService->createStaff(
            $request->validated(),
        );

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
        $user = $this->userManagementService->updateRole(
            $request->user(),
            $user,
            UserRole::from($request->validated('role')),
        );

        return $this->success(
            new UserResource($user),
            'Role pengguna berhasil diperbarui.',
        );
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
    ): JsonResponse {
        $user = $this->userManagementService->updateStatus(
            $request->user(),
            $user,
            UserStatus::from($request->validated('status')),
        );

        return $this->success(
            new UserResource($user),
            'Status pengguna berhasil diperbarui.',
        );
    }
}
