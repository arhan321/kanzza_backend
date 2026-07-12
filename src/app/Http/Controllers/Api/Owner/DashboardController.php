<?php

namespace App\Http\Controllers\Api\Owner;

use App\Application\Services\DashboardService;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->success(
            $this->dashboardService->ownerSummary(),
            'Ringkasan dashboard owner berhasil diambil.',
        );
    }
}
